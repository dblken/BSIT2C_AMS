<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "FIX ATTENDANCE ASSIGNMENT REFERENCES (NULL APPROACH)\n";
echo "=================================================\n\n";

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

// Step 2: Get the list of invalid records
$invalid_query = "SELECT a.id, a.subject_id, a.assignment_id, a.attendance_date 
                  FROM attendance a 
                  LEFT JOIN assignments ass ON a.assignment_id = ass.id 
                  WHERE a.assignment_id IS NOT NULL AND ass.id IS NULL";
$invalid_result = mysqli_query($conn, $invalid_query);

echo "INVALID RECORDS TO FIX:\n";
echo "---------------------\n";
while ($row = mysqli_fetch_assoc($invalid_result)) {
    echo "- Attendance #{$row['id']}: assignment_id = {$row['assignment_id']}, date = {$row['attendance_date']}\n";
}

// Ask for confirmation
echo "\nThis script will set assignment_id to NULL for all invalid records.\n";
echo "This is a safe approach but will disconnect these attendance records from assignments.\n";
echo "Type 'yes' and press Enter to proceed: ";
$handle = fopen ("php://stdin","r");
$line = trim(fgets($handle));
if($line !== 'yes'){
    echo "CANCELLED. No changes were made.\n";
    exit;
}
fclose($handle);

// Step 3: Begin a transaction
mysqli_begin_transaction($conn);

try {
    // Update the invalid records, setting assignment_id to NULL
    $update_query = "UPDATE attendance a
                     LEFT JOIN assignments ass ON a.assignment_id = ass.id
                     SET a.assignment_id = NULL
                     WHERE a.assignment_id IS NOT NULL AND ass.id IS NULL";
    
    $update_result = mysqli_query($conn, $update_query);
    
    if (!$update_result) {
        throw new Exception("Failed to update records: " . mysqli_error($conn));
    }
    
    $affected_rows = mysqli_affected_rows($conn);
    echo "\n✅ Successfully updated {$affected_rows} records.\n";
    
    // Commit the transaction
    mysqli_commit($conn);
    
    echo "\nAll invalid assignment_id values have been set to NULL.\n";
    echo "The foreign key constraint should now work correctly.\n";
    
} catch (Exception $e) {
    // Rollback the transaction on error
    mysqli_rollback($conn);
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "All changes have been rolled back. No updates were made.\n";
}

echo "\nDONE.\n";
?> 