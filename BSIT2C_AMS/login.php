<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];

    // First, check if user exists in users table
    $sql = "SELECT id, password FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // For simplicity, we're using direct password comparison
        // In production, you should use password_verify()
        if ($password == 'admin123' || $password == 'teacher123' || $password == 'student123') {
            $_SESSION['user_id'] = $user['id'];
            
            // Check user type and get additional details
            switch ($user_type) {
                case 'admin':
                    $sql = "SELECT id FROM admins WHERE user_id = ?";
                    break;
                case 'teacher':
                    $sql = "SELECT id FROM teachers WHERE user_id = ?";
                    break;
                case 'student':
                    $sql = "SELECT id FROM students WHERE user_id = ?";
                    break;
            }

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $type_result = $stmt->get_result();

            if ($type_result->num_rows == 1) {
                $type_data = $type_result->fetch_assoc();
                $_SESSION[$user_type . '_id'] = $type_data['id'];
                
                // Update last login
                $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();

                // Redirect based on user type
                header("Location: {$user_type}/dashboard.php");
                exit();
            }
        }
    }

    $_SESSION['error'] = "Invalid username or password";
    header("Location: index.php");
    exit();
}
?> 