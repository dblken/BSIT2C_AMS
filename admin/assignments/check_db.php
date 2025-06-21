<?php
require_once __DIR__ . '/../../config/database.php';

// Check assignments table structure
echo "Assignments table structure:\n";
$result = mysqli_query($conn, "SHOW COLUMNS FROM assignments");
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

// Check if assignment_days table exists
$result = mysqli_query($conn, "SHOW TABLES LIKE 'assignment_days'");
if (mysqli_num_rows($result) > 0) {
    echo "\nAssignment_days table structure:\n";
    $result = mysqli_query($conn, "SHOW COLUMNS FROM assignment_days");
    while ($row = mysqli_fetch_assoc($result)) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "\nAssignment_days table does not exist.\n";
}

// Check if notifications table exists
$result = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
if (mysqli_num_rows($result) > 0) {
    echo "\nNotifications table structure:\n";
    $result = mysqli_query($conn, "SHOW COLUMNS FROM notifications");
    while ($row = mysqli_fetch_assoc($result)) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "\nNotifications table does not exist.\n";
}
?> 