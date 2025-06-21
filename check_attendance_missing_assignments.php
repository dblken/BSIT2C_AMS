<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "ATTENDANCE ENTRIES MISSING ASSIGNMENT CHECK\n";
echo "=========================================\n\n";

// Include database connection
require_once 'config/database.php';

// Step 1: Check if there are any attendance records with NULL assignment_id values
$null_check_query = "SELECT COUNT(*) as count FROM attendance WHERE assignment_id IS NULL";
$null_check_result = mysqli_query($conn, $null_check_query);
$null_count = mysqli_fetch_assoc($null_check_result)['count'];

echo "Found {$null_count} attendance records with NULL assignment_id values\n\n";

if ($null_count == 0) {
    echo "✅ All attendance records have assignment_id values set.\n";
} else {
    // Get details of records with NULL assignment_id values
    $null_details_query = "SELECT a.id, a.subject_id, a.teacher_id, a.attendance_date,
                          s.subject_code, s.subject_name,
                          CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                          FROM attendance a
                          LEFT JOIN subjects s ON a.subject_id = s.id
                          LEFT JOIN teachers t ON a.teacher_id = t.id
                          WHERE a.assignment_id IS NULL
                          LIMIT 50"; // Limit to 50 to prevent overwhelming output
    
    $null_details_result = mysqli_query($conn, $null_details_query);
    
    echo "SAMPLE OF ATTENDANCE RECORDS MISSING ASSIGNMENT_ID VALUES:\n";
    echo "--------------------------------------------------------\n";
    
    while ($row = mysqli_fetch_assoc($null_details_result)) {
        echo "- Attendance #{$row['id']}: Date: {$row['attendance_date']}\n";
        echo "  Subject: " . ($row['subject_code'] ? $row['subject_code'] . ' - ' . $row['subject_name'] : "ID: " . $row['subject_id']) . "\n";
        echo "  Teacher: " . ($row['teacher_name'] ? $row['teacher_name'] : "ID: " . $row['teacher_id']) . "\n";
        echo "\n";
    }
    
    if ($null_count > 50) {
        echo "... and " . ($null_count - 50) . " more records.\n\n";
    }
    
    echo "FIXING OPTIONS:\n";
    echo "1. You can assign appropriate assignment_id values to these records.\n";
    echo "2. If this is a problem with your schema, consider modifying it to make assignment_id optional.\n";
    echo "\nTo fix automatically, you would need to find appropriate assignments for each record based on subject_id and teacher_id.\n";
}

// Step 2: Check if there are any enrollment records without assignment_id
$enrollment_query = "SHOW TABLES LIKE 'enrollments'";
$enrollment_result = mysqli_query($conn, $enrollment_query);

if (mysqli_num_rows($enrollment_result) > 0) {
    // Check if assignment_id column exists in enrollments table
    $column_check = mysqli_query($conn, "SHOW COLUMNS FROM enrollments LIKE 'assignment_id'");
    
    if (mysqli_num_rows($column_check) > 0) {
        // Check for NULL values
        $null_enrollments_query = "SELECT COUNT(*) as count FROM enrollments WHERE assignment_id IS NULL";
        $null_enrollments_result = mysqli_query($conn, $null_enrollments_query);
        $null_enrollments_count = mysqli_fetch_assoc($null_enrollments_result)['count'];
        
        echo "\nALSO FOUND {$null_enrollments_count} enrollment records with NULL assignment_id values\n";
        
        if ($null_enrollments_count > 0) {
            echo "This could be related to the issue you're experiencing.\n";
        }
    }
}

// Step 3: Check for records where assignment exists but isn't linked to timetable
echo "\nCHECKING FOR ASSIGNMENTS WITHOUT TIMETABLE ENTRIES...\n";
$missing_timetable_query = "SELECT COUNT(*) as count
                          FROM assignments a
                          LEFT JOIN timetable t ON a.id = t.assignment_id
                          WHERE t.id IS NULL";
$missing_timetable_result = mysqli_query($conn, $missing_timetable_query);
$missing_timetable_count = mysqli_fetch_assoc($missing_timetable_result)['count'];

echo "Found {$missing_timetable_count} assignments without timetable entries\n";

if ($missing_timetable_count > 0) {
    echo "⚠️ This could cause issues with enrollment and attendance tracking.\n";
    echo "Run add_missing_timetable.php to fix this issue.\n";
}

// Step 4: Check for valid assignments that could be linked to NULL attendance records
echo "\nCHECKING FOR AVAILABLE ASSIGNMENTS TO FIX NULL RECORDS...\n";

if ($null_count > 0) {
    $available_assignments_query = "SELECT DISTINCT a.subject_id, a.teacher_id,
                                   COUNT(ass.id) as assignment_count
                                   FROM attendance a
                                   JOIN assignments ass ON a.subject_id = ass.subject_id AND a.teacher_id = ass.teacher_id
                                   WHERE a.assignment_id IS NULL
                                   GROUP BY a.subject_id, a.teacher_id";
    
    $available_assignments_result = mysqli_query($conn, $available_assignments_query);
    
    if (mysqli_num_rows($available_assignments_result) > 0) {
        echo "✅ Found matching assignments for some NULL records:\n";
        
        while ($row = mysqli_fetch_assoc($available_assignments_result)) {
            echo "- Subject ID: {$row['subject_id']}, Teacher ID: {$row['teacher_id']}: {$row['assignment_count']} potential assignments\n";
        }
        
        echo "\nYou can run fix_attendance_null_assignment.php to automatically assign these records to appropriate assignments.\n";
    } else {
        echo "❌ No matching assignments found for NULL records.\n";
        echo "You may need to create assignments first or set assignment_id to a valid value manually.\n";
    }
}

echo "\nDONE.\n";
?> 