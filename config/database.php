<?php
// Enable error reporting at the beginning of the file
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '1234';
$database = 'attendance_system_1';

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set character set
mysqli_set_charset($conn, "utf8mb4");

// Optionally, set timezone
date_default_timezone_set('Asia/Manila'); // Adjust to your timezone

// Add last_login column if it doesn't exist
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'last_login'");
if (mysqli_num_rows($check_column) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL");
}

// Create admins table if it doesn't exist
$check_admins_table = mysqli_query($conn, "SHOW TABLES LIKE 'admins'");
if (mysqli_num_rows($check_admins_table) == 0) {
    $create_admins_table = "CREATE TABLE admins (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        full_name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME DEFAULT NULL
    )";
    
    if (mysqli_query($conn, $create_admins_table)) {
        // Insert default admin account
        $default_username = 'admin';
        $default_password = password_hash('admin123', PASSWORD_DEFAULT);
        $default_email = 'admin@example.com';
        $default_name = 'System Administrator';
        
        $insert_admin = "INSERT INTO admins (username, password, email, full_name) 
                        VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_admin);
        mysqli_stmt_bind_param($stmt, "ssss", $default_username, $default_password, $default_email, $default_name);
        mysqli_stmt_execute($stmt);
    }
}

// Add status column to teachers table if it doesn't exist
$check_status_column = mysqli_query($conn, "SHOW COLUMNS FROM teachers LIKE 'status'");
if (mysqli_num_rows($check_status_column) == 0) {
    mysqli_query($conn, "ALTER TABLE teachers ADD COLUMN status ENUM('Active', 'Inactive', 'On Leave') NOT NULL DEFAULT 'Active'");
    
    // Update existing teachers to Active status
    mysqli_query($conn, "UPDATE teachers SET status = 'Active' WHERE status IS NULL");
}

// Add gender column to teachers table if it doesn't exist
$check_gender_column = mysqli_query($conn, "SHOW COLUMNS FROM teachers LIKE 'gender'");
if (mysqli_num_rows($check_gender_column) == 0) {
    mysqli_query($conn, "ALTER TABLE teachers ADD COLUMN gender ENUM('Male', 'Female') NULL");
}

// Create attendance table if it doesn't exist
$check_attendance_table = mysqli_query($conn, "SHOW TABLES LIKE 'attendance'");
if (mysqli_num_rows($check_attendance_table) == 0) {
    $create_attendance_table = "CREATE TABLE attendance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        subject_id INT NOT NULL,
        teacher_id INT NOT NULL,
        attendance_date DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
    )";
    
    mysqli_query($conn, $create_attendance_table);
}

// Create attendance_records table if it doesn't exist
$check_records_table = mysqli_query($conn, "SHOW TABLES LIKE 'attendance_records'");
if (mysqli_num_rows($check_records_table) == 0) {
    $create_records_table = "CREATE TABLE attendance_records (
        id INT PRIMARY KEY AUTO_INCREMENT,
        attendance_id INT NOT NULL,
        student_id INT NOT NULL,
        status ENUM('present', 'late', 'absent') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE
    )";
    
    mysqli_query($conn, $create_records_table);
}

// Create assignments table if it doesn't exist
$check_assignments_table = mysqli_query($conn, "SHOW TABLES LIKE 'assignments'");
if (mysqli_num_rows($check_assignments_table) == 0) {
    $create_assignments_table = "CREATE TABLE assignments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        teacher_id INT NOT NULL,
        subject_id INT NOT NULL,
        month_from DATE NULL,
        month_to DATE NULL,
        preferred_day TEXT NULL,
        time_start TIME NULL,
        time_end TIME NULL,
        location VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
    )";
    
    mysqli_query($conn, $create_assignments_table);
} else {
    // Check and add missing columns if the table already exists
    $required_columns = [
        'month_from' => "ALTER TABLE assignments ADD COLUMN month_from DATE NULL",
        'month_to' => "ALTER TABLE assignments ADD COLUMN month_to DATE NULL",
        'preferred_day' => "ALTER TABLE assignments ADD COLUMN preferred_day TEXT NULL",
        'time_start' => "ALTER TABLE assignments ADD COLUMN time_start TIME NULL",
        'time_end' => "ALTER TABLE assignments ADD COLUMN time_end TIME NULL",
        'location' => "ALTER TABLE assignments ADD COLUMN location VARCHAR(255) NULL"
    ];
    
    foreach ($required_columns as $column => $alter_query) {
        $check_column = mysqli_query($conn, "SHOW COLUMNS FROM assignments LIKE '$column'");
        if (mysqli_num_rows($check_column) == 0) {
            mysqli_query($conn, $alter_query);
        }
    }
}

// Add status column to students table if it doesn't exist
$check_student_status = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'status'");
if (mysqli_num_rows($check_student_status) == 0) {
    mysqli_query($conn, "ALTER TABLE students ADD COLUMN status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active'");
    
    // Update existing students to Active status
    mysqli_query($conn, "UPDATE students SET status = 'Active' WHERE status IS NULL");
}

// Add department column to teachers table if it doesn't exist
$check_department_column = mysqli_query($conn, "SHOW COLUMNS FROM teachers LIKE 'department'");
if (mysqli_num_rows($check_department_column) == 0) {
    mysqli_query($conn, "ALTER TABLE teachers ADD COLUMN department VARCHAR(100) DEFAULT 'Computer Studies'");
}

// Add birthday column to teachers table if it doesn't exist
$check_birthday_column = mysqli_query($conn, "SHOW COLUMNS FROM teachers LIKE 'birthday'");
if (mysqli_num_rows($check_birthday_column) == 0) {
    mysqli_query($conn, "ALTER TABLE teachers ADD COLUMN birthday DATE NULL");
}

// Create notifications table if it doesn't exist
$check_notifications_table = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
if (mysqli_num_rows($check_notifications_table) == 0) {
    $create_notifications_table = "CREATE TABLE notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        teacher_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
    )";
    
    mysqli_query($conn, $create_notifications_table);
}
?> 