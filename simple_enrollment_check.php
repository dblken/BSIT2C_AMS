<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "ENROLLMENT SYSTEM CHECK\n";
echo "=====================\n\n";

// Include database connection
require_once 'config/database.php';

echo "Checking database structure...\n\n";

// Check tables existence
$tables = ['students', 'subjects', 'teachers', 'assignments', 'timetable', 'enrollments'];
foreach ($tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        echo "✅ Table '$table' exists\n";
    } else {
        echo "❌ Table '$table' does not exist\n";
    }
}

echo "\nChecking timetable structure...\n";
$result = mysqli_query($conn, "DESCRIBE timetable");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

echo "\nChecking assignments structure...\n";
$result = mysqli_query($conn, "DESCRIBE assignments");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

echo "\nChecking enrollments structure...\n";
$result = mysqli_query($conn, "DESCRIBE enrollments");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

// Check if there are assignments with populated data
echo "\nVerifying assignment data...\n";
$query = "SELECT a.id, s.subject_code, s.subject_name, 
                 CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                 a.preferred_day, a.time_start, a.time_end, a.location
          FROM assignments a
          JOIN subjects s ON a.subject_id = s.id
          JOIN teachers t ON a.teacher_id = t.id
          LIMIT 2";

$result = mysqli_query($conn, $query);
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "\nAssignment #{$row['id']}:\n";
        echo "- Subject: {$row['subject_code']} - {$row['subject_name']}\n";
        echo "- Teacher: {$row['teacher_name']}\n";
        echo "- Day: {$row['preferred_day']}\n";
        echo "- Time: {$row['time_start']} - {$row['time_end']}\n";
        echo "- Location: {$row['location']}\n";
        
        // Check if this assignment has a timetable entry
        $timetable_check = mysqli_query($conn, "SELECT id, day, time_start, time_end FROM timetable WHERE assignment_id = {$row['id']}");
        if (mysqli_num_rows($timetable_check) > 0) {
            $tt = mysqli_fetch_assoc($timetable_check);
            echo "  ✅ Has timetable entry: ID {$tt['id']}, Day {$tt['day']}, Time {$tt['time_start']} - {$tt['time_end']}\n";
        } else {
            echo "  ❌ No timetable entry found\n";
        }
    }
} else {
    echo "No assignments found or error: " . mysqli_error($conn) . "\n";
}

// Verify assignment_id values in enrollments
echo "\nVerifying enrollments...\n";
$query = "SELECT e.id, e.student_id, e.subject_id, e.assignment_id, s.first_name, s.last_name 
          FROM enrollments e
          JOIN students s ON e.student_id = s.id
          LIMIT 3";
$result = mysqli_query($conn, $query);
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "\nEnrollment #{$row['id']}:\n";
        echo "- Student: {$row['first_name']} {$row['last_name']} (ID: {$row['student_id']})\n";
        echo "- Subject ID: {$row['subject_id']}\n";
        echo "- Assignment ID: {$row['assignment_id']}\n";
    }
} else {
    echo "No enrollments found or error: " . mysqli_error($conn) . "\n";
}

// Check existence of room column in timetable 
$result = mysqli_query($conn, "SHOW COLUMNS FROM timetable LIKE 'room'");
if (mysqli_num_rows($result) > 0) {
    echo "\n❗ WARNING: 'room' column exists in timetable table, but is referenced in queries\n";
} else {
    echo "\n✅ 'room' column does not exist in timetable table, as expected\n";
}

echo "\nDone.\n";
?> 