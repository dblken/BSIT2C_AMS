<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session_protection.php'; // Include the session protection file

// Use the verify_session function to check teacher session
verify_session('teacher');

// Get teacher details
$sql = "SELECT t.*, u.username, u.last_login 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['teacher_id']);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();

// Get teacher's subjects - Count distinct subjects using COUNT instead of fetching all rows
$subjects_query = "SELECT COUNT(DISTINCT s.id) as subject_count 
        FROM subjects s 
        JOIN assignments a ON s.id = a.subject_id 
        WHERE a.teacher_id = ?";
$subjects_stmt = $conn->prepare($subjects_query);
$subjects_stmt->bind_param("i", $_SESSION['teacher_id']);
$subjects_stmt->execute();
$subjects_result = $subjects_stmt->get_result();
$subject_count = $subjects_result->fetch_assoc()['subject_count'];

// Keep the original query to fetch subject details if needed elsewhere
$subjects_details_query = "SELECT s.* FROM subjects s 
        JOIN assignments a ON s.id = a.subject_id 
        WHERE a.teacher_id = ?";
$subjects_details_stmt = $conn->prepare($subjects_details_query);
$subjects_details_stmt->bind_param("i", $_SESSION['teacher_id']);
$subjects_details_stmt->execute();
$subjects = $subjects_details_stmt->get_result();

// Get total students count based on enrollments and timetable
$total_students_query = "
    SELECT SUM(student_count) as total_students
    FROM (
        SELECT 
            s.id as subject_id,
            (SELECT COUNT(*) FROM enrollments e 
             JOIN timetable t ON e.schedule_id = t.id 
             WHERE t.subject_id = s.id) as student_count
        FROM subjects s
        JOIN assignments a ON s.id = a.subject_id
        WHERE a.teacher_id = ?
    ) as subject_counts";
$stmt = $conn->prepare($total_students_query);
$stmt->bind_param("i", $_SESSION['teacher_id']);
$stmt->execute();
$total_students_result = $stmt->get_result();
$total_students = $total_students_result->fetch_assoc()['total_students'];

// Get total attendance records with a more comprehensive query
$total_attendance_query = "
    SELECT 
        COUNT(DISTINCT a.id) as total_attendance
    FROM 
        attendance a
    WHERE 
        a.teacher_id = ?";
$stmt = $conn->prepare($total_attendance_query);
$stmt->bind_param("i", $_SESSION['teacher_id']);
$stmt->execute();
$total_attendance_result = $stmt->get_result();
$total_attendance = $total_attendance_result->fetch_assoc()['total_attendance'];

// Get attendance rate (percentage of present and late students)
$attendance_rate_query = "
    SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        COUNT(ar.id) as total_students
    FROM 
        attendance_records ar
    JOIN 
        attendance a ON ar.attendance_id = a.id
    WHERE 
        a.teacher_id = ?
    AND a.is_pending = 0
    AND MONTH(a.attendance_date) = MONTH(CURDATE()) 
    AND YEAR(a.attendance_date) = YEAR(CURDATE())"; // Only show current month's attendance rate

$rate_stmt = $conn->prepare($attendance_rate_query);
$rate_stmt->bind_param("i", $_SESSION['teacher_id']);
$rate_stmt->execute();
$rate_result = $rate_stmt->get_result();
$attendance_stats = $rate_result->fetch_assoc();

// Calculate attendance rate using the same formula as in attendance.php
function calculatePercentage($value, $total) {
    if ($total == 0) return 0;
    return round(($value / $total) * 100);
}

$attendance_rate = calculatePercentage(
    ($attendance_stats['present_count'] + $attendance_stats['late_count']), 
    $attendance_stats['total_students']
);

// Calculate additional statistics
$total_records = $attendance_stats['total_records'] ?? 0;
$present_count = $attendance_stats['present_count'] ?? 0;
$late_count = $attendance_stats['late_count'] ?? 0;
$absent_count = $attendance_stats['absent_count'] ?? 0;

// Query for recent attendance records
$recent_attendance_query = "
    SELECT 
        a.id,
        a.subject_id,
        a.attendance_date,
        s.subject_code,
        s.subject_name,
        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.attendance_id = a.id AND ar.status = 'present') as present_count,
        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.attendance_id = a.id AND ar.status = 'late') as late_count,
        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.attendance_id = a.id AND ar.status = 'absent') as absent_count,
        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.attendance_id = a.id) as total_students
    FROM 
        attendance a
    JOIN 
        subjects s ON a.subject_id = s.id
    WHERE 
        a.teacher_id = ?
    ORDER BY 
        a.attendance_date DESC, a.id DESC
    LIMIT 5";

$stmt = $conn->prepare($recent_attendance_query);
$stmt->bind_param("i", $_SESSION['teacher_id']);
$stmt->execute();
$recent_attendance = $stmt->get_result();

