<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully to database.\n";

// Get table structure
$result = $conn->query("DESCRIBE enrollments");

if ($result) {
    echo "Enrollments Table Structure:\n";
    echo "----------------------------\n";
    
    while ($row = $result->fetch_assoc()) {
        echo "Field: " . $row['Field'] . "\n";
        echo "Type: " . $row['Type'] . "\n";
        echo "Null: " . $row['Null'] . "\n";
        echo "Key: " . $row['Key'] . "\n";
        echo "Default: " . $row['Default'] . "\n";
        echo "Extra: " . $row['Extra'] . "\n";
        echo "----------------------------\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

$conn->close();
?> 