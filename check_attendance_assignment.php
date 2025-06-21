<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "ATTENDANCE ASSIGNMENT FOREIGN KEY CONSTRAINT CHECKER\n";
echo "=================================================\n\n";

// Include database connection
require_once 'config/database.php';

// Step 1: Check attendance table structure and foreign keys
echo "CHECKING ATTENDANCE TABLE STRUCTURE...\n";
$attendance_columns = mysqli_query($conn, "DESCRIBE attendance");
$has_assignment_id = false;

if (mysqli_num_rows($attendance_columns) > 0) {
    echo "Attendance table columns:\n";
    while ($col = mysqli_fetch_assoc($attendance_columns)) {
        echo "- {$col['Field']} ({$col['Type']})\n";
        if ($col['Field'] === 'assignment_id') {
            $has_assignment_id = true;
        }
    }
} else {
    echo "❌ Could not fetch attendance table structure.\n";
    exit;
}

if (!$has_assignment_id) {
    echo "\n❌ ERROR: attendance table is missing assignment_id column!\n";
    echo "This needs to be added for proper foreign key functionality.\n";
} else {
    echo "\n✅ Attendance table has assignment_id column.\n";
}

// Step 2: Check for the foreign key constraint
echo "\nCHECKING FOREIGN KEY CONSTRAINTS...\n";
$fk_query = "SELECT 
    COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE 
    REFERENCED_TABLE_NAME IS NOT NULL 
    AND TABLE_NAME = 'attendance'";

$fk_result = mysqli_query($conn, $fk_query);

$has_assignment_fk = false;
if (mysqli_num_rows($fk_result) > 0) {
    echo "Foreign key constraints on attendance table:\n";
    while ($fk = mysqli_fetch_assoc($fk_result)) {
        echo "- {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}({$fk['REFERENCED_COLUMN_NAME']}) [{$fk['CONSTRAINT_NAME']}]\n";
        if ($fk['COLUMN_NAME'] === 'assignment_id' && $fk['REFERENCED_TABLE_NAME'] === 'assignments') {
            $has_assignment_fk = true;
        }
    }
} else {
    echo "No foreign key constraints found on attendance table.\n";
}

if (!$has_assignment_fk) {
    echo "\n❌ ERROR: No foreign key constraint found linking attendance.assignment_id to assignments.id\n";
} else {
    echo "\n✅ Foreign key constraint exists for assignment_id.\n";
}

// Step 3: Check for invalid data
echo "\nCHECKING FOR INVALID ASSIGNMENT_ID VALUES...\n";
$invalid_query = "SELECT a.id, a.subject_id, a.assignment_id, a.attendance_date 
                  FROM attendance a 
                  LEFT JOIN assignments ass ON a.assignment_id = ass.id 
                  WHERE a.assignment_id IS NOT NULL AND ass.id IS NULL";

$invalid_result = mysqli_query($conn, $invalid_query);
$invalid_count = mysqli_num_rows($invalid_result);

if ($invalid_count > 0) {
    echo "❌ Found {$invalid_count} attendance records with invalid assignment_id values:\n";
    while ($row = mysqli_fetch_assoc($invalid_result)) {
        echo "- Attendance #{$row['id']}: assignment_id = {$row['assignment_id']}, date = {$row['attendance_date']}\n";
    }
    
    echo "\nWould you like to fix these records? They can be:\n";
    echo "1. Set assignment_id to NULL (safest option if you don't need assignment data)\n";
    echo "2. Deleted (will remove the attendance records entirely)\n";
    echo "3. Create missing assignment entries (if you have sufficient data)\n";
    
    echo "\nPlease run one of the following prepared fix scripts:\n";
    echo "- php fix_attendance_assignment_null.php (Set invalid assignment_id to NULL)\n";
    echo "- php fix_attendance_assignment_delete.php (Delete invalid records)\n";
    echo "- php fix_attendance_assignment_create.php (Create missing assignments)\n";
} else {
    echo "✅ No invalid assignment_id values found in attendance records.\n";
}

// Step 4: Check for NULL assignment_id values when they should be set
echo "\nCHECKING FOR NULL ASSIGNMENT_ID VALUES...\n";
$null_query = "SELECT COUNT(*) as count FROM attendance WHERE assignment_id IS NULL";
$null_result = mysqli_query($conn, $null_query);
$null_count = mysqli_fetch_assoc($null_result)['count'];

if ($null_count > 0) {
    echo "ℹ️ Found {$null_count} attendance records with NULL assignment_id values.\n";
    echo "This might be normal if not all attendance records need to be linked to assignments.\n";
} else {
    echo "✅ All attendance records have assignment_id values set.\n";
}

// Step 5: Check assignments table
echo "\nCHECKING ASSIGNMENTS TABLE...\n";
$assignments_query = "SELECT COUNT(*) as count FROM assignments";
$assignments_result = mysqli_query($conn, $assignments_query);
$assignments_count = mysqli_fetch_assoc($assignments_result)['count'];

echo "Total assignments available: {$assignments_count}\n";

if ($assignments_count == 0) {
    echo "❌ WARNING: No assignments exist in the system. This might cause foreign key constraint issues when creating attendance records.\n";
}

echo "\nSUMMARY:\n";
echo "=======\n";
if ($invalid_count > 0) {
    echo "❌ Found {$invalid_count} attendance records with invalid assignment_id values that need to be fixed.\n";
    echo "Please run one of the fix scripts mentioned above.\n";
} else {
    echo "✅ Database appears to be in good condition regarding attendance-assignment relationship.\n";
}

echo "\nDONE.\n";
?> 