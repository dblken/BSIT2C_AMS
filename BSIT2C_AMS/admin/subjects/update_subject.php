<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $subject_code = mysqli_real_escape_string($conn, $_POST['subject_code']);
        $subject_name = mysqli_real_escape_string($conn, $_POST['subject_name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $units = mysqli_real_escape_string($conn, $_POST['units']);

        // Check if subject code exists for other subjects
        $check_query = "SELECT id FROM subjects WHERE subject_code = '$subject_code' AND id != '$id'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            throw new Exception('Subject code already exists');
        }

        $query = "UPDATE subjects SET 
                  subject_code = '$subject_code',
                  subject_name = '$subject_name',
                  description = '$description',
                  units = '$units'
                  WHERE id = '$id'";

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