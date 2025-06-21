<?php
require_once '../../config/database.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get input data
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Log the received data
        error_log("Status update request received: " . json_encode($data));
        
        // Validate input data
        if (!isset($data['id']) || !isset($data['status'])) {
            throw new Exception("Missing required fields: id and status");
        }
        
        $id = $data['id'];
        $status = $data['status'];
        
        // Validate status value
        $valid_statuses = ['Active', 'Inactive'];
        if (!in_array($status, $valid_statuses)) {
            throw new Exception("Invalid status value. Allowed values: Active, Inactive");
        }
        
        // Use prepared statements to prevent SQL injection
        $query = "UPDATE students SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("si", $status, $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        // Check if any rows were affected
        if ($stmt->affected_rows == 0) {
            throw new Exception("No record found with ID: $id or status is already set to '$status'");
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Status updated successfully to '$status'"
        ]);
        
    } catch (Exception $e) {
        error_log("Status update error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. This endpoint only accepts POST requests.'
    ]);
}
?> 