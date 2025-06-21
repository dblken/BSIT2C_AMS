<?php
session_start();
require_once '../../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to browser
ini_set('log_errors', 1); // Log errors instead

// Set the content type to JSON
header('Content-Type: application/json');

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Verify all POST parameters for debugging
$post_data = [];
foreach ($_POST as $key => $value) {
    $post_data[$key] = $value;
}

// Check if required parameters are provided
if (!isset($_POST['student_id']) || empty($_POST['student_id']) || 
    !isset($_POST['subject_schedule']) && !isset($_POST['assignment_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters',
        'data' => $post_data
    ]);
    exit;
}

// Get parameters
$student_id = intval($_POST['student_id']);

// Handle different form parameter names
$assignment_ids = [];

if (isset($_POST['subject_schedule']) && is_array($_POST['subject_schedule']) && !empty($_POST['subject_schedule'])) {
    // Get all selected assignments from subject_schedule array
    foreach ($_POST['subject_schedule'] as $value) {
        $assignment_ids[] = intval($value);
    }
} elseif (isset($_POST['assignment_id'])) {
    // Single assignment ID
    if (is_array($_POST['assignment_id'])) {
        foreach ($_POST['assignment_id'] as $value) {
            $assignment_ids[] = intval($value);
        }
    } else {
        $assignment_ids[] = intval($_POST['assignment_id']);
    }
}

// Check if we have valid assignment IDs
if (empty($assignment_ids)) {
    echo json_encode([
        'success' => false,
        'message' => 'No valid assignments selected',
        'data' => $post_data
    ]);
    exit;
}

