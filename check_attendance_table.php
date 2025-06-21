<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "CHECKING ATTENDANCE TABLE STRUCTURE\n";
echo "================================\n\n";

// Include database connection
require_once 'config/database.php';

// Check attendance table structure
$query = "DESCRIBE attendance";
$result = mysqli_query($conn, $query);

if ($result) {
    echo "Attendance table columns:\n";
    echo "------------------------\n";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

echo "\nDone.\n";
?> 