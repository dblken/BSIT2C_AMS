<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "FIX ATTENDANCE ASSIGNMENT REFERENCES (DELETE APPROACH)\n";
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

// Step 2: Get the list of invalid records
$invalid_query = "SELECT a.id, a.subject_id, a.assignment_id, a.attendance_date 
                  FROM attendance a 
                  LEFT JOIN assignments ass ON a.assignment_id = ass.id 
                  WHERE a.assignment_id IS NOT NULL AND ass.id IS NULL";
$invalid_result = mysqli_query($conn, $invalid_query);

echo "INVALID RECORDS TO DELETE:\n";
echo "------------------------\n";
while ($row = mysqli_fetch_assoc($invalid_result)) {
    echo "- Attendance #{$row['id']}: assignment_id = {$row['assignment_id']}, date = {$row['attendance_date']}\n";
}

// Check if there are related attendance_records
$attendance_ids = [];
mysqli_data_seek($invalid_result, 0); // Reset result pointer
while ($row = mysqli_fetch_assoc($invalid_result)) {
    $attendance_ids[] = $row['id'];
}

if (!empty($attendance_ids)) {
    $ids_string = implode(',', $attendance_ids);
    $records_query = "SELECT COUNT(*) as count FROM attendance_records WHERE attendance_id IN ({$ids_string})";
    $records_result = mysqli_query($conn, $records_query);
    $records_count = mysqli_fetch_assoc($records_result)['count'];
    
    if ($records_count > 0) {
        echo "\n⚠️ WARNING: There are {$records_count} attendance_records linked to these attendance entries.\n";
        echo "These attendance_records will also be deleted (CASCADE).\n";
    }
}

// Ask for confirmation
echo "\n⚠️ WARNING: This script will DELETE all invalid attendance records.\n";
echo "This action cannot be undone. All related attendance_records will also be deleted.\n";
echo "Type 'DELETE' in all caps and press Enter to proceed: ";
$handle = fopen ("php://stdin","r");
$line = trim(fgets($handle));
if($line !== 'DELETE'){
    echo "CANCELLED. No changes were made.\n";
    exit;
}
fclose($handle);

// Step 3: Begin a transaction
mysqli_begin_transaction($conn);

try {
    // Delete the invalid records
    $delete_query = "DELETE a FROM attendance a
                     LEFT JOIN assignments ass ON a.assignment_id = ass.id
                     WHERE a.assignment_id IS NOT NULL AND ass.id IS NULL";
    
    $delete_result = mysqli_query($conn, $delete_query);
    
    if (!$delete_result) {
        throw new Exception("Failed to delete records: " . mysqli_error($conn));
    }
    
    $affected_rows = mysqli_affected_rows($conn);
    echo "\n✅ Successfully deleted {$affected_rows} attendance records";
    if ($records_count > 0) {
        echo " and their related attendance_records";
    }
    echo ".\n";
    
    // Commit the transaction
    mysqli_commit($conn);
    
    echo "\nAll invalid attendance records have been deleted.\n";
    echo "The foreign key constraint should now work correctly.\n";
    
} catch (Exception $e) {
    // Rollback the transaction on error
    mysqli_rollback($conn);
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "All changes have been rolled back. No deletions were made.\n";
}

echo "\nDONE.\n";
?> 