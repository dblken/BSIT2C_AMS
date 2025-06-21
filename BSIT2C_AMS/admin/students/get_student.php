<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    try {
        $id = mysqli_real_escape_string($conn, $_GET['id']);
        
        $query = "SELECT * FROM students WHERE id = '$id'";
        $result = mysqli_query($conn, $query);
        
        if ($student = mysqli_fetch_assoc($result)) {
            echo json_encode(['success' => true, 'student' => $student]);
        } else {
            throw new Exception('Student not found');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?> 