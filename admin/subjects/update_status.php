<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = mysqli_real_escape_string($conn, $data['id']);
        $status = mysqli_real_escape_string($conn, $data['status']);

        // Validate status
        if (!in_array($status, ['Active', 'Inactive'])) {
            throw new Exception('Invalid status value');
        }

        $query = "UPDATE subjects SET status = '$status' WHERE id = '$id'";
        
        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception(mysqli_error($conn));
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?> 