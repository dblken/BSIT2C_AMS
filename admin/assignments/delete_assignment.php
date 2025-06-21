<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['id'])) {
            throw new Exception('Assignment ID is required.');
        }
        
        $id = (int)$_POST['id'];
        
        // Delete timetable entries first
        $conn->query("DELETE FROM timetable WHERE assignment_id = $id");
        
        // Then delete the assignment
        if (!$conn->query("DELETE FROM assignments WHERE id = $id")) {
            throw new Exception('Failed to delete assignment: ' . $conn->error);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Assignment deleted successfully.'
        ]);
    } catch (Exception $e) {
        error_log('Delete assignment error: ' . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}
?> 