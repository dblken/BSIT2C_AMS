<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if (isset($_GET['id'])) {
    try {
        $id = mysqli_real_escape_string($conn, $_GET['id']);
        
        // Get subject details
        $query = "SELECT s.*, 
                 (SELECT COUNT(*) FROM enrollments e WHERE e.subject_id = s.id) as enrolled_count 
                 FROM subjects s 
                 WHERE s.id = '$id'";
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