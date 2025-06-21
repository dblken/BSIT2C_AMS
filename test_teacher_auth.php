<?php
require_once 'config/database.php';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? "Connection not established"));
}

// Test credentials
$username = 'teacher1';
$password = 'teacher123';

echo "Testing teacher login with username: $username\n";

// Query user without role filter
$sql = "SELECT u.id, u.username, u.password, u.role, t.id as teacher_id, t.first_name, t.last_name 
        FROM users u 
        JOIN teachers t ON u.id = t.user_id 
        WHERE u.username = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("s", $username);
$stmt->execute();

if ($stmt->error) {
    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "User found: " . $row['username'] . "\n";
    echo "Role: " . $row['role'] . "\n";
    echo "Teacher ID: " . $row['teacher_id'] . "\n";
    echo "Name: " . $row['first_name'] . " " . $row['last_name'] . "\n";
    
    // Check password with direct comparison
    echo "\nPassword check (direct): " . ($password === $row['password'] ? "Success" : "Failed") . "\n";
    
    // Check password with password_verify
    echo "Password check (password_verify): " . (password_verify($password, $row['password']) ? "Success" : "Failed") . "\n";
    
    // Our combined approach
    $auth_success = ($password === $row['password'] || password_verify($password, $row['password']));
    echo "Combined auth check: " . ($auth_success ? "Success" : "Failed") . "\n";
} else {
    echo "No user found with username: $username\n";
}
?> 