<?php
// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/database.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get database name
$dbname_query = "SELECT DATABASE()";
$result = $conn->query($dbname_query);
$row = $result->fetch_row();
$dbname = $row[0];

echo "Connected to database: " . $dbname . "<br>";

// Show tables
$tables_query = "SHOW TABLES";
$result = $conn->query($tables_query);

if ($result) {
    if ($result->num_rows > 0) {
        echo "<h2>Tables in database:</h2>";
        echo "<ul>";
        while ($row = $result->fetch_row()) {
            echo "<li>" . $row[0] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "No tables found in the database.";
    }
} else {
    echo "Error executing query: " . $conn->error;
}

// Check assignments table specifically
echo "<h2>Checking assignments table:</h2>";
$check_query = "SHOW TABLES LIKE 'assignments'";
$result = $conn->query($check_query);

if ($result && $result->num_rows > 0) {
    echo "assignments table exists.<br>";
    
    // Count records
    $count_query = "SELECT COUNT(*) as cnt FROM assignments";
    $result = $conn->query($count_query);
    if ($result) {
        $row = $result->fetch_assoc();
        echo "Number of records: " . $row['cnt'] . "<br>";
    } else {
        echo "Error counting records: " . $conn->error . "<br>";
    }
} else {
    echo "assignments table does not exist.<br>";
}

$conn->close();
?> 