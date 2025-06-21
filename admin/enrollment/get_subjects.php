<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Set content type to JSON
header('Content-Type: application/json');

// Get student ID
if (!isset($_GET['student_id']) || empty($_GET['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit();
}

$student_id = intval($_GET['student_id']);

// Check if student exists
$student_check = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE id = ? AND status = 'Active'");
$student_check->bind_param("i", $student_id);
$student_check->execute();
$student_result = $student_check->get_result();

if ($student_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Student not found or inactive']);
    exit();
}

$student = $student_result->fetch_assoc();

// Get all available assignments with enrollment status
$query = "
    SELECT 
        a.id AS assignment_id,
        a.subject_id,
        a.teacher_id,
        a.preferred_day,
        a.time_start,
        a.time_end,
        a.location,
        s.subject_code,
        s.subject_name,
        CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
        e.id AS enrollment_id,
        CASE WHEN e.id IS NOT NULL THEN 1 ELSE 0 END AS is_enrolled
    FROM 
        assignments a
    JOIN 
        subjects s ON a.subject_id = s.id
    JOIN 
        teachers t ON a.teacher_id = t.id
    LEFT JOIN 
        enrollments e ON a.id = e.assignment_id AND e.student_id = ?
    ORDER BY 
        s.subject_name ASC, 
        a.id ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$assignments = [];

while ($row = $result->fetch_assoc()) {
    // Format the date/time values
    $row['formatted_start_time'] = date('h:i A', strtotime($row['time_start']));
    $row['formatted_end_time'] = date('h:i A', strtotime($row['time_end']));
    
    // Handle preferred_day formatting
    $row['raw_preferred_day'] = $row['preferred_day'];
    $row['preferred_day'] = formatDays($row['preferred_day']);
    
    $assignments[] = $row;
}

// Return the result
echo json_encode([
    'success' => true,
    'student' => [
        'id' => $student['id'],
        'name' => $student['first_name'] . ' ' . $student['last_name']
    ],
    'assignments' => $assignments
]);
?> 