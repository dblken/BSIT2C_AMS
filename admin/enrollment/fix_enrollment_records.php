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
        throw new Exception("Timetable table doesn't exist. Please run 'Create Timetable Entries' first.");
    }
    
    // Check enrollments table structure
    $columns_result = $conn->query("DESCRIBE enrollments");
    $columns = [];
    while ($col = $columns_result->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
    
    $has_schedule_id = in_array('schedule_id', $columns);
    $has_assignment_id = in_array('assignment_id', $columns);
    $has_subject_id = in_array('subject_id', $columns);
    
    if (!$has_schedule_id && !$has_assignment_id) {
        throw new Exception("Enrollments table missing required columns: schedule_id or assignment_id");
    }
    
    $fixed_count = 0;
    $error_count = 0;
    $errors = [];
    
    // Scenario 1: Both schedule_id and assignment_id exist
    if ($has_schedule_id && $has_assignment_id) {
        // Find enrollments with assignment_id but missing/invalid schedule_id
        $query = "SELECT e.id, e.student_id, e.assignment_id, s.first_name, s.last_name, 
                         sub.subject_code, a.subject_id
                  FROM enrollments e
                  JOIN students s ON e.student_id = s.id
                  JOIN assignments a ON e.assignment_id = a.id
                  JOIN subjects sub ON a.subject_id = sub.id
                  LEFT JOIN timetable t ON e.schedule_id = t.id
                  WHERE (e.schedule_id IS NULL OR t.id IS NULL)";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($enrollment = $result->fetch_assoc()) {
            // Find the first valid timetable entry for this assignment
            $timetable_query = "SELECT id FROM timetable WHERE assignment_id = ? LIMIT 1";
            $timetable_stmt = $conn->prepare($timetable_query);
            $timetable_stmt->bind_param("i", $enrollment['assignment_id']);
            $timetable_stmt->execute();
            $timetable_result = $timetable_stmt->get_result();
            
            if ($timetable_result->num_rows > 0) {
                $timetable = $timetable_result->fetch_assoc();
                $schedule_id = $timetable['id'];
                
                // Update the enrollment record
                $update_query = "UPDATE enrollments SET schedule_id = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ii", $schedule_id, $enrollment['id']);
                
                if ($update_stmt->execute()) {
                    $fixed_count++;
                } else {
                    $error_count++;
                    $errors[] = "Failed to update enrollment #{$enrollment['id']} for student {$enrollment['first_name']} {$enrollment['last_name']} in {$enrollment['subject_code']}: " . $conn->error;
                }
            } else {
                // No timetable entry found for this assignment
                $error_count++;
                $errors[] = "No timetable entry found for assignment #{$enrollment['assignment_id']} (enrollment #{$enrollment['id']})";
            }
        }
        
        // Also ensure subject_id is set correctly
        if ($has_subject_id) {
            $subject_query = "UPDATE enrollments e 
                              JOIN assignments a ON e.assignment_id = a.id 
                              SET e.subject_id = a.subject_id 
                              WHERE e.subject_id IS NULL OR e.subject_id != a.subject_id";
            
            $conn->query($subject_query);
            $fixed_count += $conn->affected_rows;
        }
    }
    // Scenario 2: Only assignment_id exists
    else if ($has_assignment_id) {
        // Add schedule_id column if it doesn't exist
        if (!$has_schedule_id) {
            $add_column = "ALTER TABLE enrollments ADD COLUMN schedule_id INT, 
                            ADD CONSTRAINT fk_schedule_id FOREIGN KEY (schedule_id) REFERENCES timetable(id) ON DELETE SET NULL";
            
            if (!$conn->query($add_column)) {
                throw new Exception("Failed to add schedule_id column: " . $conn->error);
            }
        }
        
        // Find all enrollments and set schedule_id
        $query = "SELECT e.id, e.student_id, e.assignment_id, s.first_name, s.last_name, 
                         sub.subject_code
                  FROM enrollments e
                  JOIN students s ON e.student_id = s.id
                  JOIN assignments a ON e.assignment_id = a.id
                  JOIN subjects sub ON a.subject_id = sub.id
                  WHERE e.schedule_id IS NULL";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($enrollment = $result->fetch_assoc()) {
            // Find the first valid timetable entry for this assignment
            $timetable_query = "SELECT id FROM timetable WHERE assignment_id = ? LIMIT 1";
            $timetable_stmt = $conn->prepare($timetable_query);
            $timetable_stmt->bind_param("i", $enrollment['assignment_id']);
            $timetable_stmt->execute();
            $timetable_result = $timetable_stmt->get_result();
            
            if ($timetable_result->num_rows > 0) {
                $timetable = $timetable_result->fetch_assoc();
                $schedule_id = $timetable['id'];
                
                // Update the enrollment record
                $update_query = "UPDATE enrollments SET schedule_id = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ii", $schedule_id, $enrollment['id']);
                
                if ($update_stmt->execute()) {
                    $fixed_count++;
                } else {
                    $error_count++;
                    $errors[] = "Failed to update enrollment #{$enrollment['id']} for student {$enrollment['first_name']} {$enrollment['last_name']} in {$enrollment['subject_code']}: " . $conn->error;
                }
            } else {
                // No timetable entry found for this assignment
                $error_count++;
                $errors[] = "No timetable entry found for assignment #{$enrollment['assignment_id']} (enrollment #{$enrollment['id']})";
            }
        }
    }
    // Scenario 3: Only schedule_id exists
    else if ($has_schedule_id) {
        // Add assignment_id column if it doesn't exist
        if (!$has_assignment_id) {
            $add_column = "ALTER TABLE enrollments ADD COLUMN assignment_id INT, 
                            ADD CONSTRAINT fk_assignment_id FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE";
            
            if (!$conn->query($add_column)) {
                throw new Exception("Failed to add assignment_id column: " . $conn->error);
            }
        }
        
        // Find enrollments with schedule_id but missing assignment_id
        $query = "SELECT e.id, e.student_id, e.schedule_id, s.first_name, s.last_name, t.assignment_id
                  FROM enrollments e
                  JOIN students s ON e.student_id = s.id
                  JOIN timetable t ON e.schedule_id = t.id
                  WHERE e.assignment_id IS NULL";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($enrollment = $result->fetch_assoc()) {
            // Update the enrollment record with the assignment_id from timetable
            $update_query = "UPDATE enrollments SET assignment_id = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ii", $enrollment['assignment_id'], $enrollment['id']);
            
            if ($update_stmt->execute()) {
                $fixed_count++;
            } else {
                $error_count++;
                $errors[] = "Failed to update enrollment #{$enrollment['id']} for student {$enrollment['first_name']} {$enrollment['last_name']}: " . $conn->error;
            }
        }
        
        // Also ensure subject_id is set correctly
        if ($has_subject_id) {
            $subject_query = "UPDATE enrollments e 
                              JOIN timetable t ON e.schedule_id = t.id 
                              SET e.subject_id = t.subject_id 
                              WHERE e.subject_id IS NULL OR e.subject_id != t.subject_id";
            
            $conn->query($subject_query);
            $fixed_count += $conn->affected_rows;
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'fixed_count' => $fixed_count,
        'error_count' => $error_count,
        'errors' => $errors,
        'message' => "Enrollment records fixed: $fixed_count records updated."
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
    error_log("Fix enrollment records error: " . $e->getMessage());
}
?> 