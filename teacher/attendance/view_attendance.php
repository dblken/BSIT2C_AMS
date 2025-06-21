<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../../login.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'];

// Check if subject_id and assignment_id are provided
if (!isset($_GET['subject_id']) || !isset($_GET['assignment_id'])) {
    header('Location: ../attendance.php');
    exit;
}

$subject_id = $_GET['subject_id'];
$assignment_id = $_GET['assignment_id'];

// Verify that the subject is assigned to this teacher
$verify_query = "SELECT a.id
                FROM assignments a
                WHERE a.id = ? AND a.teacher_id = ? AND a.subject_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("iii", $assignment_id, $teacher_id, $subject_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    header('Location: ../attendance.php');
    exit;
}

// Get subject details
$subject_query = "SELECT
    s.subject_code,
    s.subject_name,
    a.preferred_day,
    TIME_FORMAT(a.time_start, '%h:%i %p') as start_time,
    TIME_FORMAT(a.time_end, '%h:%i %p') as end_time,
    a.location
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.id = ? AND a.teacher_id = ?";

$stmt = $conn->prepare($subject_query);
$stmt->bind_param("ii", $assignment_id, $teacher_id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();

// Get all attendance dates for this subject
$attendance_query = "SELECT
    a.id,
    a.attendance_date,
    COUNT(ar.id) as student_count,
    SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
    SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count
    FROM attendance a
    LEFT JOIN attendance_records ar ON a.id = ar.attendance_id
    WHERE a.subject_id = ? AND a.teacher_id = ? AND a.assignment_id = ?
    GROUP BY a.id
    ORDER BY a.attendance_date DESC";

$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("iii", $subject_id, $teacher_id, $assignment_id);
$stmt->execute();
$attendance_records = $stmt->get_result();

// Get detailed attendance for a specific date if selected
$attendance_details = null;
$selected_date = null;

if (isset($_GET['attendance_id'])) {
    $attendance_id = $_GET['attendance_id'];

    // Get date of selected attendance
    $date_query = "SELECT attendance_date FROM attendance WHERE id = ?";
    $stmt = $conn->prepare($date_query);
    $stmt->bind_param("i", $attendance_id);
    $stmt->execute();
    $date_result = $stmt->get_result();
    if ($date_result->num_rows > 0) {
        $selected_date = $date_result->fetch_assoc()['attendance_date'];
    }

    // Get attendance details
    $details_query = "SELECT
        ar.id,
        s.student_id as student_number,
        CONCAT(s.last_name, ', ', s.first_name) as student_name,
        ar.status,
        ar.remarks
        FROM attendance_records ar
        JOIN students s ON ar.student_id = s.id
        JOIN attendance a ON ar.attendance_id = a.id
        WHERE a.id = ?
        ORDER BY s.last_name, s.first_name";

    $stmt = $conn->prepare($details_query);
    $stmt->bind_param("i", $attendance_id);
    $stmt->execute();
    $attendance_details = $stmt->get_result();
}

// Helper function to get badge class based on status
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'present':
            return 'bg-success';
        case 'late':
            return 'bg-warning text-dark';
        case 'absent':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Attendance - <?php echo htmlspecialchars($subject['subject_code']); ?></title>       
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        .view-header {
            background-color: #021f3f;
            color: white;
            padding: 1.5rem;
            border-radius: 8px 8px 0 0;
        }

        .view-container {
            background-color: white;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);       
            overflow: hidden;
        }

        .attendance-date-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            border-left: 4px solid #b3c6e6;
        }

        .attendance-date-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);     
        }

        .attendance-date-card.active {
            border-color: #021f3f;
            border-width: 2px;
            box-shadow: 0 0 0 3px rgba(2, 31, 63, 0.3);
        }

        .stat-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
        }
        
        .btn-primary {
            background-color: #021f3f;
            border-color: #021f3f;
        }
        
        .btn-primary:hover {
            background-color: #01152b;
            border-color: #01152b;
        }
        
        .btn-outline-primary {
            color: #021f3f;
            border-color: #021f3f;
        }
        
        .btn-outline-primary:hover {
            background-color: #021f3f;
            border-color: #021f3f;
            color: white;
        }
        
        .btn-outline-warning {
            color: #021f3f;
            border-color: #021f3f;
        }
        
        .btn-outline-warning:hover {
            background-color: #021f3f;
            border-color: #021f3f;
            color: white;
        }
        
        .btn-warning {
            background-color: #021f3f;
            border-color: #021f3f;
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #01152b;
            border-color: #01152b;
            color: white;
        }
        
        .card-header.bg-primary {
            background-color: #021f3f !important;
        }
        
        /* Bootstrap override for primary color */
        :root {
            --bs-primary: #021f3f;
            --bs-primary-rgb: 2, 31, 63;
        }
        
        .form-check-input:checked {
            background-color: #021f3f;
            border-color: #021f3f;
        }
        
        .form-control:focus {
            border-color: #021f3f;
            box-shadow: 0 0 0 0.25rem rgba(2, 31, 63, 0.25);
        }
        
        a {
            color: #021f3f;
        }
        
        a:hover {
            color: #01152b;
        }
        
        .page-link {
            color: #021f3f;
        }
        
        .page-item.active .page-link {
            background-color: #021f3f;
            border-color: #021f3f;
        }
        
        .text-primary {
            color: #021f3f !important;
        }
        
        .border-primary {
            border-color: #021f3f !important;
        }
        
        .bg-primary {
            background-color: #021f3f !important;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="mb-4">
            <a href="../attendance.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Attendance Dashboard
            </a>
        </div>

        <div class="view-header mb-0">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2 class="mb-1"><?php echo htmlspecialchars($subject['subject_code']); ?></h2>  
                    <p class="mb-0"><?php echo htmlspecialchars($subject['subject_name']); ?></p>    
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="d-flex flex-column align-items-md-end">
                        <div class="mb-1">
                            <i class="bi bi-calendar-event"></i>
                            <?php echo formatDays($subject['preferred_day']); ?>
                        </div>
                        <div class="mb-1">
                            <i class="bi bi-clock"></i>
                            <?php echo $subject['start_time']; ?> - <?php echo $subject['end_time']; ?>
                        </div>
                        <div>
                            <i class="bi bi-geo-alt"></i>
                            <?php echo htmlspecialchars($subject['location']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="view-container p-4">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Attendance Dates</h5>
                            <span class="badge bg-light text-dark"><?php echo $attendance_records->num_rows; ?> Records</span>
                        </div>
                        <div class="card-body attendance-dates-list" style="max-height: 600px; overflow-y: auto;">
                            <?php if ($attendance_records->num_rows === 0): ?>
                                <div class="text-center text-muted my-5">
                                    <i class="bi bi-calendar-x" style="font-size: 2rem;"></i>        
                                    <p class="mt-2">No attendance records found</p>
                                </div>
                            <?php else: ?>
                                <?php while ($record = $attendance_records->fetch_assoc()): ?>       
                                    <div class="card mb-2 attendance-date-card <?php echo (isset($_GET['attendance_id']) && $_GET['attendance_id'] == $record['id']) ? 'active' : ''; ?>"
                                         onclick="location.href='view_attendance.php?subject_id=<?php echo $subject_id; ?>&assignment_id=<?php echo $assignment_id; ?>&attendance_id=<?php echo $record['id']; ?>'">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo date('F d, Y', strtotime($record['attendance_date'])); ?></h6>
                                            <p class="card-text text-muted"><?php echo date('l', strtotime($record['attendance_date'])); ?></p>

                                            <div class="d-flex gap-2 flex-wrap mt-2">
                                                <span class="stat-badge bg-success">
                                                    Present: <?php echo $record['present_count']; ?> 
                                                </span>
                                                <span class="stat-badge bg-warning text-dark">       
                                                    Late: <?php echo $record['late_count']; ?>       
                                                </span>
                                                <span class="stat-badge bg-danger">
                                                    Absent: <?php echo $record['absent_count']; ?>   
                                                </span>
                                            </div>

                                            <div class="mt-2 text-end">
                                                <a href="take_attendance.php?subject_id=<?php echo $subject_id; ?>&assignment_id=<?php echo $assignment_id; ?>&date=<?php echo $record['attendance_date']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <?php if ($selected_date && $attendance_details): ?>
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-1"><?php echo htmlspecialchars($subject['subject_code']); ?> - Attendance Details</h5>
                                <p class="mb-0">Date: <?php echo date('F d, Y (l)', strtotime($selected_date)); ?></p>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Student ID</th>
                                                <th>Name</th>
                                                <th>Status</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($student = $attendance_details->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo getStatusBadgeClass($student['status']); ?>">
                                                            <?php echo ucfirst(htmlspecialchars($student['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($student['remarks']); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-4">
                                    <a href="take_attendance.php?subject_id=<?php echo $subject_id; ?>&assignment_id=<?php echo $assignment_id; ?>&date=<?php echo $selected_date; ?>" class="btn btn-primary">
                                        <i class="bi bi-pencil-square"></i> Edit Attendance
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card h-100 d-flex justify-content-center align-items-center">    
                            <div class="text-center text-muted my-5">
                                <i class="bi bi-calendar2-check" style="font-size: 3rem;"></i>
                                <h4 class="mt-4">Select a date from the list</h4>
                                <p>to view attendance details</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 