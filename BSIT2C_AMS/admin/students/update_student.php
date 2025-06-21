<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $id = $_POST['id'];
        $student_id = $_POST['student_id'];
        $first_name = $_POST['first_name'];
        $middle_name = $_POST['middle_name'] ?? null;
        $last_name = $_POST['last_name'];
        $gender = $_POST['gender'];
        $dob = $_POST['dob'];
        $email = $_POST['email'];
        $phone = $_POST['phone'] ?? null;
        $address = $_POST['address'] ?? null;
        $current_date = date('Y-m-d H:i:s');
        
        // Validate required fields
        if (empty($student_id) || empty($first_name) || empty($last_name) || 
            empty($gender) || empty($dob) || empty($email)) {
            throw new Exception("All required fields must be filled");
        }
        
        // Check if email is already taken by another student
        $check_email = "SELECT id FROM students WHERE email = ? AND id != ?";
        $stmt = $conn->prepare($check_email);
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Email already exists for another student");
        }
        
        // Update student data - FIXED: changed 'phone' to 'phone_number'
        $sql = "UPDATE students SET 
                first_name = ?, 
                middle_name = ?, 
                last_name = ?, 
                gender = ?, 
                date_of_birth = ?, 
                email = ?, 
                phone_number = ?, 
                address = ?, 
                updated_at = ?
                WHERE id = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssssi", 
            $first_name, 
            $middle_name, 
            $last_name, 
            $gender, 
            $dob, 
            $email, 
            $phone, 
            $address, 
            $current_date,
            $id
        );
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Student successfully updated'
            ]);
        } else {
            throw new Exception("Failed to update student: " . $conn->error);
        }
        
    } catch (Exception $e) {
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