<?php
require_once '../../config/database.php';

// Read the SQL file
$sql = file_get_contents('create_logs_table.sql');

try {
    // Execute the SQL commands
    if ($conn->multi_query($sql)) {
        echo "Table 'attendance_logs' has been recreated successfully!";
    } else {
        echo "Error executing SQL: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?> 