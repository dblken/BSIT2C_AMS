<?php
require_once 'config/database.php';

// Start transaction
$conn->begin_transaction();

try {
    // Create admin user
    $sql = "INSERT INTO users (username, password) VALUES ('admin', 'admin123')";
    $conn->query($sql);
    $admin_user_id = $conn->insert_id;

    $sql = "INSERT INTO admins (user_id, admin_id, first_name, last_name, gender, date_of_birth, email, department, designation) 
            VALUES ($admin_user_id, 'A001', 'Admin', 'User', 'Male', '1990-01-01', 'admin@test.com', 'IT', 'Administrator')";
    $conn->query($sql);

    // Create test teacher
    $sql = "INSERT INTO users (username, password) VALUES ('teacher', 'teacher123')";
    $conn->query($sql);
    $teacher_user_id = $conn->insert_id;

    $sql = "INSERT INTO teachers (user_id, teacher_id, first_name, last_name, gender, date_of_birth, email, department, designation) 
            VALUES ($teacher_user_id, 'T001', 'Teacher', 'User', 'Male', '1985-01-01', 'teacher@test.com', 'IT', 'Full-time')";
    $conn->query($sql);

    // Create test student
    $sql = "INSERT INTO users (username, password) VALUES ('student', 'student123')";
    $conn->query($sql);
    $student_user_id = $conn->insert_id;

    $sql = "INSERT INTO students (user_id, student_id, first_name, last_name, gender, date_of_birth, email, program, year_level, section) 
            VALUES ($student_user_id, 'S001', 'Student', 'User', 'Male', '2000-01-01', 'student@test.com', 'BSIT', 2, 'Block C')";
    $conn->query($sql);

    $conn->commit();
    echo "Test users created successfully!<br>";
    echo "Admin login: username='admin' password='admin123'<br>";
    echo "Teacher login: username='teacher' password='teacher123'<br>";
    echo "Student login: username='student' password='student123'<br>";

} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}
?> 