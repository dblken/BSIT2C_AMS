<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

// Check if the user is an admin
session_start();
if (!isset($_SESSION['admin_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Check if timetable table exists
    $check_timetable = $conn->query("SHOW TABLES LIKE 'timetable'");
    if ($check_timetable->num_rows === 0) {
        // Create timetable table if it doesn't exist
        $create_timetable = "CREATE TABLE timetable (
            id INT PRIMARY KEY AUTO_INCREMENT,
            subject_id INT,
            teacher_id INT,
            day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            room VARCHAR(50) NOT NULL,
            status ENUM('Active', 'Cancelled', 'Rescheduled') DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (subject_id) REFERENCES subjects(id),
            FOREIGN KEY (teacher_id) REFERENCES teachers(id)
        )";
        
        if (!$conn->query($create_timetable)) {
            throw new Exception("Failed to create timetable table: " . $conn->error);
        }
    }
    
    // Get day number mapping for timetable
    $day_mapping = [
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
        'Saturday' => 6
    ];
    
    // Find assignments without timetable entries
    $query = "SELECT a.* FROM assignments a 
              LEFT JOIN timetable t ON a.id = t.assignment_id 
              WHERE t.id IS NULL";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error fetching assignments: " . $conn->error);
    }
    
    $created_count = 0;
    $error_count = 0;
    $errors = [];
    
    // Process each assignment
    while ($assignment = $result->fetch_assoc()) {
        $assignment_id = $assignment['id'];
        $teacher_id = $assignment['teacher_id'];
        $subject_id = $assignment['subject_id'];
        $time_start = $assignment['time_start'];
        $time_end = $assignment['time_end'];
        $location = $assignment['location'] ?: 'TBA';
        $preferred_days = json_decode($assignment['preferred_day'], true);
        
        if (!is_array($preferred_days) || empty($preferred_days)) {
            $error_count++;
            
            // Get subject code for better error reporting
            $subject_query = "SELECT subject_code FROM subjects WHERE id = ?";
            $subject_stmt = $conn->prepare($subject_query);
            $subject_stmt->bind_param("i", $subject_id);
            $subject_stmt->execute();
            $subject_result = $subject_stmt->get_result();
            $subject_code = $subject_result->fetch_assoc()['subject_code'] ?? 'Unknown';
            
            $errors[] = "Assignment ID {$assignment_id} for subject {$subject_code} has invalid days format";
            continue;
        }
        
        $days_processed = 0;
        foreach ($preferred_days as $day) {
            $day_number = $day_mapping[$day] ?? null;
            
            // Create timetable entry query
            $timetable_query = "INSERT INTO timetable (
                subject_id, teacher_id, day_of_week, start_time, end_time, room, status
            ) VALUES (?, ?, ?, ?, ?, ?, 'Active')";
            
            $timetable_stmt = $conn->prepare($timetable_query);
            if (!$timetable_stmt) {
                $error_count++;
                $errors[] = "Error preparing statement for assignment {$assignment_id}: " . $conn->error;
                continue;
            }
            
            // Use the day name directly since we're using ENUM
            $day_value = $day;
            
            $timetable_stmt->bind_param("iiisss", 
                $subject_id,
                $teacher_id,
                $day_value,
                $time_start, 
                $time_end, 
                $location
            );
            
            if (!$timetable_stmt->execute()) {
                $error_count++;
                $errors[] = "Error creating timetable entry for assignment {$assignment_id}, day {$day}: " . $timetable_stmt->error;
                continue;
            }
            
            $days_processed++;
        }
        
        if ($days_processed > 0) {
            $created_count++;
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'created_count' => $created_count,
        'error_count' => $error_count,
        'errors' => $errors,
        'message' => "Timetable entries created: $created_count assignments updated with timetable entries."
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    // Log the error to the server log
    error_log("Create timetable entries error: " . $e->getMessage());
}
?> 