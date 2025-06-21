<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    // First check if user exists in users table
    $sql = "SELECT u.id, u.password FROM users u WHERE u.username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // For simplicity, using direct comparison
        // In production, use password_verify()
        if ($password == $user['password']) {
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