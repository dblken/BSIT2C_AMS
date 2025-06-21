<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    // First check if user exists in users table
    $sql = "SELECT u.id, u.password, u.role FROM users u WHERE u.username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Check if the role is admin
        if ($user['role'] !== 'admin') {
            $_SESSION['error'] = "This account does not have admin privileges";
            header("Location: index.php");
            exit();
        }
        
        // Properly verify password with multiple methods
        $password_verified = false;
        
        // Method 1: Direct comparison (for non-hashed passwords)
        if ($password === $user['password']) {
            $password_verified = true;
        }
        // Method 2: Using password_verify for bcrypt hashes
        else if (password_verify($password, $user['password'])) {
            $password_verified = true;
        }
        
        if ($password_verified) {
            // Check if user is an admin
            $sql = "SELECT id FROM admins WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $admin_result = $stmt->get_result();

            if ($admin_result->num_rows == 1) {
                // User is an admin
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['admin_id'] = $admin_result->fetch_assoc()['id'];
                
                // Update last login
                $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();

                header("Location: dashboard.php");
                exit();
            }
        }
    }

    $_SESSION['error'] = "Invalid username or password";
    header("Location: index.php");
    exit();
}
?> 