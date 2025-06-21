<?php
session_start();
require_once '../../config/database.php';

// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "ENROLLMENT SYSTEM VERIFICATION\n";
echo "=============================\n\n";

// Check database tables structure
echo "CHECKING DATABASE STRUCTURE...\n";

// Check students table
echo "\nStudents Table:\n";
$result = $conn->query("DESCRIBE students");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

// Check teachers table
echo "\nTeachers Table:\n";
$result = $conn->query("DESCRIBE teachers");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

// Check enrollments table
echo "\nEnrollments Table:\n";
$result = $conn->query("DESCRIBE enrollments");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

// Verify queries
echo "\n\nVERIFYING QUERIES...\n";

// Test teacher name query
echo "\nTeacher Name Query Test:\n";
$query = "SELECT t.id, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name 
         FROM teachers t LIMIT 1";
$result = $conn->query($query);
if ($result) {
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "Success! Teacher ID: {$row['id']}, Name: {$row['teacher_name']}\n";
    } else {
        echo "No teachers found in the database.\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

// Test student name query
echo "\nStudent Name Query Test:\n";
$query = "SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) AS student_name 
         FROM students s LIMIT 1";
$result = $conn->query($query);
if ($result) {
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "Success! Student ID: {$row['id']}, Name: {$row['student_name']}\n";
    } else {
        echo "No students found in the database.\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

// Test assignment query
echo "\nAssignment Query Test:\n";
$query = "SELECT a.id, s.subject_name, 
         CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
         FROM assignments a
         JOIN subjects s ON a.subject_id = s.id
         JOIN teachers t ON a.teacher_id = t.id
         LIMIT 1";
$result = $conn->query($query);
if ($result) {
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "Success! Assignment ID: {$row['id']}, Subject: {$row['subject_name']}, Teacher: {$row['teacher_name']}\n";
    } else {
        echo "No assignments found in the database.\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "\n\nVERIFICATION COMPLETE\n";
echo "=====================\n";
?> 