// Count upcoming classes in the next 7 days
$upcoming_query = "
    SELECT COUNT(DISTINCT a.id) as upcoming_classes
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
$upcoming_stmt = $conn->prepare($upcoming_query);
$upcoming_stmt->bind_param("i", $_SESSION['teacher_id']);
$upcoming_stmt->execute();
$upcoming_result = $upcoming_stmt->get_result();
$upcoming_classes = $upcoming_result->fetch_assoc()['upcoming_classes'];
$upcoming_stmt->close();

// Get today's scheduled subjects
$today_day = date('l'); // Get current day name
$current_time = date('H:i:s');

$today_subjects_query = "
    SELECT 
        a.id as assignment_id,
        s.id as subject_id,
        s.subject_code,
        s.subject_name,
        a.time_start,
        a.time_end,
        a.location,
        (SELECT COUNT(*) FROM attendance att 
         WHERE att.teacher_id = a.teacher_id 
         AND att.subject_id = s.id 
         AND att.assignment_id = a.id 
         AND att.attendance_date = CURDATE()) as attendance_taken
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.teacher_id = ?
    AND JSON_CONTAINS(a.preferred_day, ?)
    ORDER BY a.time_start ASC";

$today_subjects_stmt = $conn->prepare($today_subjects_query);
$today_day_json = json_encode($today_day);
$today_subjects_stmt->bind_param("is", $_SESSION['teacher_id'], $today_day_json);
$today_subjects_stmt->execute();
$today_subjects = $today_subjects_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        /* Custom styles for teacher dashboard */
        :root {
            --primary-color: #021F3F;
            --secondary-color: #C8A77E;
            --primary-dark: #011327;
            --secondary-light: #d8b78e;
            --text-dark: #1f2937;
            --text-light: #ffffff;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: var(--text-dark);
        }
        
        /* Header styling */
        header.dashboard-header {
            background-color: var(--primary-color);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
        }
        
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .navbar-dark .navbar-nav .nav-link:hover,
        .navbar-dark .navbar-nav .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        /* Main content area */
        .container.main-content {
            padding-top: 2rem;
            padding-bottom: 2rem;
        }
        
        .page-title {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.75rem;
        }
        
        .page-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 4px;
            background-color: var(--secondary-color);
            border-radius: 2px;
        }
        
        /* Dashboard cards */
        .stat-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            height: 100%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .stat-card .icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--secondary-color);
            color: var(--primary-color);
            border-radius: 50%;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-card .icon.subjects {
            background-color: rgba(2, 31, 63, 0.1);
            color: var(--primary-color);
        }
        
        .stat-card .icon.students {
            background-color: rgba(200, 167, 126, 0.2);
            color: var(--primary-color);
        }
        
        .stat-card .icon.schedule {
            background-color: rgba(5, 150, 105, 0.1);
            color: var(--success-color);
        }
        
        .stat-card .icon.attendance {
            background-color: rgba(13, 148, 136, 0.1);
            color: #0d9488;
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        .stat-card .label {
            font-size: 0.875rem;
            color: var(--text-light);
            font-weight: 500;
        }
        
        /* Recent activity section */
        .recent-activity {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            margin-top: 2rem;
        }
        
        .recent-activity .card-header {
            background-color: var(--primary-color);
            color: white;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }
        
        .activity-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(200, 167, 126, 0.3);
            display: flex;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
            background-color: var(--primary-color);
            color: white;
        }
        
        .activity-details {
            flex-grow: 1;
        }
        
        .activity-details .title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--primary-color);
        }
        
        .activity-details .time {
            font-size: 0.75rem;
            color: var(--text-light);
        }
        
        .activity-stats {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .stat {
            display: flex;
            align-items: center;
        }
        
        .stat-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .stat-dot.present {
            background-color: var(--success-color);
        }
        
        .stat-dot.late {
            background-color: var(--secondary-color);
        }
        
        .stat-dot.absent {
            background-color: var(--danger-color);
        }
        
        /* Quick access buttons */
        .quick-access {
            margin-top: 2rem;
        }
        
        .quick-action-btn {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            transition: all 0.3s ease;
            color: var(--text-dark);
            text-decoration: none;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            color: var(--primary-color);
        }
        
        .quick-action-btn i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .quick-action-btn span {
            font-weight: 600;
        }
        
        /* Footer */
        footer {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem 0;
            margin-top: 3rem;
        }
        
        footer p {
            margin-bottom: 0;
            opacity: 0.9;
        }
        
        /* Button overrides */
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-outline-secondary {
            color: var(--primary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--secondary-color);
            color: var(--primary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        /* Update button specific styles */
        .btn-outline-primary.btn-sm:hover {
            background-color: #021f3f;
            border-color: #021f3f;
            color: white;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 1rem;
            }
            
            .quick-action-btn {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include_once 'includes/header.php'; ?>

    <div class="container main-content">
        <h1 class="page-title mb-4">Teacher Dashboard</h1>
        
        <!-- Dashboard Stats -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="icon subjects">
                        <i class="bi bi-book-fill"></i>
                    </div>
                    <div class="number"><?= $subject_count ?></div>
                    <div class="label">Assigned Subjects</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="icon students">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="number"><?= $total_students ?></div>
                    <div class="label">Total Students</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="icon attendance">
                        <i class="bi bi-clipboard-check-fill"></i>
                    </div>
                    <div class="number"><?= $attendance_rate ?>%</div>
                    <div class="label">Attendance Rate</div>
                </div>
            </div>
        </div>
        
        <!-- Quick Access Buttons -->
        <h4 class="mb-3">Quick Access</h4>
        <div class="row quick-access">
            <div class="col-6 col-md-3 mb-4">
                <a href="subjects/index.php" class="quick-action-btn">
                    <i class="bi bi-book"></i>
                    <span>My Subjects</span>
                </a>
            </div>
            
            <div class="col-6 col-md-3 mb-4">
                <a href="schedule.php" class="quick-action-btn">
                    <i class="bi bi-calendar-check"></i>
                    <span>Schedule</span>
                </a>
            </div>
            
            <div class="col-6 col-md-3 mb-4">
                <a href="attendance.php" class="quick-action-btn">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Attendance</span>
                </a>
            </div>
            
            <div class="col-6 col-md-3 mb-4">
                <a href="profile.php" class="quick-action-btn">
                    <i class="bi bi-person-circle"></i>
                    <span>Profile</span>
                </a>
            </div>
        </div>
        
        <!-- Today's Schedule Section -->
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center" style="background-color: var(--primary-color); color: white;">
                    <h6 class="m-0 font-weight-bold">Today's Schedule</h6>
                    <span class="badge bg-light text-dark"><?php echo date('l, F j, Y'); ?></span>
                </div>
                <div class="card-body">
                    <?php if ($today_subjects->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Time</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($subject = $today_subjects->fetch_assoc()): 
                                        $start_time = strtotime($subject['time_start']);
                                        $end_time = strtotime($subject['time_end']);
                                        $current = strtotime($current_time);
                                        
                                        // Determine class status
                                        $status = '';
                                        $status_class = '';
                                        
                                        if ($subject['attendance_taken']) {
                                            $status = 'Attendance Taken';
                                            $status_class = 'bg-success';
                                        } elseif ($current > $end_time) {
                                            $status = 'Class Ended';
                                            $status_class = 'bg-secondary';
                                        } elseif ($current >= $start_time && $current <= $end_time) {
                                            $status = 'Ongoing';
                                            $status_class = 'bg-warning text-dark';
                                        } else {
                                            $status = 'Upcoming';
                                            $status_class = 'bg-info';
                                        }
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($subject['subject_name']); ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                echo date('h:i A', strtotime($subject['time_start'])) . ' - ' . 
                                                     date('h:i A', strtotime($subject['time_end'])); 
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($subject['location']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <?php echo $status; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!$subject['attendance_taken'] && ($current <= $end_time)): ?>
                                                    <a href="attendance/take_attendance.php?subject_id=<?php echo $subject['subject_id']; ?>&assignment_id=<?php echo $subject['assignment_id']; ?>&date=<?php echo date('Y-m-d'); ?>" 
                                                       class="btn btn-primary btn-sm">
                                                        <i class="bi bi-clipboard-check"></i> Take Attendance
                                                    </a>
                                                <?php elseif ($subject['attendance_taken']): ?>
                                                    <a href="attendance/take_attendance.php?subject_id=<?php echo $subject['subject_id']; ?>&assignment_id=<?php echo $subject['assignment_id']; ?>&date=<?php echo date('Y-m-d'); ?>" 
                                                       class="btn btn-outline-primary btn-sm">
                                                        <i class="bi bi-pencil"></i> Update
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2">No classes scheduled for today.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="recent-activity">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Activity</h5>
            </div>
            <div class="card-body p-0">
                <?php if ($recent_attendance->num_rows === 0): ?>
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-clock-history"></i>
                        <p class="mb-0">No recent activity</p>
                    </div>
                <?php else: ?>
                    <?php while ($activity = $recent_attendance->fetch_assoc()): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="bi bi-clipboard-check"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?= htmlspecialchars($activity['subject_code']) ?> - 
                                    <?= htmlspecialchars($activity['subject_name']) ?>
                                </div>
                                <div class="activity-meta">
                                    <span class="activity-date">
                                        <i class="bi bi-calendar"></i>
                                        <?= date('M d, Y', strtotime($activity['attendance_date'])) ?>
                                    </span>
                                    <span class="activity-time">
                                        <i class="bi bi-clock"></i>
                                        <?= date('h:i A', strtotime($activity['attendance_date'])) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
    <script>
        // Current time display
        function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            const timeString = `${hours}:${minutes}:${seconds} ${ampm}`;
            
            document.getElementById('currentTime').textContent = timeString;
        }
        
        // Update clock every second
        updateClock();
        setInterval(updateClock, 1000);
        
        // Auto refresh the page every 5 minutes to keep class status updated
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>