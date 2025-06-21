<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/database.php';

// Check attendance table structure
echo "<h1>Attendance Table Structure</h1>";

try {
    // Query to get table structure
    $result = $conn->query("SHOW COLUMNS FROM attendance");
    
    if (!$result) {
        throw new Exception("Error querying table structure: " . $conn->error);
    }
    
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] === NULL ? 'NULL' : $row['Default']) . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Check foreign keys
    echo "<h2>Foreign Key Constraints</h2>";
    
    $fk_query = "
        SELECT 
            CONSTRAINT_NAME as constraint_name,
            COLUMN_NAME as column_name,
            REFERENCED_TABLE_NAME as referenced_table,
            REFERENCED_COLUMN_NAME as referenced_column
        FROM 
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE 
            TABLE_SCHEMA = DATABASE() AND
            TABLE_NAME = 'attendance' AND
            REFERENCED_TABLE_NAME IS NOT NULL";
    
    $fk_result = $conn->query($fk_query);
    
    if (!$fk_result) {
        throw new Exception("Error querying foreign keys: " . $conn->error);
    }
    
    if ($fk_result->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>Constraint Name</th><th>Column Name</th><th>Referenced Table</th><th>Referenced Column</th></tr>";
        
        while ($row = $fk_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['constraint_name'] . "</td>";
            echo "<td>" . $row['column_name'] . "</td>";
            echo "<td>" . $row['referenced_table'] . "</td>";
            echo "<td>" . $row['referenced_column'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No foreign key constraints found on the attendance table.</p>";
    }
    
    // Check for assignment_id column
    $hasAssignmentId = false;
    
    $result = $conn->query("SHOW COLUMNS FROM attendance");
    while ($row = $result->fetch_assoc()) {
        if ($row['Field'] == 'assignment_id') {
            $hasAssignmentId = true;
            break;
        }
    }
    
    if ($hasAssignmentId) {
        echo "<div style='background-color: #dff0d8; padding: 10px; margin-top: 20px;'>";
        echo "<h3 style='color: #3c763d;'>✓ The attendance table has an assignment_id column.</h3>";
        echo "</div>";
    } else {
        echo "<div style='background-color: #f2dede; padding: 10px; margin-top: 20px;'>";
        echo "<h3 style='color: #a94442;'>✗ The attendance table does NOT have an assignment_id column!</h3>";
        echo "<p>This may be the cause of your foreign key constraint error. You need to update your database schema.</p>";
        
        echo "<h4>Recommended Solution:</h4>";
        echo "<pre style='background-color: #f8f8f8; padding: 10px;'>";
        echo "ALTER TABLE attendance ADD COLUMN assignment_id INT AFTER subject_id;\n";
        echo "ALTER TABLE attendance ADD FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE;\n";
        echo "</pre>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; font-weight: bold;'>";
    echo "Error: " . $e->getMessage();
    echo "</div>";
}
?> 