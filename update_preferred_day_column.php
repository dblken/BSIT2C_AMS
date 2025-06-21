<?php
require_once 'config/database.php';

echo "Updating preferred_day column type...<br>";

// Get all keys on assignments table
$keys_query = "SHOW KEYS FROM assignments WHERE Column_name = 'preferred_day'";
$keys_result = mysqli_query($conn, $keys_query);

if ($keys_result) {
    while ($key = mysqli_fetch_assoc($keys_result)) {
        if ($key['Key_name'] != 'PRIMARY') {
            echo "Found key: " . $key['Key_name'] . "<br>";
            
            // Drop the key
            $drop_key_query = "ALTER TABLE assignments DROP INDEX `{$key['Key_name']}`";
            if (mysqli_query($conn, $drop_key_query)) {
                echo "Dropped index: " . $key['Key_name'] . "<br>";
            } else {
                echo "Error dropping index: " . mysqli_error($conn) . "<br>";
            }
        }
    }
}

// Check if there's a foreign key constraint
$fk_query = "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'assignments' 
             AND COLUMN_NAME = 'preferred_day' 
             AND REFERENCED_TABLE_NAME IS NOT NULL";
$fk_result = mysqli_query($conn, $fk_query);

if ($fk_result && mysqli_num_rows($fk_result) > 0) {
    while ($fk = mysqli_fetch_assoc($fk_result)) {
        echo "Found foreign key: " . $fk['CONSTRAINT_NAME'] . "<br>";
        
        // Drop the foreign key
        $drop_fk_query = "ALTER TABLE assignments DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`";
        if (mysqli_query($conn, $drop_fk_query)) {
            echo "Dropped foreign key: " . $fk['CONSTRAINT_NAME'] . "<br>";
        } else {
            echo "Error dropping foreign key: " . mysqli_error($conn) . "<br>";
        }
    }
}

// Now alter the column type
$alter_query = "ALTER TABLE assignments MODIFY preferred_day TEXT NULL";
$result = mysqli_query($conn, $alter_query);

if ($result) {
    echo "Column type successfully updated to TEXT.<br>";
} else {
    echo "Error updating column type: " . mysqli_error($conn) . "<br>";
}

echo "Done!";
?> 