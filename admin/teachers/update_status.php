<?php
session_start();
require_once '../../config/database.php';

// For debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set content type to JSON
header('Content-Type: application/json');

// Log the raw request
$raw_input = file_get_contents('php://input');
error_log("Raw request received: " . $raw_input);

// Get JSON data from request
$data = json_decode($raw_input, true);

// Log the decoded data for debugging
error_log("Decoded data: " . print_r($data, true));

// Check if required data is provided
if (isset($data['teacher_id']) && isset($data['status'])) {
    $teacher_id = $data['teacher_id'];
    $status = $data['status'];
    
    // Validate status value
    $valid_statuses = ['Active', 'Inactive', 'On Leave'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid status value: ' . $status . '. Must be one of: ' . implode(', ', $valid_statuses)
        ]);
        exit;
    }
    
    // Check if the 'status' column exists in the teachers table
    $check_column = mysqli_query($conn, "SHOW COLUMNS FROM teachers LIKE 'status'");
    if (mysqli_num_rows($check_column) == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Status column does not exist in teachers table.'
        ]);
        exit;
    }
    
    // Get the current column definition
    $column_info = mysqli_fetch_assoc($check_column);
    error_log("Column definition: " . print_r($column_info, true));
    
    // Check if the status is in the ENUM values
    $type = $column_info['Type'];
    preg_match('/enum\((.*)\)/', $type, $matches);
    
    if (isset($matches[1])) {
        $enum_values = str_getcsv($matches[1], ',', "'");
        error_log("ENUM values: " . print_r($enum_values, true));
        
        // Check if the status is in the ENUM values
        if (!in_array($status, $enum_values)) {
            // Attempt to alter the column to add the missing ENUM value
            $new_enum = "'" . implode("','", array_unique(array_merge($enum_values, $valid_statuses))) . "'";
            $alter_query = "ALTER TABLE teachers MODIFY COLUMN status ENUM($new_enum) NOT NULL DEFAULT 'Active'";
            
            error_log("Attempting to alter table with query: " . $alter_query);
            
            if (!mysqli_query($conn, $alter_query)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update the status column definition: ' . mysqli_error($conn)
                ]);
                exit;
            }
            
            error_log("Column definition updated successfully");
        }
    }
    
    // Update teacher status
    $sql = "UPDATE teachers SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Prepare failed: ' . $conn->error
        ]);
        exit;
    }
    
    $stmt->bind_param("si", $status, $teacher_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully',
            'new_status' => $status
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error updating status: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required data. Received: ' . json_encode($data)
    ]);
}

$conn->close();
?> 