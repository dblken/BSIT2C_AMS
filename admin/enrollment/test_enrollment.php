<?php
session_start();
require_once '../../config/database.php';

// Set the content type to text to make debugging easier
header('Content-Type: text/plain');

// Check database connection
echo "Testing database connection...\n";
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "Database connection successful!\n\n";
}

// Check if required tables exist
echo "Checking required tables...\n";
$tables = ['students', 'subjects', 'enrollments', 'assignments', 'teachers', 'timetable', 'notifications'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "- Table '$table' exists.\n";
    } else {
        echo "- Table '$table' DOES NOT EXIST.\n";
    }
}
echo "\n";

// Check assignments table structure
echo "Checking assignments table structure...\n";
$result = $conn->query("DESCRIBE assignments");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "- Error checking assignments table structure: " . $conn->error . "\n";
}
echo "\n";

// Check enrollment table structure
echo "Checking enrollments table structure...\n";
$result = $conn->query("DESCRIBE enrollments");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "- Error checking enrollments table structure: " . $conn->error . "\n";
}
echo "\n";

// Check if there are assignments available
echo "Checking for available assignments...\n";
$query = "SELECT COUNT(*) as count FROM assignments";
$result = $conn->query($query);
if ($result) {
    $count = $result->fetch_assoc()['count'];
    echo "- Found $count assignments.\n";
} else {
    echo "- Error checking assignments: " . $conn->error . "\n";
}
echo "\n";

// Test the assignment query in process_enrollment.php
echo "Testing assignment query...\n";
$assignment_id = 1; // Using a test value
$assignment_query = "SELECT a.*, s.id AS subject_id, s.subject_name, s.subject_code, 
                           t.id AS teacher_id, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
                           tt.day AS day, tt.time_start, tt.time_end
                    FROM assignments a
                    JOIN subjects s ON a.subject_id = s.id
                    JOIN teachers t ON a.teacher_id = t.id
                    LEFT JOIN timetable tt ON a.id = tt.assignment_id
                    WHERE a.id = ?";

$stmt = $conn->prepare($assignment_query);
if ($stmt) {
    $stmt->bind_param("i", $assignment_id);
    $exec_result = $stmt->execute();
    if ($exec_result) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            echo "- Query executed successfully, found matching assignment.\n";
        } else {
            echo "- Query executed but no assignment found with ID = $assignment_id.\n";
        }
    } else {
        echo "- Error executing query: " . $stmt->error . "\n";
    }
} else {
    echo "- Error preparing statement: " . $conn->error . "\n";
}

echo "\nDone testing.";
?> 