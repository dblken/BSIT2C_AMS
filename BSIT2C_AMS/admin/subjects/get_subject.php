<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if (isset($_GET['id'])) {
    try {
        $id = mysqli_real_escape_string($conn, $_GET['id']);
        
        $query = "SELECT * FROM subjects WHERE id = '$id'";
        $result = mysqli_query($conn, $query);
        
        if ($subject = mysqli_fetch_assoc($result)) {
            echo json_encode(['success' => true, 'subject' => $subject]);
        } else {
            throw new Exception('Subject not found');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?> 