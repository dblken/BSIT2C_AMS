<?php
require_once 'config/database.php';

// Clear existing data
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("TRUNCATE TABLE attendance");
$conn->query("TRUNCATE TABLE student_subjects");
$conn->query("TRUNCATE TABLE students");
$conn->query("TRUNCATE TABLE teachers");
$conn->query("TRUNCATE TABLE users");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

// Create admin account
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, password, role) VALUES ('admin', ?, 'admin')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $admin_password);
$stmt->execute();
echo "Admin account created<br>";

// Create teacher account
$teacher_password = password_hash('teacher123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, password, role) VALUES ('teacher', ?, 'teacher')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $teacher_password);
$stmt->execute();
$teacher_user_id = $conn->insert_id;

// Add teacher details
$sql = "INSERT INTO teachers (user_id, teacher_id, first_name, middle_name, last_name, gender, date_of_birth, email, phone_number, department, designation) 
        VALUES (?, 'T001', 'John', 'M', 'Doe', 'Male', '1980-01-01', 'john@example.com', '1234567890', 'IT Department', 'Full-time')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_user_id);
$stmt->execute();
echo "Teacher account created<br>";

// Create student account
$student_password = password_hash('student123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, password, role) VALUES ('student', ?, 'student')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_password);
$stmt->execute();
$student_user_id = $conn->insert_id;

// Add student details
$sql = "INSERT INTO students (user_id, student_id, first_name, middle_name, last_name, gender, date_of_birth, email, phone_number, program, year_level) 
        VALUES (?, 'S001', 'Jane', 'A', 'Smith', 'Female', '2000-01-01', 'jane@example.com', '0987654321', 'BSIT', 2)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_user_id);
$stmt->execute();
echo "Student account created<br>";

echo "All test accounts have been created successfully!";
?> 