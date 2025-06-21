<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $response = ['success' => false, 'message' => ''];

    try {
        $subject_name = $conn->real_escape_string($_POST['subject_name']);
        $subject_code = $conn->real_escape_string($_POST['subject_code']);
        $description = $conn->real_escape_string($_POST['description']);

        // Check for duplicate
        $stmt = $conn->prepare("SELECT id FROM subjects WHERE subject_name = ? OR (subject_code = ? AND ? != '')");
        $stmt->bind_param("sss", $subject_name, $subject_code, $subject_code);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Subject name or code already exists!");
        }

        // Insert subject
        $sql = "INSERT INTO subjects (subject_name, subject_code, description) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $subject_name, $subject_code, $description);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Subject '$subject_name' created successfully!";
        } else {
            throw new Exception("Error creating subject");
        }

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit();
}
?> 