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

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Check if timetable table exists
    $check_timetable = $conn->query("SHOW TABLES LIKE 'timetable'");
    if ($check_timetable->num_rows === 0) {
        echo json_encode([
            'success' => true,
            'missing_entries' => 0,
            'inconsistent_entries' => 0,
            'orphaned_entries' => 0,
            'message' => 'Timetable table does not exist yet. Run fix to create it.'
        ]);
        exit();
    }
    
    // Initialize counters and arrays
    $missing_entries = 0;
    $inconsistent_entries = 0;
    $orphaned_entries = 0;
    $details = [];
    
    // 1. Check for assignments without timetable entries
    $missing_query = "SELECT a.id, s.subject_code 
                     FROM assignments a 
                     LEFT JOIN subjects s ON a.subject_id = s.id 
                     LEFT JOIN timetable t ON a.id = t.assignment_id 
                     WHERE t.id IS NULL";
    $missing_result = $conn->query($missing_query);
    
    if ($missing_result) {
        $missing_entries = $missing_result->num_rows;
        
        // Add details for the missing entries
        while ($row = $missing_result->fetch_assoc()) {
            $details[] = "Assignment #{$row['id']} for subject {$row['subject_code']} has no timetable entries";
        }
    }
    
    // 2. Check for inconsistent timetable entries (where the data doesn't match the assignment)
    $inconsistent_query = "SELECT t.id, a.id as assignment_id, s.subject_code, 
                          t.subject_id as t_subject_id, a.subject_id as a_subject_id,
                          t.teacher_id as t_teacher_id, a.teacher_id as a_teacher_id
                          FROM timetable t
                          JOIN assignments a ON t.assignment_id = a.id
                          JOIN subjects s ON a.subject_id = s.id
                          WHERE t.subject_id != a.subject_id OR t.teacher_id != a.teacher_id";
    $inconsistent_result = $conn->query($inconsistent_query);
    
    if ($inconsistent_result) {
        $inconsistent_entries = $inconsistent_result->num_rows;
        
        // Add details for the inconsistent entries
        while ($row = $inconsistent_result->fetch_assoc()) {
            if ($row['t_subject_id'] != $row['a_subject_id']) {
                $details[] = "Timetable entry #{$row['id']} has subject ID {$row['t_subject_id']} but assignment #{$row['assignment_id']} has subject ID {$row['a_subject_id']}";
            }
            if ($row['t_teacher_id'] != $row['a_teacher_id']) {
                $details[] = "Timetable entry #{$row['id']} has teacher ID {$row['t_teacher_id']} but assignment #{$row['assignment_id']} has teacher ID {$row['a_teacher_id']}";
            }
        }
    }
    
    // 3. Check for orphaned timetable entries (entries without valid assignments)
    $orphaned_query = "SELECT t.id, t.subject_id, s.subject_code 
                      FROM timetable t
                      LEFT JOIN assignments a ON t.assignment_id = a.id
                      LEFT JOIN subjects s ON t.subject_id = s.id
                      WHERE a.id IS NULL";
    $orphaned_result = $conn->query($orphaned_query);
    
    if ($orphaned_result) {
        $orphaned_entries = $orphaned_result->num_rows;
        
        // Add details for the orphaned entries
        while ($row = $orphaned_result->fetch_assoc()) {
            $subject_code = $row['subject_code'] ?: "Unknown Subject ID {$row['subject_id']}";
            $details[] = "Timetable entry #{$row['id']} for {$subject_code} is orphaned (no valid assignment)";
        }
    }
    
    echo json_encode([
        'success' => true,
        'missing_entries' => $missing_entries,
        'inconsistent_entries' => $inconsistent_entries,
        'orphaned_entries' => $orphaned_entries,
        'details' => $details,
        'message' => "Check completed: Found $missing_entries missing, $inconsistent_entries inconsistent, and $orphaned_entries orphaned entries."
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    // Log the error to the server log
    error_log("Timetable check error: " . $e->getMessage());
}
?> 