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
        $conn->begin_transaction();

        // Insert into users table
        $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, 'student')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $_POST['username'], $_POST['password']);
        $stmt->execute();
        $user_id = $conn->insert_id;

        // Insert into students table
        $sql = "INSERT INTO students (user_id, student_id, first_name, middle_name, last_name, 
                gender, date_of_birth, email, phone_number, program, year_level, section) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssssssss", $user_id, $_POST['student_id'], $_POST['first_name'], 
                         $_POST['middle_name'], $_POST['last_name'], $_POST['gender'], 
                         $_POST['date_of_birth'], $_POST['email'], $_POST['phone_number'], 
                         $_POST['program'], $_POST['year_level'], $_POST['section']);
        $stmt->execute();

        $conn->commit();
        $response['success'] = true;
        $response['message'] = "Student registered successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = "Error: " . $e->getMessage();
    }

    echo json_encode($response);
    exit();
}
?> 