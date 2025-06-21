<?php
require_once '../../config/database.php';

// SQL commands to drop and recreate the attendance_logs table
$sql = "DROP TABLE IF EXISTS attendance_logs;
        CREATE TABLE attendance_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            attendance_id INT NOT NULL,
            log_type ENUM('override', 'status_update', 'modification') NOT NULL,
            log_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (attendance_id) REFERENCES attendance(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

try {
    // Execute each SQL statement separately
    if ($conn->multi_query($sql)) {
        do {
            // Store first result set (if any)
            if ($result = $conn->store_result()) {
                $result->free();
            }
            // Check if there are more results
        } while ($conn->more_results() && $conn->next_result());
        
        if ($conn->errno) {
            throw new Exception("Error executing SQL: " . $conn->error);
        }
        echo "Table 'attendance_logs' has been created successfully!";
    } else {
        throw new Exception("Error executing SQL: " . $conn->error);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?> 