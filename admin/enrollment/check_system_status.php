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
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Initialize counters and arrays
    $missing_timetable_count = 0;
    $missing_schedule_count = 0;
    $inconsistencies = 0;
    $details = [];
    
    // Check if timetable table exists
    $check_timetable = $conn->query("SHOW TABLES LIKE 'timetable'");
    if ($check_timetable->num_rows === 0) {
        echo json_encode([
            'success' => true,
            'missing_timetable_count' => 0,
            'missing_schedule_count' => 0,
            'inconsistencies' => 1,
            'details' => ['Timetable table does not exist yet. Run "Create Timetable Entries" to create it.']
        ]);
        exit();
    }
    
    // Check assignments without timetable entries
    $missing_timetable_query = "SELECT a.id, s.subject_code, s.subject_name 
                              FROM assignments a 
                              LEFT JOIN subjects s ON a.subject_id = s.id 
                              LEFT JOIN timetable t ON a.id = t.assignment_id 
                              WHERE t.id IS NULL";
    $missing_timetable_result = $conn->query($missing_timetable_query);
    
    if ($missing_timetable_result) {
        $missing_timetable_count = $missing_timetable_result->num_rows;
        
        // Add details for missing timetable entries
        while ($row = $missing_timetable_result->fetch_assoc()) {
            $details[] = "Assignment #{$row['id']} for subject {$row['subject_code']} ({$row['subject_name']}) has no timetable entries";
        }
    }
    
    // Check structure of enrollments table
    $check_enrollments = $conn->query("SHOW TABLES LIKE 'enrollments'");
    if ($check_enrollments->num_rows === 0) {
        echo json_encode([
            'success' => true,
            'missing_timetable_count' => $missing_timetable_count,
            'missing_schedule_count' => 0,
            'inconsistencies' => $inconsistencies + 1,
            'details' => array_merge($details, ['Enrollments table does not exist yet.'])
        ]);
        exit();
    }
    
    // Check enrollments table structure
    $columns_result = $conn->query("DESCRIBE enrollments");
    $columns = [];
    while ($col = $columns_result->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
    
    // Check for enrollments without proper schedule links
    $has_schedule_id = in_array('schedule_id', $columns);
    $has_assignment_id = in_array('assignment_id', $columns);
    
    if ($has_schedule_id && $has_assignment_id) {
        // Both schedule_id and assignment_id exist
        $missing_schedule_query = "SELECT e.id, e.student_id, s.first_name, s.last_name, subj.subject_code
                                  FROM enrollments e
                                  JOIN students s ON e.student_id = s.id
                                  LEFT JOIN assignments a ON e.assignment_id = a.id
                                  LEFT JOIN subjects subj ON a.subject_id = subj.id
                                  LEFT JOIN timetable t ON e.schedule_id = t.id
                                  WHERE (e.schedule_id IS NULL OR t.id IS NULL) AND e.assignment_id IS NOT NULL";
        
        $missing_schedule_result = $conn->query($missing_schedule_query);
        
        if ($missing_schedule_result) {
            $missing_schedule_count = $missing_schedule_result->num_rows;
            
            // Add details for enrollments without schedules
            while ($row = $missing_schedule_result->fetch_assoc()) {
                $details[] = "Enrollment #{$row['id']} for student {$row['first_name']} {$row['last_name']} in {$row['subject_code']} has no valid schedule ID";
            }
        }
    } elseif ($has_schedule_id) {
        // Only schedule_id exists
        $missing_schedule_query = "SELECT e.id, e.student_id, s.first_name, s.last_name
                                  FROM enrollments e
                                  JOIN students s ON e.student_id = s.id
                                  LEFT JOIN timetable t ON e.schedule_id = t.id
                                  WHERE e.schedule_id IS NULL OR t.id IS NULL";
        
        $missing_schedule_result = $conn->query($missing_schedule_query);
        
        if ($missing_schedule_result) {
            $missing_schedule_count = $missing_schedule_result->num_rows;
            
            // Add details for enrollments without schedules
            while ($row = $missing_schedule_result->fetch_assoc()) {
                $details[] = "Enrollment #{$row['id']} for student {$row['first_name']} {$row['last_name']} has no valid schedule ID";
            }
        }
    } elseif ($has_assignment_id) {
        // Only assignment_id exists
        $missing_schedule_query = "SELECT e.id, e.student_id, s.first_name, s.last_name, subj.subject_code
                                  FROM enrollments e
                                  JOIN students s ON e.student_id = s.id
                                  LEFT JOIN assignments a ON e.assignment_id = a.id
                                  LEFT JOIN subjects subj ON a.subject_id = subj.id
                                  LEFT JOIN timetable t ON a.id = t.assignment_id
                                  WHERE t.id IS NULL AND e.assignment_id IS NOT NULL";
        
        $missing_schedule_result = $conn->query($missing_schedule_query);
        
        if ($missing_schedule_result) {
            $missing_schedule_count = $missing_schedule_result->num_rows;
            
            // Add details for enrollments without schedules
            while ($row = $missing_schedule_result->fetch_assoc()) {
                $details[] = "Enrollment #{$row['id']} for student {$row['first_name']} {$row['last_name']} in {$row['subject_code']} has no valid timetable entry";
            }
        }
    } else {
        // Neither schedule_id nor assignment_id exists
        $inconsistencies++;
        $details[] = "Enrollments table structure is incomplete. Missing schedule_id or assignment_id column.";
    }
    
    // Count total inconsistencies
    $inconsistencies += ($missing_timetable_count > 0 ? 1 : 0) + ($missing_schedule_count > 0 ? 1 : 0);
    
    echo json_encode([
        'success' => true,
        'missing_timetable_count' => $missing_timetable_count,
        'missing_schedule_count' => $missing_schedule_count,
        'inconsistencies' => $inconsistencies,
        'details' => $details
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    // Log the error to the server log
    error_log("Check system status error: " . $e->getMessage());
}
?> 