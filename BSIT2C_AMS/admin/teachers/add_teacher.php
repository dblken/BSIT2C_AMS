<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $teacher_id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $gender = mysqli_real_escape_string($conn, $_POST['gender']);
        $date_of_birth = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $department = mysqli_real_escape_string($conn, $_POST['department']);
        $designation = mysqli_real_escape_string($conn, $_POST['designation']);

        // Insert the teacher
        $query = "INSERT INTO teachers (
            teacher_id, first_name, middle_name, last_name, 
            gender, date_of_birth, email, phone_number, 
            address, department, designation
        ) VALUES (
            '$teacher_id', '$first_name', '$middle_name', '$last_name',
            '$gender', '$date_of_birth', '$email', '$phone_number',
            '$address', '$department', '$designation'
        )";

        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true, 'message' => 'Teacher added successfully']);
        } else {
            throw new Exception(mysqli_error($conn));
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 