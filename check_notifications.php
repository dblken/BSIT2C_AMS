<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "CHECKING NOTIFICATIONS TABLE STRUCTURE\n";
echo "====================================\n\n";

// Include database connection
require_once 'config/database.php';

// Check if the notifications table exists
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
if (mysqli_num_rows($check_table) == 0) {
    echo "❌ Notifications table does not exist!\n";
    exit;
}

// Get the structure of the notifications table
echo "Notifications table structure:\n";
$result = mysqli_query($conn, "DESCRIBE notifications");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

// Check if the table has any data
echo "\nSample data from notifications table:\n";
$data = mysqli_query($conn, "SELECT * FROM notifications LIMIT 3");
if (mysqli_num_rows($data) > 0) {
    $i = 1;
    while ($row = mysqli_fetch_assoc($data)) {
        echo "\nNotification #$i:\n";
        foreach ($row as $key => $value) {
            echo "- $key: $value\n";
        }
        $i++;
    }
} else {
    echo "No data found in notifications table.\n";
}

echo "\nDone.\n";
?> 