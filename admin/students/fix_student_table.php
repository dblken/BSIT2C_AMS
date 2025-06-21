<?php
session_start();
require_once '../../config/database.php';

// For debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'add_gender_column') {
        try {
            // Check if the gender column already exists
            $table_structure = $conn->query("DESCRIBE students");
            $columns = [];
            while ($row = $table_structure->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
            
            if (!in_array("gender", $columns)) {
                // Add gender column to the table
                $alter_gender = "ALTER TABLE students ADD COLUMN gender ENUM('Male', 'Female') DEFAULT NULL";
                $conn->query($alter_gender);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Gender column added successfully.'
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'Gender column already exists.'
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?> 