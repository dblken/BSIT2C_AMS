<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "FIX ATTENDANCE NULL ASSIGNMENT_ID VALUES\n";
echo "=====================================\n\n";

// Include database connection
require_once 'config/database.php';

// Step 1: Count records with NULL assignment_id
$count_query = "SELECT COUNT(*) as count FROM attendance WHERE assignment_id IS NULL";
$count_result = mysqli_query($conn, $count_query);
$null_count = mysqli_fetch_assoc($count_result)['count'];

echo "Found {$null_count} attendance records with NULL assignment_id values\n\n";

if ($null_count == 0) {
    echo "✅ No records to fix. All attendance records have assignment_id values set.\n";
    exit;
}

// Step 2: Identify records that can be fixed automatically
// These are records where subject_id and teacher_id match an existing assignment
$fixable_query = "SELECT COUNT(*) as count
                 FROM attendance a
                 JOIN assignments ass 
                 ON a.subject_id = ass.subject_id AND a.teacher_id = ass.teacher_id
                 WHERE a.assignment_id IS NULL";
$fixable_result = mysqli_query($conn, $fixable_query);
$fixable_count = mysqli_fetch_assoc($fixable_result)['count'];

echo "Of these, {$fixable_count} records can be automatically fixed because matching assignments exist.\n";

if ($fixable_count == 0) {
    echo "❌ No records can be automatically fixed. No matching assignments found.\n";
    echo "You need to create appropriate assignments first or fix the records manually.\n";
    exit;
}

// Step 3: Get details about fixable records
$details_query = "SELECT a.id, a.subject_id, a.teacher_id, a.attendance_date,
                 MIN(ass.id) as assignment_id,
                 s.subject_code, s.subject_name,
                 CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                 FROM attendance a
                 JOIN assignments ass ON a.subject_id = ass.subject_id AND a.teacher_id = ass.teacher_id
                 LEFT JOIN subjects s ON a.subject_id = s.id
                 LEFT JOIN teachers t ON a.teacher_id = t.id
                 WHERE a.assignment_id IS NULL
                 GROUP BY a.id, a.subject_id, a.teacher_id, a.attendance_date, s.subject_code, s.subject_name, teacher_name
                 LIMIT 50";
                 
$details_result = mysqli_query($conn, $details_query);

echo "\nSAMPLE OF RECORDS TO FIX:\n";
echo "----------------------\n";
while ($row = mysqli_fetch_assoc($details_result)) {
    echo "- Attendance #{$row['id']}: Date: {$row['attendance_date']}\n";
    echo "  Subject: " . ($row['subject_code'] ? $row['subject_code'] . ' - ' . $row['subject_name'] : "ID: " . $row['subject_id']) . "\n";
    echo "  Teacher: " . ($row['teacher_name'] ? $row['teacher_name'] : "ID: " . $row['teacher_id']) . "\n";
    echo "  Will be linked to Assignment ID: {$row['assignment_id']}\n\n";
}

if ($fixable_count > 50) {
    echo "... and " . ($fixable_count - 50) . " more records.\n\n";
}

// Ask for confirmation
echo "This script will update the attendance records with appropriate assignment_id values.\n";
echo "Type 'yes' and press Enter to proceed: ";
$handle = fopen ("php://stdin","r");
$line = trim(fgets($handle));
if($line !== 'yes'){
    echo "CANCELLED. No changes were made.\n";
    exit;
}
fclose($handle);

// Step 4: Begin a transaction
mysqli_begin_transaction($conn);

try {
    // Update records with matching assignments
    $update_query = "UPDATE attendance a
                    JOIN (
                        SELECT a.id, MIN(ass.id) as assignment_id
                        FROM attendance a
                        JOIN assignments ass ON a.subject_id = ass.subject_id AND a.teacher_id = ass.teacher_id
                        WHERE a.assignment_id IS NULL
                        GROUP BY a.id
                    ) AS matches ON a.id = matches.id
                    SET a.assignment_id = matches.assignment_id";
    
    $update_result = mysqli_query($conn, $update_query);
    
    if (!$update_result) {
        throw new Exception("Failed to update records: " . mysqli_error($conn));
    }
    
    $affected_rows = mysqli_affected_rows($conn);
    echo "\n✅ Successfully updated {$affected_rows} records with assignment_id values.\n";
    
    // Check if there are still records with NULL assignment_id
    $remaining_query = "SELECT COUNT(*) as count FROM attendance WHERE assignment_id IS NULL";
    $remaining_result = mysqli_query($conn, $remaining_query);
    $remaining_count = mysqli_fetch_assoc($remaining_result)['count'];
    
    if ($remaining_count > 0) {
        echo "\n⚠️ There are still {$remaining_count} records with NULL assignment_id values.\n";
        echo "These records could not be automatically fixed because no matching assignments were found.\n";
        echo "You may need to create appropriate assignments or fix these records manually.\n";
    }
    
    // Commit the transaction
    mysqli_commit($conn);
    
} catch (Exception $e) {
    // Rollback the transaction on error
    mysqli_rollback($conn);
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "All changes have been rolled back. No updates were made.\n";
}

echo "\nDONE.\n";
?> 