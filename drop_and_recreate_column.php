<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/database.php';

echo "Dropping and recreating preferred_day column...<br>";

// First, let's try to drop the column
$drop_query = "ALTER TABLE assignments DROP COLUMN preferred_day";
$drop_result = mysqli_query($conn, $drop_query);

if ($drop_result) {
    echo "Successfully dropped the preferred_day column.<br>";
} else {
    echo "Error dropping column: " . mysqli_error($conn) . "<br>";
}

// Now recreate it with the correct type
$add_query = "ALTER TABLE assignments ADD COLUMN preferred_day TEXT NULL";
$add_result = mysqli_query($conn, $add_query);

if ($add_result) {
    echo "Successfully re-added the preferred_day column as TEXT.<br>";
} else {
    echo "Error adding column: " . mysqli_error($conn) . "<br>";
}

// Verify the column structure
$check_query = "DESCRIBE assignments preferred_day";
$check_result = mysqli_query($conn, $check_query);

if ($check_result && $row = mysqli_fetch_assoc($check_result)) {
    echo "New column type: " . $row['Type'] . "<br>";
} else {
    echo "Error verifying column: " . mysqli_error($conn) . "<br>";
}

echo "Done!";
?> 