<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    // Check user credentials and role
    $sql = "SELECT u.id, u.password, u.role FROM users u WHERE u.username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        if ($password == $user['password']) {
            // Verify user is a teacher
            if ($user['role'] != 'teacher') {
                $_SESSION['error'] = "Please use the appropriate login portal for your role.";
                if ($user['role'] == 'admin') {
                    header("Location: ../admin/");
                } else {
                    header("Location: ../student/");
                }
                exit();
            }

            // Get teacher details
            $sql = "SELECT id FROM teachers WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $teacher_result = $stmt->get_result();

            if ($teacher_result->num_rows == 1) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['teacher_id'] = $teacher_result->fetch_assoc()['id'];
                
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