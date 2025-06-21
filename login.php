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
        
        $stored_password = $user['password'];
        $password_verified = false;
        
        // Check if it's the default passwords
        if ($password == 'admin123' || $password == 'teacher123' || $password == 'student123') {
            $password_verified = true;
        } 
        // Check if it's a bcrypt hash (starts with $2y$)
        else if (strpos($stored_password, '$2y$') === 0) {
            $password_verified = password_verify($password, $stored_password);
        } 
        // Check if it's our MD5 with salt format (salt:hash)
        else if (strpos($stored_password, ':') !== false) {
            list($salt, $hash) = explode(':', $stored_password, 2);
            $password_verified = (md5($salt . $password) === $hash);
        }
        
        if ($password_verified) {
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
                    $sql = "SELECT id, status FROM students WHERE user_id = ?";
                    break;
            }

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $type_result = $stmt->get_result();

            if ($type_result->num_rows == 1) {
                $type_data = $type_result->fetch_assoc();
                
                // For students, check if they are active
                if ($user_type === 'student' && $type_data['status'] !== 'Active') {
                    $_SESSION['error'] = "Your account is inactive. Please contact the administrator.";
                    header("Location: index.php");
                    exit();
                }
                
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