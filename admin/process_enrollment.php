<?php
require_once '../config/database.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$student_id = $_POST['student_id'];
$schedule_id = $_POST['schedule_id'];

// Validate inputs
if (empty($student_id) || empty($schedule_id)) {
    echo json_encode(['success' => false, 'message' => 'Please select both student and schedule']);
    exit;
}

// Check if student is already enrolled in this schedule
$check_query = "SELECT * FROM enrollments 
                WHERE student_id = ? AND schedule_id = ? AND status = 'active'";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $student_id, $schedule_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Student is already enrolled in this schedule']);
    exit;
}

// Check for schedule conflicts
$conflict_query = "SELECT e.*, s.start_time, s.end_time, s.day
                  FROM enrollments e
                  JOIN schedules s ON e.schedule_id = s.schedule_id
                  WHERE e.student_id = ? AND e.status = 'active'";
$stmt = $conn->prepare($conflict_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$existing_schedules = $stmt->get_result();

// Get the new schedule details
$new_schedule_query = "SELECT * FROM schedules WHERE schedule_id = ?";
$stmt = $conn->prepare($new_schedule_query);
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$new_schedule = $stmt->get_result()->fetch_assoc();

while ($existing = $existing_schedules->fetch_assoc()) {
    if ($existing['day'] == $new_schedule['day']) {
        $existing_start = strtotime($existing['start_time']);
        $existing_end = strtotime($existing['end_time']);
        $new_start = strtotime($new_schedule['start_time']);
        $new_end = strtotime($new_schedule['end_time']);

        if (($new_start >= $existing_start && $new_start < $existing_end) ||
            ($new_end > $existing_start && $new_end <= $existing_end) ||
            ($new_start <= $existing_start && $new_end >= $existing_end)) {
            echo json_encode(['success' => false, 'message' => 'Schedule conflicts with existing enrollment']);
            exit;
        }
    }
}

// Process the enrollment
$query = "INSERT INTO enrollments (student_id, schedule_id) VALUES (?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $student_id, $schedule_id);

if ($stmt->execute()) {
    // Get enrollment details for the success message
    $details_query = "SELECT s.first_name as student_fname, s.last_name as student_lname,
                      sub.subject_name, t.first_name as teacher_fname, t.last_name as teacher_lname
                      FROM students s
                      JOIN enrollments e ON s.student_id = e.student_id
                      JOIN schedules sch ON e.schedule_id = sch.schedule_id
                      JOIN subjects sub ON sch.subject_id = sub.subject_id
                      JOIN teachers t ON sch.teacher_id = t.teacher_id
                      WHERE e.enrollment_id = ?";
    $stmt = $conn->prepare($details_query);
    $enrollment_id = $stmt->insert_id;
    $stmt->bind_param("i", $enrollment_id);
    $stmt->execute();
    $details = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        'success' => true,
        'message' => "Successfully enrolled {$details['student_fname']} {$details['student_lname']} in {$details['subject_name']} with {$details['teacher_fname']} {$details['teacher_lname']}"
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
} 