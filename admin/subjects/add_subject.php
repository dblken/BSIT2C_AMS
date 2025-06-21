<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $subject_code = mysqli_real_escape_string($conn, $_POST['subject_code']);
        $subject_name = mysqli_real_escape_string($conn, $_POST['subject_name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $units = mysqli_real_escape_string($conn, $_POST['units']);

        // Check if subject code already exists
        $check_query = "SELECT id FROM subjects WHERE subject_code = '$subject_code'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            throw new Exception('Subject code already exists');
        }

        $query = "INSERT INTO subjects (subject_code, subject_name, description, units) 
                  VALUES ('$subject_code', '$subject_name', '$description', '$units')";

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