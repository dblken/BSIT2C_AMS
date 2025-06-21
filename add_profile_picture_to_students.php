<?php
// Set the content type to plain text for easier debugging
header('Content-Type: text/plain');

// Database connection
$db_host = "localhost";
$db_user = "root";
$db_password = "1234";
$db_name = "attendance_system_1";

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to database successfully.\n\n";

// Check if profile_picture column already exists in students table
$result = $conn->query("SHOW COLUMNS FROM students LIKE 'profile_picture'");
if ($result->num_rows > 0) {
    echo "The profile_picture column already exists in the students table.\n";
} else {
    // Add profile_picture column to students table
    $sql = "ALTER TABLE students ADD COLUMN profile_picture VARCHAR(255) DEFAULT 'default.jpg'";
    
    if ($conn->query($sql) === TRUE) {
        echo "Successfully added profile_picture column to students table.\n";
    } else {
        echo "Error adding profile_picture column: " . $conn->error . "\n";
    }
}

$conn->close();
echo "\nDone!";
?> 