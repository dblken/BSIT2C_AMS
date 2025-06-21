<?php
session_start();
require_once '../../config/database.php';

// For debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Check if form data is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $teacher_id = $_POST['teacher_id'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $department = $_POST['department'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Use the provided password or fallback to default
    $password_to_use = !empty($password) ? $password : (!empty($teacher_id) ? $teacher_id : 'teacher123');
    $hashed_password = password_hash($password_to_use, PASSWORD_DEFAULT);
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($teacher_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please fill in all required fields (teacher ID, first name, last name, email, and username)'
        ]);
        exit;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid email address in the format example@domain.com'
        ]);
        exit;
    }
    
    // Additional validation for domain part
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid email address in the format example@domain.com'
        ]);
        exit;
    }
    
    $domain = $parts[1];
    
    // Check if domain has at least one dot and the TLD is at least 2 characters
    $domainParts = explode('.', $domain);
    $tld = end($domainParts);
    
    // Validate domain structure (must have at least a domain name and TLD)
    if (count($domainParts) < 2 || empty($domainParts[0])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid domain name format'
        ]);
        exit;
    }
    
    // Check if domain name follows valid format (alphanumeric with hyphens, not starting or ending with hyphen)
    foreach ($domainParts as $part) {
        if (empty($part) || !preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/i', $part)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid domain name format. Domain parts must contain only letters, numbers, and hyphens, and cannot start or end with a hyphen.'
            ]);
            exit;
        }
    }
    
    // List of common generic TLDs
    $validTlds = ['com', 'net', 'org', 'edu', 'gov', 'mil', 'int', 'info', 'biz', 'name', 'pro', 'museum', 'coop', 'aero', 'xxx', 'idv', 'ac', 'edu'];
    
    // Check for multiple domain extensions (e.g., .com.com.com)
    $domainDotsCount = substr_count($domain, '.');
    if ($domainDotsCount > 2 || count($domainParts) > 3) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email domain. Multiple extensions are not allowed.'
        ]);
        exit;
    }
    
    if (count($domainParts) < 2 || strlen($tld) < 2 || !in_array($tld, $validTlds)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email domain. Only common domains like .com, .net, .org, etc. are accepted'
        ]);
        exit;
    }
    
    // Check if email already exists
    $check_email = "SELECT id FROM teachers WHERE email = ?";
    $stmt = $conn->prepare($check_email);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Email address already in use'
        ]);
        exit;
    }
    
    // Check if teacher_id already exists
    $check_teacher_id = "SELECT id FROM teachers WHERE teacher_id = ?";
    $stmt = $conn->prepare($check_teacher_id);
    $stmt->bind_param("s", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Teacher ID already in use'
        ]);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First create user account
        $create_user = "INSERT INTO users (username, password, role) VALUES (?, ?, 'teacher')";
        $stmt = $conn->prepare($create_user);
        $stmt->bind_param("ss", $username, $hashed_password);
        $stmt->execute();
        $user_id = $conn->insert_id;
        
        // Then create teacher record
        $create_teacher = "INSERT INTO teachers (user_id, teacher_id, first_name, middle_name, last_name, gender, birthday, email, phone, department, status) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($create_teacher);
        $stmt->bind_param("issssssssss", $user_id, $teacher_id, $first_name, $middle_name, $last_name, $gender, $birthday, $email, $phone, $department, $status);
        
        // Log the values for debugging
        error_log("Adding teacher with values: " . json_encode([
            'user_id' => $user_id,
            'teacher_id' => $teacher_id,
            'first_name' => $first_name,
            'middle_name' => $middle_name,
            'last_name' => $last_name,
            'gender' => $gender,
            'birthday' => $birthday,
            'email' => $email,
            'phone' => $phone,
            'department' => $department,
            'status' => $status
        ]));
        
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Teacher added successfully'
        ]);
    } catch (Exception $e) {
        // An error occurred, rollback
        $conn->rollback();
        error_log("Error adding teacher: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error adding teacher: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?> 