<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['student_id_for_update', 'student_id', 'first_name', 'last_name', 'email'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("$field is required");
            }
        }

        // Get and sanitize data
        $id = mysqli_real_escape_string($conn, $_POST['student_id_for_update']);
        $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $birthday = mysqli_real_escape_string($conn, $_POST['birthday']);
        $gender = mysqli_real_escape_string($conn, $_POST['gender']);
        $course = mysqli_real_escape_string($conn, $_POST['course']);
        $year_level = mysqli_real_escape_string($conn, $_POST['year_level']);
        $section = mysqli_real_escape_string($conn, $_POST['section']);

        // Check if student ID already exists for other students
        $check_query = "SELECT id FROM students WHERE student_id = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("si", $student_id, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            throw new Exception('Student ID already exists');
        }

        // Check if email already exists for other students
        $check_query = "SELECT id FROM students WHERE email = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("si", $email, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            throw new Exception('Email already exists');
        }

        // Start transaction
        $conn->begin_transaction();

        // Update student information
        $update_query = "UPDATE students SET 
            student_id = ?,
            first_name = ?,
            middle_name = ?,
            last_name = ?,
            email = ?,
            phone = ?,
            address = ?,
            birthday = ?,
            gender = ?,
            course = ?,
            year_level = ?,
            section = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ?";
            
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ssssssssssssi",
            $student_id,
            $first_name,
            $middle_name,
            $last_name,
            $email,
            $phone,
            $address,
            $birthday,
            $gender,
            $course,
            $year_level,
            $section,
            $id
        );

        if (!$update_stmt->execute()) {
            throw new Exception('Error updating student: ' . $update_stmt->error);
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Student updated successfully'
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollback();
        }

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?> 