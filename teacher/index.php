<?php
// Start session
session_start();

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once '../config/database.php';

// Set page title
$page_title = 'Teacher Dashboard';

// Get teacher ID
$teacher_id = $_SESSION['teacher_id'];

// Get teacher name
$teacher_name = $_SESSION['name'] ?? 'Teacher';

// Get today's date
$today = date('Y-m-d');
$day_of_week = date('N'); // 1 (Monday) to 7 (Sunday)
$current_time = date('H:i:s');

// Count all classes for today that don't have attendance records
$current_day_name = date('l'); // Monday, Tuesday, etc.

$missed_classes_count_query = "
    SELECT 
        COUNT(*) as count
    FROM 
        assignments a
    WHERE 
        a.teacher_id = ? AND
        JSON_CONTAINS(a.preferred_day, ?) AND
        NOT EXISTS (
            SELECT 1 FROM attendance att 
            WHERE att.teacher_id = a.teacher_id 
            AND att.subject_id = a.subject_id 
            AND att.assignment_id = a.id 
            AND att.attendance_date = ?
        )";

$current_day_json = '"' . $current_day_name . '"'; // Format: "Monday"
$stmt = mysqli_prepare($conn, $missed_classes_count_query);
mysqli_stmt_bind_param($stmt, "iss", $teacher_id, $current_day_json, $today);
mysqli_stmt_execute($stmt);
$missed_count_result = mysqli_stmt_get_result($stmt);
$missed_classes_count = $missed_count_result->fetch_assoc()['count'];
mysqli_stmt_close($stmt);

// Get statistics for dashboard cards

// 1. Count assigned subjects
$subjects_query = "
    SELECT COUNT(DISTINCT subject_id) as total_subjects
    FROM assignments
    WHERE teacher_id = ?";
$subjects_stmt = mysqli_prepare($conn, $subjects_query);
mysqli_stmt_bind_param($subjects_stmt, "i", $teacher_id);
mysqli_stmt_execute($subjects_stmt);
$subjects_result = mysqli_stmt_get_result($subjects_stmt);
$total_subjects = $subjects_result->fetch_assoc()['total_subjects'];
mysqli_stmt_close($subjects_stmt);

// 2. Count total students
$students_query = "
    SELECT COUNT(DISTINCT e.student_id) as total_students
    FROM enrollments e
    JOIN assignments a ON e.assignment_id = a.id
    JOIN students s ON e.student_id = s.id
    WHERE a.teacher_id = ?
    AND s.status = 'Active'";
$students_stmt = mysqli_prepare($conn, $students_query);
mysqli_stmt_bind_param($students_stmt, "i", $teacher_id);
mysqli_stmt_execute($students_stmt);
$students_result = mysqli_stmt_get_result($students_stmt);
$total_students = $students_result->fetch_assoc()['total_students'];
mysqli_stmt_close($students_stmt);

// 3. Count total attendance records
$attendance_query = "
    SELECT COUNT(*) as total_attendance
    FROM attendance
    WHERE teacher_id = ?";
$attendance_stmt = mysqli_prepare($conn, $attendance_query);
mysqli_stmt_bind_param($attendance_stmt, "i", $teacher_id);
mysqli_stmt_execute($attendance_stmt);
$attendance_result = mysqli_stmt_get_result($attendance_stmt);
$total_attendance = $attendance_result->fetch_assoc()['total_attendance'];
mysqli_stmt_close($attendance_stmt);

