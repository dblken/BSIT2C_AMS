<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    try {
        $id = mysqli_real_escape_string($conn, $_GET['id']);
        
        $query = "SELECT t.*, s.subject_code, s.subject_name 
                  FROM timetable t
                  JOIN subjects s ON t.subject_id = s.id
                  WHERE t.id = '$id'";
        $result = mysqli_query($conn, $query);
        
        if ($assignment = mysqli_fetch_assoc($result)) {
            echo json_encode(['success' => true, 'assignment' => $assignment]);
        } else {
            throw new Exception('Assignment not found');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?> 