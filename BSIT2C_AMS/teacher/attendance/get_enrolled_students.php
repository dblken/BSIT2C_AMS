<?php
require_once '../../config/database.php';

$schedule_id = $_GET['schedule_id'];
$date = $_GET['date'];

$query = "SELECT e.enrollment_id, s.first_name, s.last_name, 
          a.status, a.remarks
          FROM enrollments e
          JOIN students s ON e.student_id = s.student_id
          LEFT JOIN attendance a ON e.enrollment_id = a.enrollment_id 
          AND a.date = ?
          WHERE e.schedule_id = ? AND e.status = 'active'
          ORDER BY s.last_name, s.first_name";

$stmt = $conn->prepare($query);
$stmt->bind_param("si", $date, $schedule_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

header('Content-Type: application/json');
echo json_encode($students); 