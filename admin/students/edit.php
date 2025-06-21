<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $query = "SELECT s.*, u.username 
              FROM students s 
              LEFT JOIN users u ON s.user_id = u.id 
              WHERE s.id = '$id'";
    $result = mysqli_query($conn, $query);
    $student = mysqli_fetch_assoc($result);
    echo json_encode(['success' => true, 'student' => $student]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // This will be implemented when needed for updating students
    // Similar to the teacher update functionality
    echo json_encode(['success' => false, 'message' => 'Update functionality not implemented yet']);
}
?> 