try {
    // Start a transaction
    $conn->begin_transaction();
    
    $success_count = 0;
    $error_messages = [];
    
    // Process each assignment ID
    foreach ($assignment_ids as $assignment_id) {
        // Get assignment details
        $assignment_query = "SELECT a.*, s.id AS subject_id, s.subject_name, s.subject_code, 
                                 t.id AS teacher_id, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
                          FROM assignments a
                          JOIN subjects s ON a.subject_id = s.id
                          JOIN teachers t ON a.teacher_id = t.id
                          WHERE a.id = ?";
        
        $stmt = $conn->prepare($assignment_query);
        $stmt->bind_param("i", $assignment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error_messages[] = "Assignment #$assignment_id not found";
            continue;
        }
        
        $assignment = $result->fetch_assoc();
        
        // Check if this assignment has a timetable entry - if not, create one
        $timetable_check_query = "SELECT id FROM timetable WHERE assignment_id = ? LIMIT 1";
        $timetable_check_stmt = $conn->prepare($timetable_check_query);
        $timetable_check_stmt->bind_param("i", $assignment_id);
        $timetable_check_stmt->execute();
        $timetable_check_result = $timetable_check_stmt->get_result();
        
        if ($timetable_check_result->num_rows === 0) {
            // Create timetable entries for this assignment
            $preferred_days = json_decode($assignment['preferred_day'], true);
            
            if (!is_array($preferred_days) || empty($preferred_days)) {
                $conn->rollback();
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid days format for assignment'
                ]);
                exit;
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
            
            foreach ($preferred_days as $day) {
                $day_number = $day_mapping[$day] ?? null;
                $day_value = is_numeric($day_number) ? $day_number : $day;
                $location = $assignment['location'] ?: 'TBA';
                
                $timetable_query = "INSERT INTO timetable (
                    subject_id, assignment_id, day, time_start, time_end
                ) VALUES (?, ?, ?, ?, ?)";
                
                $timetable_stmt = $conn->prepare($timetable_query);
                $timetable_stmt->bind_param("iisss", 
                    $assignment['subject_id'],
                    $assignment_id,
                    $day_value,
                    $assignment['time_start'],
                    $assignment['time_end']
                );
                
                if (!$timetable_stmt->execute()) {
                    error_log("Failed to create timetable entry: " . $timetable_stmt->error);
                }
            }
            
            // Check again after creating
            $timetable_check_stmt->execute();
            $timetable_check_result = $timetable_check_stmt->get_result();
            
            if ($timetable_check_result->num_rows === 0) {
                $conn->rollback();
                echo json_encode([
                    'success' => false,
                    'message' => "Failed to create timetable entries for assignment. Please run fix_timetable.php first."
                ]);
                exit;
            }
        }
        
        // Get the first timetable entry ID for this assignment
        $timetable_id_query = "SELECT id FROM timetable WHERE assignment_id = ? LIMIT 1";
        $timetable_id_stmt = $conn->prepare($timetable_id_query);
        $timetable_id_stmt->bind_param("i", $assignment_id);
        $timetable_id_stmt->execute();
        $timetable_id_result = $timetable_id_stmt->get_result();
        $timetable_id = $timetable_id_result->fetch_assoc()['id'];
        
        // Check if student already enrolled in this subject
        $check_query = "SELECT e.* FROM enrollments e 
                        JOIN assignments a ON e.assignment_id = a.id
                        WHERE e.student_id = ? AND a.subject_id = ?";
        
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $student_id, $assignment['subject_id']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $conn->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'Student is already enrolled in this subject'
            ]);
            exit;
        }
        
        // Check if there are any schedule conflicts
        $conflict_query = "SELECT e.*, s.subject_code, s.subject_name, tt.day, tt.time_start, tt.time_end
                           FROM enrollments e
                           JOIN assignments a ON e.assignment_id = a.id
                           JOIN subjects s ON a.subject_id = s.id
                           LEFT JOIN timetable tt ON a.id = tt.assignment_id
                           WHERE e.student_id = ? 
                           AND tt.day IS NOT NULL AND tt.time_start IS NOT NULL AND tt.time_end IS NOT NULL
                           AND tt.day = ? 
                           AND ((tt.time_start <= ? AND tt.time_end > ?) 
                           OR (tt.time_start < ? AND tt.time_end >= ?) 
                           OR (tt.time_start >= ? AND tt.time_end <= ?))";
        
        // Get the day and times from the first timetable entry for this assignment
        $timetable_entry_query = "SELECT * FROM timetable WHERE assignment_id = ? LIMIT 1";
        $timetable_entry_stmt = $conn->prepare($timetable_entry_query);
        $timetable_entry_stmt->bind_param("i", $assignment_id);
        $timetable_entry_stmt->execute();
        $timetable_entry_result = $timetable_entry_stmt->get_result();
        $timetable_entry = $timetable_entry_result->fetch_assoc();
        
        if ($timetable_entry) {
            $conflict_stmt = $conn->prepare($conflict_query);
            $conflict_stmt->bind_param(
                "isssssss", 
                $student_id, 
                $timetable_entry['day'],
                $timetable_entry['time_end'],
                $timetable_entry['time_start'],
                $timetable_entry['time_end'],
                $timetable_entry['time_start'],
                $timetable_entry['time_start'],
                $timetable_entry['time_end']
            );
            $conflict_stmt->execute();
            $conflict_result = $conflict_stmt->get_result();
            
            if ($conflict_result->num_rows > 0) {
                $conflict = $conflict_result->fetch_assoc();
                $conn->rollback();
                echo json_encode([
                    'success' => false,
                    'message' => 'Schedule conflict with ' . $conflict['subject_code'] . ' (' . 
                                  $conflict['subject_name'] . ') on ' . $conflict['day'] . ' at ' . 
                                  $conflict['time_start'] . ' - ' . $conflict['time_end']
                ]);
                exit;
            }
        }
        
        // Verify enrollments table structure before insertion
        $check_table = $conn->query("DESCRIBE enrollments");
        $columns = [];
        while ($col = $check_table->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
        
        // Insert enrollment with schema-appropriate query
        if (in_array('subject_id', $columns) && in_array('assignment_id', $columns)) {
            // New schema with both subject_id and assignment_id
            $insert_query = "INSERT INTO enrollments (student_id, subject_id, assignment_id, schedule_id, created_at) 
                            VALUES (?, ?, ?, ?, NOW())";
            
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("iiii", $student_id, $assignment['subject_id'], $assignment_id, $timetable_id);
        } else if (in_array('assignment_id', $columns)) {
            // Schema with just assignment_id
            $insert_query = "INSERT INTO enrollments (student_id, assignment_id, created_at) 
                            VALUES (?, ?, NOW())";
            
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("ii", $student_id, $assignment_id);
        } else if (in_array('schedule_id', $columns)) {
            // Old schema with just schedule_id
            $insert_query = "INSERT INTO enrollments (student_id, schedule_id, created_at) 
                            VALUES (?, ?, NOW())";
            
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("ii", $student_id, $timetable_id);
        } else {
            // Fallback if columns are missing
            $available_columns = "Available columns: " . implode(", ", $columns);
            throw new Exception("Enrollment table structure is incomplete. " . $available_columns);
        }
        
        $insert_result = $insert_stmt->execute();
        
        if (!$insert_result) {
            $conn->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create enrollment: ' . $conn->error
            ]);
            exit;
        }
        
        // Get student details for notification
        $student_query = "SELECT CONCAT(first_name, ' ', last_name) AS student_name 
                          FROM students WHERE id = ?";
        
        $student_stmt = $conn->prepare($student_query);
        $student_stmt->bind_param("i", $student_id);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        $student = $student_result->fetch_assoc();
        
        // Only create notifications if the table exists and has the right structure
        try {
            $check_notifications = $conn->query("SHOW TABLES LIKE 'notifications'");
            if ($check_notifications->num_rows > 0) {
                // Get the structure to check what columns we have
                $columns_result = $conn->query("DESCRIBE notifications");
                $notification_columns = [];
                while ($col = $columns_result->fetch_assoc()) {
                    $notification_columns[] = $col['Field'];
                }
                
                // Check if we have user_id
                if (in_array('user_id', $notification_columns)) {
                    // First get user_id for this teacher
                    $user_query = "SELECT user_id FROM teachers WHERE id = ?";
                    $user_stmt = $conn->prepare($user_query);
                    $user_stmt->bind_param("i", $assignment['teacher_id']);
                    $user_stmt->execute();
                    $user_result = $user_stmt->get_result();
                    
                    if ($user_result->num_rows > 0) {
                        $user_id = $user_result->fetch_assoc()['user_id'];
                        
                        // Create a notification for the teacher using user_id
                        $message = $student['student_name'] . " has been enrolled in your subject " . 
                                   $assignment['subject_code'] . " (" . $assignment['subject_name'] . ").";
                        
                        $notification_query = "INSERT INTO notifications (user_id, message, created_at) 
                                             VALUES (?, ?, NOW())";
                        $notification_stmt = $conn->prepare($notification_query);
                        $notification_stmt->bind_param("is", $user_id, $message);
                        $notification_stmt->execute();
                    }
                } else if (in_array('teacher_id', $notification_columns)) {
                    // Create a notification for the teacher using teacher_id
                    $teacher_id = $assignment['teacher_id'];
                    $message = $student['student_name'] . " has been enrolled in your subject " . 
                              $assignment['subject_code'] . " (" . $assignment['subject_name'] . ").";
                    
                    $notification_query = "INSERT INTO notifications (teacher_id, message, created_at) 
                                          VALUES (?, ?, NOW())";
                    $notification_stmt = $conn->prepare($notification_query);
                    $notification_stmt->bind_param("is", $teacher_id, $message);
                    $notification_stmt->execute();
                }
            }
        } catch (Exception $e) {
            // Just log the error and continue - don't let notification errors stop the enrollment
            error_log("Error creating notifications: " . $e->getMessage());
        }
        
        $success_count++;
    }
    
    // Commit the transaction
    $conn->commit();
    
    // Generate a summary message based on results
    if ($success_count > 0) {
        if ($success_count == 1) {
            $message = 'Student successfully enrolled in the selected subject';
        } else {
            $message = "Student successfully enrolled in $success_count subjects";
        }
        
        if (!empty($error_messages)) {
            $message .= ", but the following errors occurred: " . implode("; ", $error_messages);
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'enrolled_count' => $success_count
        ]);
    } else {
        // If no enrollments were successful
        echo json_encode([
            'success' => false,
            'message' => 'Failed to enroll student: ' . implode("; ", $error_messages),
            'errors' => $error_messages
        ]);
    }
    
} catch (Exception $e) {
    // Rollback the transaction in case of an error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage(),
        'debug_info' => [
            'student_id' => $student_id,
            'assignment_ids' => $assignment_ids,
            'post_data' => $post_data
        ]
    ]);
} finally {
    // Close the database connection
    $conn->close();
}