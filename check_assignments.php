<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "ASSIGNMENTS AND TIMETABLE ENTRIES CHECK\n";
echo "====================================\n\n";

// Include database connection
require_once 'config/database.php';

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$tableName'");
    return mysqli_num_rows($result) > 0;
}

// Check if assignments table exists
if (!tableExists($conn, 'assignments')) {
    echo "❌ ERROR: The assignments table does not exist in the database.\n";
    exit;
}

// Get all assignments
$query = "SELECT 
    a.id as assignment_id,
    a.subject_id,
    a.teacher_id,
    s.subject_code,
    s.subject_name,
    CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
    a.preferred_day,
    a.time_start,
    a.time_end,
    a.location
FROM 
    assignments a
JOIN 
    subjects s ON a.subject_id = s.id
JOIN 
    teachers t ON a.teacher_id = t.id
ORDER BY 
    a.id";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo "❌ ERROR: " . mysqli_error($conn) . "\n";
    exit;
}

$assignments_count = mysqli_num_rows($result);
echo "Total assignments: $assignments_count\n\n";

if ($assignments_count > 0) {
    echo "ASSIGNMENT DETAILS:\n";
    echo "==================\n";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo "Assignment ID: {$row['assignment_id']}\n";
        echo "Subject: {$row['subject_code']} - {$row['subject_name']} (ID: {$row['subject_id']})\n";
        echo "Teacher: {$row['teacher_name']} (ID: {$row['teacher_id']})\n";
        echo "Schedule: {$row['preferred_day']}, {$row['time_start']} - {$row['time_end']}\n";
        echo "Location: {$row['location']}\n";
        
        // Check if this assignment has timetable entries
        $timetable_query = "SELECT id, day FROM timetable WHERE assignment_id = {$row['assignment_id']}";
        $timetable_result = mysqli_query($conn, $timetable_query);
        
        if (mysqli_num_rows($timetable_result) > 0) {
            echo "Timetable Entries: " . mysqli_num_rows($timetable_result) . "\n";
            while ($timetable = mysqli_fetch_assoc($timetable_result)) {
                echo " - TT ID: {$timetable['id']}, Day: {$timetable['day']}\n";
            }
        } else {
            echo "❌ No timetable entries found for this assignment!\n";
        }
        
        echo "\n-----------------------------\n\n";
    }
} else {
    echo "No assignments found in the database.\n";
}

// Check for assignments used in attendance
echo "ASSIGNMENTS USED IN ATTENDANCE RECORDS:\n";
echo "====================================\n";

if (tableExists($conn, 'attendance')) {
    $att_query = "SELECT 
        DISTINCT a.assignment_id,
        COUNT(*) as record_count,
        MAX(a.attendance_date) as latest_date
    FROM 
        attendance a
    GROUP BY 
        a.assignment_id";
    
    $att_result = mysqli_query($conn, $att_query);
    
    if (mysqli_num_rows($att_result) > 0) {
        echo "Found " . mysqli_num_rows($att_result) . " assignment IDs used in attendance records:\n\n";
        
        while ($att = mysqli_fetch_assoc($att_result)) {
            $assignment_check = mysqli_query($conn, "SELECT id FROM assignments WHERE id = {$att['assignment_id']}");
            
            echo "Assignment ID: {$att['assignment_id']}\n";
            echo "Record Count: {$att['record_count']}\n";
            echo "Latest Date: {$att['latest_date']}\n";
            
            if (mysqli_num_rows($assignment_check) > 0) {
                echo "✅ Assignment exists\n";
            } else {
                echo "❌ Assignment does not exist in assignments table!\n";
            }
            
            echo "\n";
        }
    } else {
        echo "No attendance records with assignment IDs found.\n";
    }
} else {
    echo "Attendance table does not exist.\n";
}

echo "\nDONE.\n";
?> 