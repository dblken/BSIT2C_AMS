<?php
// Start session
session_start();

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Include database connection
require_once '../../config/database.php';

// Initialize variables
$response = [
    'success' => false,
    'message' => 'An error occurred while processing the request.'
];

// Verify request method and data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get POST data
        $teacher_id = $_SESSION['teacher_id'];
        $subject_id = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : 0;
        $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
        $attendance_date = isset($_POST['attendance_date']) ? $_POST['attendance_date'] : date('Y-m-d');
        $status_data = isset($_POST['status']) ? $_POST['status'] : [];
        $remarks_data = isset($_POST['remarks']) ? $_POST['remarks'] : [];
        $is_pending = isset($_POST['is_pending']) ? intval($_POST['is_pending']) : 0;
        
        // Validate required data
        if (!$subject_id || !$assignment_id || empty($status_data)) {
            $response['message'] = 'Missing required data for attendance.';
            echo json_encode($response);
            exit;
        }
        
        // Comprehensive validation for the assignment
        $validation_query = "
            SELECT a.id, a.subject_id 
            FROM assignments a
            JOIN subjects s ON a.subject_id = s.id
            JOIN teachers t ON a.teacher_id = t.id
            WHERE a.id = ? 
            AND a.teacher_id = ? 
            AND a.subject_id = ?";
        
        $stmt = $conn->prepare($validation_query);
        $stmt->bind_param("iii", $assignment_id, $teacher_id, $subject_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Check if the assignment exists at all
            $check_assignment = "SELECT id FROM assignments WHERE id = ?";
            $stmt = $conn->prepare($check_assignment);
            $stmt->bind_param("i", $assignment_id);
            $stmt->execute();
            $check_result = $stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                $response['message'] = 'The selected assignment does not exist in the database. Please select a valid assignment.';
            } else {
                $response['message'] = 'You do not have permission to take attendance for this assignment or the assignment is no longer active.';
            }
            
            echo json_encode($response);
            exit;
        }
        
        // Set autocommit to false to start transaction
        $conn->autocommit(FALSE);
        
        // Check if an attendance record already exists for this date, subject, and teacher
        $check_query = "
            SELECT id FROM attendance 
            WHERE teacher_id = ? AND subject_id = ? AND assignment_id = ? AND attendance_date = ?";
        
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("iiis", $teacher_id, $subject_id, $assignment_id, $attendance_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Attendance record exists, update it
            $attendance_id = $result->fetch_assoc()['id'];
            
            // Update the attendance record
            $update_query = "
                UPDATE attendance 
                SET is_pending = ?, updated_at = NOW() 
                WHERE id = ?";
            
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ii", $is_pending, $attendance_id);
            $stmt->execute();
            
            // Delete existing attendance records for this attendance_id
            $delete_query = "DELETE FROM attendance_records WHERE attendance_id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $attendance_id);
            $stmt->execute();
        } else {
            // Create new attendance record
            $insert_query = "
                INSERT INTO attendance (teacher_id, subject_id, assignment_id, attendance_date, is_pending, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("iiisi", $teacher_id, $subject_id, $assignment_id, $attendance_date, $is_pending);
            
            // If the query fails due to foreign key constraints, log detailed information
            if (!$stmt->execute()) {
                // Log error details
                error_log("Error inserting attendance record: " . $stmt->error);
                error_log("teacher_id: $teacher_id, subject_id: $subject_id, assignment_id: $assignment_id");
                
                // Check if the assignment exists
                $check_query = "SELECT id FROM assignments WHERE id = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("i", $assignment_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows === 0) {
                    error_log("Assignment ID $assignment_id does not exist in the database");
                    // Handle the error by sending an appropriate message to the user
                    echo json_encode([
                        'success' => false,
                        'message' => "The assignment does not exist in the database. Please create the assignment first."
                    ]);
                    exit;
                }
                
                // If we reach here, it's a different error
                echo json_encode([
                    'success' => false,
                    'message' => "Failed to create attendance record: " . $stmt->error
                ]);
                exit;
            }
            
            $attendance_id = $conn->insert_id;
        }
        
        // Insert attendance records for each student
        $record_count = 0;
        foreach ($status_data as $student_id => $status) {
            $student_id = intval($student_id);
            $remarks = isset($remarks_data[$student_id]) ? $remarks_data[$student_id] : '';
            
            $insert_record = "
                INSERT INTO attendance_records (attendance_id, student_id, status, remarks, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($insert_record);
            $stmt->bind_param("iiss", $attendance_id, $student_id, $status, $remarks);
            $stmt->execute();
            $record_count++;
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set autocommit back to true
        $conn->autocommit(TRUE);
        
        // Check if activity_logs table exists before logging
        $check_table = $conn->query("SHOW TABLES LIKE 'activity_logs'");
        if ($check_table->num_rows > 0) {
            // Log the action
            $log_details = json_encode([
                'subject_id' => $subject_id,
                'assignment_id' => $assignment_id,
                'date' => $attendance_date,
                'students_count' => $record_count,
                'is_pending' => $is_pending
            ]);
            
            $log_query = "
                INSERT INTO activity_logs (user_id, user_type, action, details, created_at) 
                VALUES (?, 'teacher', 'attendance_saved', ?, NOW())";
            
            $stmt = $conn->prepare($log_query);
            $stmt->bind_param("is", $teacher_id, $log_details);
            $stmt->execute();
        }
        
        // Success response
        $response['success'] = true;
        $response['message'] = "Attendance for $record_count students has been saved successfully.";
        if ($is_pending) {
            $response['message'] .= " The attendance has been marked as pending because it was taken outside of the regular schedule.";
        }
        $response['attendance_id'] = $attendance_id;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $conn->autocommit(TRUE); // Set autocommit back to true
        
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response); 