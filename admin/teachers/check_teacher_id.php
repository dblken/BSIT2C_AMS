<?php
session_start();
require_once '../../config/database.php';

// For debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacher_id'])) {
    $teacher_id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
    
    // Check if teacher_id already exists
    $check_teacher_id = "SELECT id FROM teachers WHERE teacher_id = ?";
    $stmt = $conn->prepare($check_teacher_id);
    $stmt->bind_param("s", $teacher_id);
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