<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "FIX ATTENDANCE ASSIGNMENT REFERENCES (CREATE APPROACH)\n";
echo "===================================================\n\n";

// Include database connection
require_once 'config/database.php';

// Step 1: Count invalid assignment_id values
$count_query = "SELECT COUNT(*) as count 
                FROM attendance a 
                LEFT JOIN assignments ass ON a.assignment_id = ass.id 
                WHERE a.assignment_id IS NOT NULL AND ass.id IS NULL";
$count_result = mysqli_query($conn, $count_query);
$invalid_count = mysqli_fetch_assoc($count_result)['count'];

echo "Found {$invalid_count} attendance records with invalid assignment_id values\n\n";

if ($invalid_count == 0) {
    echo "✅ No invalid records to fix. Everything is in order.\n";
    exit;
}

// Step 2: Get the list of invalid records with subject and teacher information if available
$invalid_query = "SELECT a.id, a.subject_id, a.teacher_id, a.assignment_id, a.attendance_date,
                  s.subject_code, s.subject_name,
                  CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                  FROM attendance a 
                  LEFT JOIN assignments ass ON a.assignment_id = ass.id 
                  LEFT JOIN subjects s ON a.subject_id = s.id
                  LEFT JOIN teachers t ON a.teacher_id = t.id
                  WHERE a.assignment_id IS NOT NULL AND ass.id IS NULL";
$invalid_result = mysqli_query($conn, $invalid_query);

echo "INVALID RECORDS TO FIX:\n";
echo "---------------------\n";

$can_create = true;
$missing_data = [];

while ($row = mysqli_fetch_assoc($invalid_result)) {
    echo "- Attendance #{$row['id']}: assignment_id = {$row['assignment_id']}, date = {$row['attendance_date']}\n";
    
    // Check if we have subject_id and teacher_id to create assignments
    if (empty($row['subject_id']) || empty($row['teacher_id'])) {
        $can_create = false;
        $missing_data[] = "Attendance #{$row['id']}: " . 
                         (empty($row['subject_id']) ? "missing subject_id" : "") .
                         (empty($row['subject_id']) && empty($row['teacher_id']) ? " and " : "") .
                         (empty($row['teacher_id']) ? "missing teacher_id" : "");
    } else {
        echo "  - Subject: " . ($row['subject_code'] ? $row['subject_code'] . " - " . $row['subject_name'] : "ID: " . $row['subject_id']) . "\n";
        echo "  - Teacher: " . ($row['teacher_name'] ? $row['teacher_name'] : "ID: " . $row['teacher_id']) . "\n";
    }
    echo "\n";
}

if (!$can_create) {
    echo "\n❌ ERROR: Cannot create assignments due to missing data:\n";
    foreach ($missing_data as $error) {
        echo "- " . $error . "\n";
    }
    echo "\nPlease use one of the other fix scripts (NULL or DELETE) or fix the data manually.\n";
    exit;
}

// Ask for confirmation
echo "\nℹ️ This script will CREATE new assignment entries for invalid assignment_id values.\n";
echo "This approach will preserve the relationship but requires that the subject_id and teacher_id are valid.\n";
echo "Type 'CREATE' in all caps and press Enter to proceed: ";
$handle = fopen ("php://stdin","r");
$line = trim(fgets($handle));
if($line !== 'CREATE'){
    echo "CANCELLED. No changes were made.\n";
    exit;
}
fclose($handle);

// Step 3: Begin a transaction
mysqli_begin_transaction($conn);

try {
    $created_count = 0;
    mysqli_data_seek($invalid_result, 0); // Reset result pointer
    
    // For each invalid assignment_id, create a new assignment
    while ($row = mysqli_fetch_assoc($invalid_result)) {
        $subject_id = $row['subject_id'];
        $teacher_id = $row['teacher_id'];
        $attendance_id = $row['id'];
        $invalid_assignment_id = $row['assignment_id'];
        
        // Get more detailed information about the subject and teacher
        $subject_query = "SELECT subject_code, subject_name FROM subjects WHERE id = ?";
        $subject_stmt = mysqli_prepare($conn, $subject_query);
        mysqli_stmt_bind_param($subject_stmt, "i", $subject_id);
        mysqli_stmt_execute($subject_stmt);
        $subject_result = mysqli_stmt_get_result($subject_stmt);
        $subject = mysqli_fetch_assoc($subject_result);
        
        // Create new assignment
        $assignment_query = "INSERT INTO assignments (
                            id, teacher_id, subject_id, month_from, month_to, 
                            preferred_day, time_start, time_end, location, created_at
                           ) VALUES (
                            ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH),
                            JSON_ARRAY('Monday', 'Wednesday'), '08:00:00', '10:00:00', 'TBA', NOW()
                           )";
        
        $assignment_stmt = mysqli_prepare($conn, $assignment_query);
        mysqli_stmt_bind_param($assignment_stmt, "iii", $invalid_assignment_id, $teacher_id, $subject_id);
        
        if (!mysqli_stmt_execute($assignment_stmt)) {
            // If we can't insert with the specific ID, try without specifying ID
            $assignment_query = "INSERT INTO assignments (
                                teacher_id, subject_id, month_from, month_to, 
                                preferred_day, time_start, time_end, location, created_at
                               ) VALUES (
                                ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH),
                                JSON_ARRAY('Monday', 'Wednesday'), '08:00:00', '10:00:00', 'TBA', NOW()
                               )";
            
            $assignment_stmt = mysqli_prepare($conn, $assignment_query);
            mysqli_stmt_bind_param($assignment_stmt, "ii", $teacher_id, $subject_id);
            
            if (!mysqli_stmt_execute($assignment_stmt)) {
                throw new Exception("Failed to create assignment: " . mysqli_error($conn));
            }
            
            $new_assignment_id = mysqli_insert_id($conn);
            
            // Update the attendance record with the new assignment_id
            $update_query = "UPDATE attendance SET assignment_id = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "ii", $new_assignment_id, $attendance_id);
            
            if (!mysqli_stmt_execute($update_stmt)) {
                throw new Exception("Failed to update attendance record: " . mysqli_error($conn));
            }
        }
        
        $created_count++;
        
        // Create timetable entry for this assignment
        $timetable_query = "INSERT INTO timetable (
                          subject_id, assignment_id, day, time_start, time_end, created_at
                         ) VALUES (
                          ?, ?, 1, '08:00:00', '10:00:00', NOW()
                         )";
        
        $timetable_stmt = mysqli_prepare($conn, $timetable_query);
        $assignment_id_to_use = isset($new_assignment_id) ? $new_assignment_id : $invalid_assignment_id;
        mysqli_stmt_bind_param($timetable_stmt, "ii", $subject_id, $assignment_id_to_use);
        
        if (!mysqli_stmt_execute($timetable_stmt)) {
            throw new Exception("Failed to create timetable entry: " . mysqli_error($conn));
        }
    }
    
    // Commit the transaction
    mysqli_commit($conn);
    
    echo "\n✅ Successfully created {$created_count} assignments and timetable entries.\n";
    echo "The foreign key constraint should now work correctly.\n";
    
} catch (Exception $e) {
    // Rollback the transaction on error
    mysqli_rollback($conn);
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "All changes have been rolled back. No updates were made.\n";
}

echo "\nDONE.\n";
?> 