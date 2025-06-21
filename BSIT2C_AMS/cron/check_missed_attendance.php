<?php
// This script should be run via cron job once per day, after all classes have ended

// Include database connection
require_once '../config/database.php';

// Get current date
$current_date = date('Y-m-d');
$day_of_week = date('N'); // 1 (Monday) to 7 (Sunday)

// Get all missed classes for today
$missed_classes_query = "
    SELECT 
        a.id as assignment_id,
        a.teacher_id,
        a.subject_id,
        a.preferred_day,
        a.time_start,
        a.time_end,
        s.subject_code,
        s.subject_name
    FROM 
        assignments a
    JOIN 
        subjects s ON a.subject_id = s.id
    WHERE 
        a.preferred_day = ? AND
        NOT EXISTS (
            SELECT 1 FROM attendance att 
            WHERE att.teacher_id = a.teacher_id 
            AND att.subject_id = a.subject_id 
            AND att.assignment_id = a.id 
            AND att.attendance_date = ?
        )";

$stmt = $conn->prepare($missed_classes_query);
$stmt->bind_param("is", $day_of_week, $current_date);
$stmt->execute();
$result = $stmt->get_result();

// Log file
$log_file = fopen("attendance_log_" . date('Y-m-d') . ".txt", "a");
fwrite($log_file, "=== Attendance Check Run: " . date('Y-m-d H:i:s') . " ===\n");

if ($result->num_rows === 0) {
    fwrite($log_file, "No missed classes found for today.\n");
} else {
    fwrite($log_file, "Found " . $result->num_rows . " missed classes.\n");
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Create automatic pending records for each missed class
        while ($class = $result->fetch_assoc()) {
            // Insert pending attendance record
            $insert_query = "
                INSERT INTO attendance (
                    teacher_id, subject_id, assignment_id, attendance_date, is_pending, created_at
                ) VALUES (?, ?, ?, ?, 1, NOW())";
            
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("iiis", 
                $class['teacher_id'], 
                $class['subject_id'], 
                $class['assignment_id'], 
                $current_date
            );
            $insert_stmt->execute();
            
            fwrite($log_file, "Created pending record for: " . $class['subject_code'] . " - " . $class['subject_name'] . "\n");
            $insert_stmt->close();
            
            // Also send notification (if you have a notification system)
            // ...
        }
        
        // Commit transaction
        $conn->commit();
        fwrite($log_file, "All pending records created successfully.\n");
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        fwrite($log_file, "ERROR: " . $e->getMessage() . "\n");
    }
}

fwrite($log_file, "=== End of Run ===\n\n");
fclose($log_file);
$stmt->close();
$conn->close();

echo "Attendance check completed."; 