<?php
require_once dirname(__FILE__) . '/../../config/database.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if birthday column exists
$check_birthday_column = mysqli_query($conn, "SHOW COLUMNS FROM teachers LIKE 'birthday'");
if (mysqli_num_rows($check_birthday_column) == 0) {
    // Add birthday column if it doesn't exist
    $query = "ALTER TABLE teachers ADD COLUMN birthday DATE NULL";
    if (mysqli_query($conn, $query)) {
        echo "Birthday column added successfully!";
    } else {
        echo "Error adding birthday column: " . mysqli_error($conn);
    }
} else {
    echo "Birthday column already exists.";
}
?> 