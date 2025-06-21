<?php
require_once __DIR__ . '/../../config/database.php';

// Array of index creation queries
$index_queries = [
    // Index for checking duplicate subject assignments
    "ALTER TABLE assignments ADD INDEX idx_subject_id (subject_id)",
    
    // Indexes for conflict checking
    "ALTER TABLE assignments ADD INDEX idx_teacher_id (teacher_id)",
    "ALTER TABLE assignments ADD INDEX idx_time_start (time_start)",
    "ALTER TABLE assignments ADD INDEX idx_time_end (time_end)",
    
    // Composite index for teacher and time range queries
    "ALTER TABLE assignments ADD INDEX idx_teacher_time (teacher_id, time_start, time_end)",
    
    // Index for notifications
    "ALTER TABLE notifications ADD INDEX idx_teacher_id (teacher_id)"
];

// Execute each query
foreach ($index_queries as $query) {
    try {
        if ($conn->query($query)) {
            echo "Successfully executed: $query<br>";
        } else {
            echo "Error executing: $query - " . $conn->error . "<br>";
        }
    } catch (Exception $e) {
        // If index already exists, just continue
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "Index already exists for: $query<br>";
        } else {
            echo "Error: " . $e->getMessage() . "<br>";
        }
    }
}

echo "Index creation process completed.";
?> 