// 4. Calculate attendance rate (present students / total possible attendances)
$attendance_rate_query = "
    SELECT 
        ROUND(
            (SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 
            1
        ) as attendance_rate
    FROM attendance
    WHERE teacher_id = ?";
$rate_stmt = mysqli_prepare($conn, $attendance_rate_query);
mysqli_stmt_bind_param($rate_stmt, "i", $teacher_id);
mysqli_stmt_execute($rate_stmt);
$rate_result = mysqli_stmt_get_result($rate_stmt);
$attendance_rate = $rate_result->fetch_assoc()['attendance_rate'];
if ($attendance_rate === NULL) $attendance_rate = 0; // Handle case of no attendance records
mysqli_stmt_close($rate_stmt);

// 5. Count upcoming classes in the next 7 days
$upcoming_query = "
    SELECT COUNT(*) as upcoming_classes
    FROM assignments a
    WHERE a.teacher_id = ?
    AND JSON_OVERLAPS(
        a.preferred_day,
        JSON_ARRAY(
            DAYNAME(CURRENT_DATE),
            DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY)),
            DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY)),
            DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY)),
            DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 4 DAY)),
            DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY)),
            DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 6 DAY))
        )
    )";
$upcoming_stmt = mysqli_prepare($conn, $upcoming_query);
mysqli_stmt_bind_param($upcoming_stmt, "i", $teacher_id);
mysqli_stmt_execute($upcoming_stmt);
$upcoming_result = mysqli_stmt_get_result($upcoming_stmt);
$upcoming_classes = $upcoming_result->fetch_assoc()['upcoming_classes'];
mysqli_stmt_close($upcoming_stmt);

// If there are missed classes, show a notification
$show_missed_notification = ($missed_classes_count > 0);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teacher Dashboard - BSIT 2C AMS</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .stat-card {
            border-radius: 10px;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .stat-count {
            font-size: 2rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <h2>Welcome, <?php echo htmlspecialchars($teacher_name); ?>!</h2>
                <p>Teacher Dashboard</p>
                
                <?php if ($show_missed_notification): ?>
                <div class="alert alert-warning alert-dismissible fade show mb-4">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-exclamation-triangle-fill display-5"></i>
                        </div>
                        <div>
                            <h4 class="alert-heading">Attendance Needed!</h4>
                            <p class="mb-0">You have <strong><?= $missed_classes_count ?> <?= $missed_classes_count == 1 ? 'class' : 'classes' ?></strong> scheduled today that <?= $missed_classes_count == 1 ? 'needs' : 'need' ?> attendance to be recorded.</p>
                            <a href="attendance/pending.php" class="btn btn-dark mt-2">
                                <i class="bi bi-arrow-right-circle"></i> Manage Class Attendance
                            </a>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Statistics Cards Row -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary text-white stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Assigned Subjects</h6>
                                        <p class="stat-count mb-0"><?php echo $total_subjects; ?></p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-book"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-success text-white stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Students</h6>
                                        <p class="stat-count mb-0"><?php echo $total_students; ?></p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-people"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-info text-white stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Attendance</h6>
                                        <p class="stat-count mb-0"><?php echo $total_attendance; ?></p>
                                        <p class="mb-0">Rate: <?php echo $attendance_rate; ?>%</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-warning text-white stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Upcoming Classes</h6>
                                        <p class="stat-count mb-0"><?php echo $upcoming_classes; ?></p>
                                        <p class="mb-0">Next 7 days</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-calendar-week"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Manage Attendance</h5>
                                <p class="card-text">Record and view attendance for your classes.</p>
                                <a href="attendance/index.php" class="btn btn-primary">Go to Attendance</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">View Reports</h5>
                                <p class="card-text">Generate and view attendance reports.</p>
                                <a href="reports.php" class="btn btn-primary">View Reports</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Account Settings</h5>
                                <p class="card-text">Update your profile and settings.</p>
                                <a href="profile.php" class="btn btn-primary">Settings</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="logoutModalLabel"><i class="bi bi-box-arrow-right me-2"></i>Confirm Logout</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-exclamation-circle text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <p class="text-center">Are you sure you want to logout from the Teacher Portal?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1"></i>Cancel</button>
                    <a href="logout.php" class="btn btn-danger"><i class="bi bi-box-arrow-right me-1"></i>Yes, Logout</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html> 