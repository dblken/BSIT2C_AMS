<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT u.id, u.password, t.id as teacher_id, t.first_name, t.last_name 
            FROM users u 
            JOIN teachers t ON u.id = t.user_id 
            WHERE u.username = ? AND u.role = 'teacher'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['teacher_id'] = $row['teacher_id'];
            $_SESSION['role'] = 'teacher';
            $_SESSION['name'] = $row['first_name'] . ' ' . $row['last_name'];
            header('Location: teacher/dashboard.php');
            exit();
        }
    }
    $error = "Invalid credentials";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Teacher Login - BSIT 2C</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <h2>Teacher Login</h2>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        
        <div class="login-links">
            <a href="index.php">Back to Home</a>
        </div>
    </div>
</body>
</html> 