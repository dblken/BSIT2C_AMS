<?php
// Start session
session_start();
require_once '../../config/database.php';
require_once '../../includes/session_protection.php';

// Use the verify_session function to check admin session
verify_session('admin');

// Set headers for plain text
header('Content-Type: text/plain; charset=utf-8');

echo "STUDENTS TABLE DIAGNOSTIC\n";
echo "========================\n\n";

// Check if students table exists
$check_table = $conn->query("SHOW TABLES LIKE 'students'");
if ($check_table->num_rows == 0) {
    echo "ERROR: The 'students' table does not exist in the database.\n";
    exit;
}

echo "The 'students' table exists in the database.\n\n";

// Get table structure
echo "TABLE STRUCTURE:\n";
$structure = $conn->query("DESCRIBE students");
if ($structure) {
    while ($row = $structure->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . " - " . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
} else {
    echo "Error getting table structure: " . $conn->error . "\n";
}

echo "\n";

// Count records
$count = $conn->query("SELECT COUNT(*) as total FROM students");
$total = 0;
if ($count) {
    $total = $count->fetch_assoc()['total'];
    echo "TOTAL RECORDS: " . $total . "\n\n";
} else {
    echo "Error counting records: " . $conn->error . "\n\n";
}

// Sample data
if ($total > 0) {
    echo "SAMPLE DATA (first 5 records):\n";
    $sample = $conn->query("SELECT * FROM students LIMIT 5");
    if ($sample) {
        $columns = [];
        $first = true;
        
        while ($row = $sample->fetch_assoc()) {
            if ($first) {
                $columns = array_keys($row);
                echo implode("\t", $columns) . "\n";
                echo str_repeat("-", 100) . "\n";
                $first = false;
            }
            
            echo implode("\t", array_map(function($col) use ($row) {
                return $row[$col] ?? 'NULL';
            }, $columns)) . "\n";
        }
    } else {
        echo "Error getting sample data: " . $conn->error . "\n";
    }
}

echo "\nDiagnostic completed at " . date('Y-m-d H:i:s');
?> 