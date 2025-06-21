<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/database.php';

echo "Database Tables:<br>";
$tables_result = $conn->query("SHOW TABLES");
while ($table = $tables_result->fetch_array()) {
    echo "- " . $table[0] . "<br>";
}
echo "<hr>";

// Check attendance table structure
echo "Attendance Table Structure:<br>";
$att_result = $conn->query("DESCRIBE attendance");
if ($att_result) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $att_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table><hr>";
} else {
    echo "Error: Could not get attendance table structure.<br><hr>";
}

// Check foreign key constraints for attendance table
echo "Attendance Table Foreign Keys:<br>";
$fk_query = "SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME, 
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME 
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE 
    REFERENCED_TABLE_NAME IS NOT NULL 
    AND TABLE_NAME = 'attendance'";

$fk_result = $conn->query($fk_query);
if ($fk_result && $fk_result->num_rows > 0) {
    echo "<table border='1'><tr><th>Table</th><th>Column</th><th>Constraint</th><th>Referenced Table</th><th>Referenced Column</th></tr>";
    while ($row = $fk_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['TABLE_NAME'] . "</td>";
        echo "<td>" . $row['COLUMN_NAME'] . "</td>";
        echo "<td>" . $row['CONSTRAINT_NAME'] . "</td>";
        echo "<td>" . $row['REFERENCED_TABLE_NAME'] . "</td>";
        echo "<td>" . $row['REFERENCED_COLUMN_NAME'] . "</td>";
        echo "</tr>";
    }
    echo "</table><hr>";
} else {
    echo "No foreign key constraints found for attendance table or error in query.<br><hr>";
}

// Check assignments table and count records
echo "Assignments Table:<br>";
$count_result = $conn->query("SELECT COUNT(*) as count FROM assignments");
if ($count_result) {
    $count = $count_result->fetch_assoc()['count'];
    echo "Total assignments: " . $count . "<br><hr>";
} else {
    echo "Error: Could not count assignments.<br><hr>";
}

// Get some sample assignments if they exist
if ($count > 0) {
    echo "Sample Assignments:<br>";
    $sample_result = $conn->query("SELECT * FROM assignments LIMIT 5");
    if ($sample_result) {
        echo "<table border='1'><tr>";
        while ($field = $sample_result->fetch_field()) {
            echo "<th>" . $field->name . "</th>";
        }
        echo "</tr>";
        
        while ($row = $sample_result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . $value . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "Error: Could not fetch sample assignments.<br>";
    }
}
?> 