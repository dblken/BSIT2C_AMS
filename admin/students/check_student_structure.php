<?php
session_start();
require_once '../../config/database.php';

// For debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Check if the gender column exists in the students table
$has_gender_column = false;

try {
    // Get table structure
    $table_structure = $conn->query("DESCRIBE students");
    $columns = [];
    while ($row = $table_structure->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    $has_gender_column = in_array("gender", $columns);
    
    echo json_encode([
        'success' => true,
        'has_gender_column' => $has_gender_column,
        'columns' => $columns
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?> 