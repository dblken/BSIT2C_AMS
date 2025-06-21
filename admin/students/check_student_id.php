<?php
session_start();
require_once '../../config/database.php';

// For debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
    
    // Check if student_id already exists
    $check_student_id = "SELECT id FROM students WHERE student_id = ?";
    $stmt = $conn->prepare($check_student_id);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode([
        'exists' => $result->num_rows > 0
    ]);
} else {
    echo json_encode([
        'error' => 'Invalid request',
        'exists' => false
    ]);
}
?> 