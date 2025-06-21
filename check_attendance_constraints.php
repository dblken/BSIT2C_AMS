<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/database.php';

echo "<h1>Attendance System Constraint Check</h1>";

// Check attendance table structure
echo "<h2>Attendance Table Structure</h2>";
$result = $conn->query("DESCRIBE attendance");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

// Check assignments table structure
echo "<h2>Assignments Table Structure</h2>";
$result = $conn->query("DESCRIBE assignments");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

// Check foreign key constraints
echo "<h2>Foreign Key Constraints</h2>";
$sql = "SELECT
    TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
    REFERENCED_TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'attendance'";

$result = $conn->query($sql);
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Table</th><th>Column</th><th>Constraint</th><th>Referenced Table</th><th>Referenced Column</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['TABLE_NAME'] . "</td>";
        echo "<td>" . $row['COLUMN_NAME'] . "</td>";
        echo "<td>" . $row['CONSTRAINT_NAME'] . "</td>";
        echo "<td>" . $row['REFERENCED_TABLE_NAME'] . "</td>";
        echo "<td>" . $row['REFERENCED_COLUMN_NAME'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

// Check for invalid assignments in attendance table
echo "<h2>Invalid Assignment IDs in Attendance Table</h2>";
$sql = "SELECT a.id, a.teacher_id, a.subject_id, a.assignment_id, a.attendance_date
       FROM attendance a
       LEFT JOIN assignments ass ON a.assignment_id = ass.id
       WHERE ass.id IS NULL";

$result = $conn->query($sql);
if ($result) {
    if ($result->num_rows > 0) {
        echo "<p>Found " . $result->num_rows . " records with invalid assignment IDs:</p>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Teacher ID</th><th>Subject ID</th><th>Assignment ID</th><th>Date</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['teacher_id'] . "</td>";
            echo "<td>" . $row['subject_id'] . "</td>";
            echo "<td>" . $row['assignment_id'] . "</td>";
            echo "<td>" . $row['attendance_date'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No invalid assignment IDs found in attendance table.</p>";
    }
} else {
    echo "Error: " . $conn->error;
}

// Display counts
echo "<h2>Record Counts</h2>";
$tables = ['assignments', 'attendance', 'attendance_records'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>$table: " . $row['count'] . " records</p>";
    }
}

// Sample of records from assignments
echo "<h2>Sample Assignments</h2>";
$result = $conn->query("SELECT * FROM assignments LIMIT 5");
if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1'>";
        // Headers
        $firstRow = $result->fetch_assoc();
        echo "<tr>";
        foreach (array_keys($firstRow) as $key) {
            echo "<th>$key</th>";
        }
        echo "</tr>";
        
        // Reset pointer and show rows
        $result->data_seek(0);
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . (is_null($value) ? "NULL" : htmlspecialchars($value)) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No assignments found.</p>";
    }
} else {
    echo "Error: " . $conn->error;
}
?> 