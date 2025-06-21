<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "ASSIGNMENT AND TIMETABLE DIAGNOSTIC\n";
echo "=================================\n\n";

// Include database connection
require_once 'config/database.php';

// Check if assignments table exists
$check_assignments = mysqli_query($conn, "SHOW TABLES LIKE 'assignments'");
if (mysqli_num_rows($check_assignments) == 0) {
    echo "❌ Assignments table does not exist!\n";
    exit;
}

// Check if timetable exists
$check_timetable = mysqli_query($conn, "SHOW TABLES LIKE 'timetable'");
if (mysqli_num_rows($check_timetable) == 0) {
    echo "❌ Timetable table does not exist!\n";
    exit;
}

// Check assignments table structure
echo "Assignments table structure:\n";
$result = mysqli_query($conn, "DESCRIBE assignments");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

echo "\nTimetable table structure:\n";
$result = mysqli_query($conn, "DESCRIBE timetable");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

// Check if assignment_id column exists in timetable
$check_timetable_assignment = mysqli_query($conn, "SHOW COLUMNS FROM timetable LIKE 'assignment_id'");
if (mysqli_num_rows($check_timetable_assignment) == 0) {
    echo "\n❗ WARNING: timetable table does not have assignment_id column!\n";
    echo "This is required for the enrollment process to work correctly.\n";
} else {
    echo "\n✅ timetable has assignment_id column\n";
}

// Count assignments and related timetable entries
echo "\nStatistics:\n";

$assignment_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM assignments");
$assignments = mysqli_fetch_assoc($assignment_count)['count'];
echo "- Total assignments: {$assignments}\n";

$timetable_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM timetable");
$timetables = mysqli_fetch_assoc($timetable_count)['count'];
echo "- Total timetable entries: {$timetables}\n";

if (mysqli_num_rows($check_timetable_assignment) > 0) {
    $linked_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM timetable WHERE assignment_id IS NOT NULL");
    $linked = mysqli_fetch_assoc($linked_count)['count'];
    echo "- Timetable entries linked to assignments: {$linked}\n";
    
    if ($linked < $assignments) {
        echo "⚠️ Not all assignments have timetable entries!\n";
    }
}

// Check if there are any assignments available for enrollment
echo "\nSample assignments available for enrollment:\n";
$sample_assignments = mysqli_query($conn, "
    SELECT a.id, s.subject_code, s.subject_name, 
           CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
           a.preferred_day, a.time_start, a.time_end, a.location
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN teachers t ON a.teacher_id = t.id
    LIMIT 5
");

if (mysqli_num_rows($sample_assignments) > 0) {
    $i = 1;
    while ($row = mysqli_fetch_assoc($sample_assignments)) {
        echo "\nAssignment #{$i}:\n";
        echo "- ID: {$row['id']}\n";
        echo "- Subject: {$row['subject_code']} - {$row['subject_name']}\n";
        echo "- Teacher: {$row['teacher_name']}\n";
        echo "- Day: {$row['preferred_day']}\n";
        echo "- Time: {$row['time_start']} - {$row['time_end']}\n";
        echo "- Location: {$row['location']}\n";
        
        // Check if this assignment has a timetable entry
        if (mysqli_num_rows($check_timetable_assignment) > 0) {
            $timetable_check = mysqli_query($conn, "SELECT id FROM timetable WHERE assignment_id = {$row['id']}");
            if (mysqli_num_rows($timetable_check) > 0) {
                echo "- ✅ Has timetable entry\n";
            } else {
                echo "- ❌ No timetable entry found\n";
            }
        }
        
        $i++;
    }
} else {
    echo "No assignments found.\n";
}

echo "\nDone.\n";
?> 