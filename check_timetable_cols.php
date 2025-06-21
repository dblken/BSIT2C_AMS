<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/database.php';

echo "<h2>Timetable Table Structure</h2>";
if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error;
} else {
    echo "Connected successfully to database.<br>";
    $query = "SHOW COLUMNS FROM timetable";
    $result = $conn->query($query);
    if (!$result) {
        echo "Error: " . $conn->error;
    } else {
        echo "Found " . $result->num_rows . " columns:<br>";
        echo "<pre>";
        while($row = $result->fetch_assoc()) {
            echo $row['Field'] . " | " . $row['Type'] . "\n";
        }
        echo "</pre>";
    }
}
?> 