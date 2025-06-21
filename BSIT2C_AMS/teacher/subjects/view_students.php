<?php
session_start();
require_once '../../config/database.php';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../login.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'];

// Check if subject_id and assignment_id are provided
if (!isset($_GET['subject_id']) || !isset($_GET['assignment_id'])) {
    header('Location: index.php');
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
    header('Location: index.php');
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

// Get all students enrolled in this subject
$students_query = "SELECT 
    s.student_id,
    s.first_name,
    s.middle_name,
    s.last_name,
    s.email,
    s.phone_number,
    e.enrollment_date
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    JOIN timetable tt ON e.schedule_id = tt.id
    WHERE tt.subject_id = ?
    ORDER BY s.last_name, s.first_name";
    
$stmt = $conn->prepare($students_query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$students = $stmt->get_result();

// Convert preferred_day to day name
function getDayName($day_code) {
    $days = [
        'M' => 'Monday',
        'T' => 'Tuesday',
        'W' => 'Wednesday',
        'TH' => 'Thursday',
        'F' => 'Friday',
        'SAT' => 'Saturday',
        'SUN' => 'Sunday',
        '1' => 'Monday',
        '2' => 'Tuesday',
        '3' => 'Wednesday',
        '4' => 'Thursday',
        '5' => 'Friday',
        '6' => 'Saturday',
        '7' => 'Sunday'
    ];
    
    return $days[$day_code] ?? $day_code;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrolled Students - Teacher Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
</head>
<body>
    <?php include_once '../includes/header.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">My Subjects</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Enrolled Students</li>
                </ol>
            </nav>
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="bi bi-printer me-2"></i>Print
            </button>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><?= htmlspecialchars($subject['subject_code']) ?> - <?= htmlspecialchars($subject['subject_name']) ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>Schedule:</strong> <?= getDayName($subject['preferred_day']) ?>, <?= $subject['start_time'] ?> - <?= $subject['end_time'] ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Location:</strong> <?= htmlspecialchars($subject['location'] ?: 'TBA') ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Total Students:</strong> <?= $students->num_rows ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($students->num_rows === 0): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i> No students are enrolled in this subject yet.
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Enrolled Students</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Enrollment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php while ($student = $students->fetch_assoc()): ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td><?= htmlspecialchars($student['student_id']) ?></td>
                                <td>
                                    <?= htmlspecialchars($student['last_name']) ?>,
                                    <?= htmlspecialchars($student['first_name']) ?>
                                    <?= $student['middle_name'] ? htmlspecialchars($student['middle_name'][0]) . '.' : '' ?>
                                </td>
                                <td><?= htmlspecialchars($student['email']) ?></td>
                                <td><?= htmlspecialchars($student['phone_number'] ?: 'N/A') ?></td>
                                <td><?= date('M d, Y', strtotime($student['enrollment_date'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
    
    <!-- Print styles - Only applied when printing -->
    <style type="text/css" media="print">
        @media print {
            .breadcrumb, .btn-outline-primary, header, .nav, footer {
                display: none !important;
            }
            .container {
                width: 100%;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            .card {
                border: none;
            }
            .card-header {
                background-color: #f8f9fa !important;
                color: #000 !important;
            }
            body {
                padding: 20px;
                font-size: 12pt;
            }
            h5 {
                font-size: 14pt;
            }
        }
    </style>
</body>
</html> 