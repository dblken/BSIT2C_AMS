<?php
session_start();
require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['teacher_id'])) {
        throw new Exception('Teacher ID is required');
    }
    
    $id = $data['teacher_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    // First, update any subjects that have this teacher assigned
    $update_subjects = "UPDATE subjects SET id = NULL WHERE id = ?";
    $stmt = $conn->prepare($update_subjects);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    // Then delete from timetable if it exists
    $delete_timetable = "DELETE FROM timetable WHERE id = ?";
    $stmt = $conn->prepare($delete_timetable);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    // Finally delete the teacher
    $delete_teacher = "DELETE FROM teachers WHERE id = ?";
    $stmt = $conn->prepare($delete_teacher);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Teacher deleted successfully'
        ]);
    } else {
        throw new Exception("Failed to delete teacher");
    }
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting teacher: ' . $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>