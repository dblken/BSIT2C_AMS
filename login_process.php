<?php
session_start();
require_once 'config/database.php';
require_once 'config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $user_type = mysqli_real_escape_string($conn, $_POST['user_type']);

    // Validate inputs
    if (empty($username) || empty($password) || empty($user_type)) {
        $_SESSION['error'] = "All fields are required";
        header("Location: index.php");
        exit();
    }

    switch ($user_type) {
        case 'admin':
            $query = "SELECT * FROM admin WHERE username = ?";
            break;
        case 'teacher':
            $query = "SELECT * FROM teachers WHERE email = ?";
            break;
        case 'student':
            $query = "SELECT * FROM students WHERE student_id = ?";
            break;
        default:
            $_SESSION['error'] = "Invalid user type";
            header("Location: index.php");
            exit();
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        if ($user_type == 'admin') {
            if (password_verify($password, $user['password'])) {
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: " . url('admin/dashboard.php'));
                exit();
            }
        } elseif ($user_type == 'teacher') {
            if (password_verify($password, $user['password'])) {
                $_SESSION['teacher_id'] = $user['id'];
                $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
                header("Location: " . url('teacher/dashboard.php'));
                exit();
            }
        } elseif ($user_type == 'student') {
            if (password_verify($password, $user['password'])) {
                $_SESSION['student_id'] = $user['id'];
                $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
                header("Location: " . url('student/dashboard.php'));
                exit();
            }
        }
        
        // If password verification fails
        $_SESSION['error'] = "Invalid password";
        header("Location: index.php");
        exit();
    } else {
        // If user not found
        $_SESSION['error'] = "User not found";
        header("Location: index.php");
        exit();
    }
}

// If not POST request
header("Location: index.php");
exit();
?> 