<?php
session_start();
require_once '../../config/database.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($data['id'])) {
    $teacher_id = $data['id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First, get the user_id associated with this teacher
        $get_user_id = "SELECT user_id FROM teachers WHERE id = ?";
        $stmt = $conn->prepare($get_user_id);
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Teacher not found');
        }
        
        $user_id = $result->fetch_assoc()['user_id'];
        
        // Delete teacher record
        $delete_teacher = "DELETE FROM teachers WHERE id = ?";
        $stmt = $conn->prepare($delete_teacher);
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        
        // Delete associated user account
        $delete_user = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($delete_user);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Teacher deleted successfully'
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting teacher: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}

$conn->close();
?>