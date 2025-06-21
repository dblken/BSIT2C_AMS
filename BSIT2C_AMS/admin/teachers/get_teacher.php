<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['teacher_id'])) {
    $teacher_id = mysqli_real_escape_string($conn, $_GET['teacher_id']);
    
    $query = "SELECT * FROM teachers WHERE id = '$teacher_id'";
    $result = mysqli_query($conn, $query);
    
    if ($teacher = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'teacher' => $teacher]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    }
}
?>