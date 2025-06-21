<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enrollment_id']) && isset($_POST['status'])) {
    $enrollment_id = $_POST['enrollment_id'];
    $status = $_POST['status'];
    
    // Validate status value
    if (!in_array($status, ['Enrolled', 'Dropped', 'Completed', 'Pending'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        exit();
    }
    
    try {
        // Update the enrollment status
        $query = "UPDATE enrollments SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $status, $enrollment_id);
        $success = $stmt->execute();
        
        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Enrollment status updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update enrollment status: ' . $conn->error
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error updating enrollment status: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters (enrollment_id, status)'
    ]);
}
?> 