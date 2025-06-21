<?php
session_start();
require_once '../../config/database.php';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../login.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'];

// Get teacher information
$teacher_query = "SELECT first_name, last_name FROM teachers WHERE id = ?";
$stmt = $conn->prepare($teacher_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();

// Get assigned subjects
$subjects_query = "SELECT 
    a.id as assignment_id,
    s.id as subject_id,
    s.subject_code,
    s.subject_name,
    a.preferred_day,
    TIME_FORMAT(a.time_start, '%h:%i %p') as start_time,
    TIME_FORMAT(a.time_end, '%h:%i %p') as end_time,
    a.location,
    (SELECT COUNT(*) FROM enrollments e 
     JOIN timetable tt ON e.schedule_id = tt.id 
     WHERE tt.subject_id = s.id) as student_count
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.teacher_id = ?
    ORDER BY a.preferred_day, a.time_start";
    
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$subjects = $stmt->get_result();

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
    <title>My Subjects - Teacher Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
</head>
<body>
    <?php include_once '../includes/header.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>My Assigned Subjects</h2>
        </div>

        <?php if ($subjects->num_rows === 0): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i> You don't have any assigned subjects yet.
        </div>
        <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php while ($subject = $subjects->fetch_assoc()): ?>
            <div class="col">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><?= htmlspecialchars($subject['subject_code']) ?></h5>
                    </div>
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($subject['subject_name']) ?></h6>
                        <p class="card-text">
                            <strong>Schedule:</strong> <?= getDayName($subject['preferred_day']) ?>, <?= $subject['start_time'] ?> - <?= $subject['end_time'] ?><br>
                            <strong>Location:</strong> <?= htmlspecialchars($subject['location'] ?: 'TBA') ?><br>
                            <strong>Students Enrolled:</strong> <?= $subject['student_count'] ?>
                        </p>
                    </div>
                    <div class="card-footer text-center">
                        <a href="view_students.php?subject_id=<?= $subject['subject_id'] ?>&assignment_id=<?= $subject['assignment_id'] ?>" class="btn btn-primary">
                            <i class="bi bi-people-fill me-2"></i>View Enrolled Students
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
</body>
</html> 