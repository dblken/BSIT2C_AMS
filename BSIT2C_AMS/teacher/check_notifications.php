<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (isset($_SESSION['teacher_id'])) {
    $teacher_id = $_SESSION['teacher_id'];
    
    $query = "SELECT COUNT(*) as count FROM notifications 
              WHERE teacher_id = '$teacher_id' AND is_read = FALSE";
    
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    
    echo json_encode([
        'success' => true,
        'new_notifications' => $row['count']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated'
    ]);
}
?> 