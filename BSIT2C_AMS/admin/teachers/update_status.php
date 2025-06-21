<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id']) || !isset($data['status'])) {
            throw new Exception('Invalid data provided');
        }

        $id = intval($data['id']);
        $status = mysqli_real_escape_string($conn, $data['status']);

        // Validate status
        $valid_statuses = ['Active', 'Inactive', 'On Leave'];
        if (!in_array($status, $valid_statuses)) {
            throw new Exception('Invalid status value');
        }

        // Update the status
        $query = "UPDATE teachers SET status = '$status' WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            echo json_encode([
                'success' => true, 
                'message' => 'Status updated successfully',
                'status' => $status
            ]);
        } else {
            throw new Exception(mysqli_error($conn));
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?> 