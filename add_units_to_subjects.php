<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "ADDING UNITS COLUMN TO SUBJECTS TABLE\n";
echo "=====================================\n\n";

// Include database connection
require_once 'config/database.php';

// Check if the units column already exists
$check_units = mysqli_query($conn, "SHOW COLUMNS FROM subjects LIKE 'units'");
$has_units = mysqli_num_rows($check_units) > 0;

if ($has_units) {
    echo "✅ 'units' column already exists in subjects table.\n";
} else {
    // Add the units column
    $add_column = "ALTER TABLE subjects ADD COLUMN units INT DEFAULT 3";
    
    if (mysqli_query($conn, $add_column)) {
        echo "✅ Successfully added 'units' column to subjects table with default value of 3.\n";
        
        // Update student dashboard to properly use the units column
        echo "\nNow 's.units' can be used in queries without causing errors.\n";
    } else {
        echo "❌ Failed to add 'units' column: " . mysqli_error($conn) . "\n";
    }
}

echo "\n✨ Process completed!\n";
echo "You can now safely use 's.units' in your SQL queries.\n";
?> 