<?php
session_start();
require_once '../../config/database.php';

// For debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $id = isset($_POST['id']) ? trim($_POST['id']) : '';
        $teacher_id = isset($_POST['teacher_id']) ? trim($_POST['teacher_id']) : '';
        $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
        $middle_name = isset($_POST['middle_name']) && $_POST['middle_name'] !== '' ? trim($_POST['middle_name']) : null;
        $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
        $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
        $department = isset($_POST['department']) ? trim($_POST['department']) : '';
        $current_date = date('Y-m-d H:i:s');
        
        // Validate required fields
        if (empty($id) || empty($teacher_id) || empty($first_name) || empty($last_name) || empty($email) || empty($department)) {
            throw new Exception("All required fields must be filled");
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address");
        }
        
        // Additional validation for domain part
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            throw new Exception("Please enter a valid email address in the format example@domain.com");
        }
        
        $domain = $parts[1];
        
        // Check if domain has at least one dot and the TLD is at least 2 characters
        $domainParts = explode('.', $domain);
        $tld = end($domainParts);
        
        // Validate domain structure (must have at least a domain name and TLD)
        if (count($domainParts) < 2 || empty($domainParts[0])) {
            throw new Exception("Invalid domain name format");
        }
        
        // Check if domain name follows valid format (alphanumeric with hyphens, not starting or ending with hyphen)
        foreach ($domainParts as $part) {
            if (empty($part) || !preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/i', $part)) {
                throw new Exception("Invalid domain name format. Domain parts must contain only letters, numbers, and hyphens, and cannot start or end with a hyphen.");
            }
        }
        
        // List of common generic TLDs
        $validTlds = ['com', 'net', 'org', 'edu', 'gov', 'mil', 'int', 'info', 'biz', 'name', 'pro', 'museum', 'coop', 'aero', 'xxx', 'idv', 'ac', 'edu'];
        
        // Check for multiple domain extensions (e.g., .com.com.com)
        $domainDotsCount = substr_count($domain, '.');
        if ($domainDotsCount > 2 || count($domainParts) > 3) {
            throw new Exception("Invalid email domain. Multiple extensions are not allowed.");
        }
        
        if (count($domainParts) < 2 || strlen($tld) < 2 || !in_array($tld, $validTlds)) {
            throw new Exception("Invalid email domain. Only common domains like .com, .net, .org, etc. are accepted");
        }
        
        // Check if email exists for other teachers
        $check_email = "SELECT id FROM teachers WHERE email = ? AND id != ?";
        $stmt = $conn->prepare($check_email);
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception("Email address already in use by another teacher");
        }
        
        // Check if teacher_id exists for other teachers
        $check_id = "SELECT id FROM teachers WHERE teacher_id = ? AND id != ?";
        $stmt = $conn->prepare($check_id);
        $stmt->bind_param("si", $teacher_id, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception("Teacher ID already in use by another teacher");
        }
        
        // Update teacher
        $update_teacher = "UPDATE teachers SET 
                          teacher_id = ?, 
                          first_name = ?, 
                          middle_name = ?, 
                          last_name = ?, 
                          gender = ?, 
                          email = ?, 
                          phone = ?, 
                          department = ?, 
                          updated_at = ?
                          WHERE id = ?";
        
        $stmt = $conn->prepare($update_teacher);
        $stmt->bind_param("sssssssssi", 
            $teacher_id, 
            $first_name, 
            $middle_name, 
            $last_name, 
            $gender, 
            $email, 
            $phone,
            $department, 
            $current_date, 
            $id
        );
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Teacher updated successfully'
            ]);
        } else {
            throw new Exception("Error updating teacher: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        error_log("Update teacher error: " . $e->getMessage());
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

$conn->close();
?> 