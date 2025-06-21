<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "ENROLLMENT TABLE STRUCTURE CHECK\n";
echo "===============================\n\n";

// Include database connection
require_once 'config/database.php';

// Check enrollment table structure 
echo "Checking enrollments table structure:\n";
$result = $conn->query("DESCRIBE enrollments");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

// Check if subject_id column exists
echo "\nVerifying required columns for enrollment process:\n";
$columns = ['student_id', 'subject_id', 'assignment_id', 'created_at'];
foreach ($columns as $column) {
    $check = $conn->query("SHOW COLUMNS FROM enrollments LIKE '$column'");
    if ($check && $check->num_rows > 0) {
        echo "- Column '$column' exists ✓\n";
    } else {
        echo "- Column '$column' DOES NOT EXIST ✗\n";
    }
}

// Check for sample data
echo "\nSample data from enrollments table:\n";
$sample = $conn->query("SELECT * FROM enrollments LIMIT 3");
if ($sample && $sample->num_rows > 0) {
    $i = 1;
    while ($row = $sample->fetch_assoc()) {
        echo "Row $i:\n";
        foreach ($row as $key => $value) {
            echo "  $key: $value\n";
        }
        $i++;
    }
} else {
    echo "No data found in enrollments table or error: " . $conn->error . "\n";
}

?> 