<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = mysqli_real_escape_string($conn, $data['id']);

        // Start transaction
        $conn->begin_transaction();
        
        // First, get the user_id from the student record
        $query = "SELECT user_id FROM students WHERE id = '$id'";
        $result = mysqli_query($conn, $query);
        $user_id = null;
        
        if ($row = mysqli_fetch_assoc($result)) {
            $user_id = $row['user_id'];
        }
        
        // Delete student record
        $query = "DELETE FROM students WHERE id = '$id'";
        if (!mysqli_query($conn, $query)) {
            throw new Exception(mysqli_error($conn));
        }
        
        // If we have a user_id, delete the user record too
        if ($user_id) {
            $query = "DELETE FROM users WHERE id = '$user_id'";
            if (!mysqli_query($conn, $query)) {
                throw new Exception(mysqli_error($conn));
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn && !$conn->connect_error) {
            $conn->rollback();
        }
        
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?> 