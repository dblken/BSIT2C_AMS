<?php
require_once 'config/database.php';

// Create admin account with password 'admin123'
$username = 'admin';
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert into users table
$sql = "INSERT INTO users (username, password) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $hashed_password);
$stmt->execute();
$user_id = $conn->insert_id;

// Insert admin details
$sql = "INSERT INTO admins (
    user_id, admin_id, first_name, last_name, gender, date_of_birth, 
    email, phone_number, department, designation
) VALUES (
    ?, 'A001', 'Admin', 'User', 'Male', '1990-01-01',
    'admin@test.com', '1234567890', 'IT', 'Administrator'
)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

echo "Admin account created successfully!<br>";
echo "Username: admin<br>";
echo "Password: admin123<br>";
echo "Generated Hash: $hashed_password<br>";
?> 