<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "MODIFY ATTENDANCE TABLE TO MAKE ASSIGNMENT_ID NULLABLE\n";
echo "==================================================\n\n";

// Include database connection
require_once 'config/database.php';

// Step 1: Check the current structure of the attendance table
$check_query = "SHOW COLUMNS FROM attendance LIKE 'assignment_id'";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) == 0) {
    echo "❌ ERROR: attendance table does not have an assignment_id column!\n";
    exit;
}

$column_info = mysqli_fetch_assoc($check_result);
$is_nullable = $column_info['Null'] === 'YES';

if ($is_nullable) {
    echo "✅ assignment_id column is already nullable. No changes needed.\n";
    exit;
}

// Step 2: Check foreign key constraints
$fk_query = "SELECT 
    CONSTRAINT_NAME 
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE 
    TABLE_SCHEMA = DATABASE() AND
    TABLE_NAME = 'attendance' AND
    COLUMN_NAME = 'assignment_id' AND
    REFERENCED_TABLE_NAME IS NOT NULL";

$fk_result = mysqli_query($conn, $fk_query);
$constraint_name = null;

if (mysqli_num_rows($fk_result) > 0) {
    $constraint_name = mysqli_fetch_assoc($fk_result)['CONSTRAINT_NAME'];
    echo "Found foreign key constraint: {$constraint_name}\n";
} else {
    echo "No foreign key constraint found on assignment_id column.\n";
}

// Step 3: Confirm with the user
echo "\n⚠️ WARNING: This script will modify the database structure to make the assignment_id column nullable.\n";
echo "This is a workaround and should only be used if you cannot fix the underlying issue.\n";
echo "Type 'yes' and press Enter to proceed: ";
$handle = fopen ("php://stdin","r");
$line = trim(fgets($handle));
if($line !== 'yes'){
    echo "CANCELLED. No changes were made.\n";
    exit;
}
fclose($handle);

// Step 4: Make the changes
mysqli_begin_transaction($conn);

try {
    // Step 4.1: Drop the foreign key constraint if it exists
    if ($constraint_name) {
        $drop_fk_query = "ALTER TABLE attendance DROP FOREIGN KEY {$constraint_name}";
        echo "Dropping foreign key constraint...\n";
        
        if (!mysqli_query($conn, $drop_fk_query)) {
            throw new Exception("Failed to drop foreign key constraint: " . mysqli_error($conn));
        }
    }
    
    // Step 4.2: Modify the column to be nullable
    $modify_query = "ALTER TABLE attendance MODIFY COLUMN assignment_id INT NULL";
    echo "Modifying assignment_id column to be nullable...\n";
    
    if (!mysqli_query($conn, $modify_query)) {
        throw new Exception("Failed to modify column: " . mysqli_error($conn));
    }
    
    // Step 4.3: Re-add the foreign key constraint but with ON DELETE SET NULL
    if ($constraint_name) {
        $add_fk_query = "ALTER TABLE attendance 
                        ADD CONSTRAINT {$constraint_name} 
                        FOREIGN KEY (assignment_id) 
                        REFERENCES assignments(id) 
                        ON DELETE SET NULL";
        echo "Re-adding foreign key constraint with ON DELETE SET NULL...\n";
        
        if (!mysqli_query($conn, $add_fk_query)) {
            throw new Exception("Failed to re-add foreign key constraint: " . mysqli_error($conn));
        }
    }
    
    // Commit the transaction
    mysqli_commit($conn);
    
    echo "\n✅ Successfully modified attendance table. assignment_id is now nullable.\n";
    echo "This means attendance records can exist without being linked to an assignment.\n";
    
} catch (Exception $e) {
    // Rollback the transaction on error
    mysqli_rollback($conn);
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "All changes have been rolled back. No modifications were made.\n";
}

echo "\nDONE.\n";
?> 