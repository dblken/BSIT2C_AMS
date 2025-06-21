<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = mysqli_real_escape_string($conn, $data['id']);

        // Check if subject is assigned to any teacher
        $check_query = "SELECT COUNT(*) as count FROM assignments WHERE subject_id = '$id'";
        $check_result = mysqli_query($conn, $check_query);
        $row = mysqli_fetch_assoc($check_result);

        if ($row['count'] > 0) {
            throw new Exception('Cannot delete subject because it is assigned to a teacher');
        }

        $query = "DELETE FROM subjects WHERE id = '$id'";
        
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