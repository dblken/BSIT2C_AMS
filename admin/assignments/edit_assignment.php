<?php
// Prevent any output before headers
ob_start();

require_once '../../config/database.php';

// Make sure no HTML or whitespace is output before headers
header('Content-Type: application/json');

// Enable error reporting but log to file instead of output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../logs/php_errors.log');

// Function to clean output and return JSON
function return_json($success, $message, $data = null) {
    ob_clean();
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    ob_end_flush();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug: Log the entire POST data
        error_log("Edit Assignment POST data: " . print_r($_POST, true));
        
        // Check for required fields
        $required_fields = ['id', 'teacher_id', 'subject_id', 'time_start', 'time_end', 'location', 'month_from', 'month_to'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            return_json(false, 'Missing required fields: ' . implode(', ', $missing_fields));
        }
        
        if (!isset($_POST['days']) || !is_array($_POST['days']) || count($_POST['days']) == 0) {
            return_json(false, 'Please select at least one day for the schedule');
        }
        
        // Get and sanitize data
        $id = (int)$_POST['id'];
        $teacher_id = (int)$_POST['teacher_id'];
        $subject_id = (int)$_POST['subject_id'];
        $time_start = $_POST['time_start'];
        $time_end = $_POST['time_end'];
        $location = $_POST['location'];
        $month_from = $_POST['month_from'];
        $month_to = $_POST['month_to'];
        $days = $_POST['days'];
        
        // Convert days array to JSON string
        $preferred_days = json_encode($days);
        
        error_log("Parsed data: id=$id, teacher_id=$teacher_id, subject_id=$subject_id");
        error_log("Days array: " . print_r($days, true));
        error_log("JSON days: " . $preferred_days);
        
        // Validate dates
        if (strtotime($month_from) > strtotime($month_to)) {
            return_json(false, 'Start date must be earlier than end date');
        }
        
        // Validate times
        if (strtotime($time_end) <= strtotime($time_start)) {
            return_json(false, 'End time must be later than start time');
        }

        // Get the current assignment data
        $check_query = "SELECT * FROM assignments WHERE id = ?";
        $stmt = $conn->prepare($check_query);
        if (!$stmt) {
            return_json(false, 'Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return_json(false, 'Assignment not found');
        }

        $current_assignment = $result->fetch_assoc();
        error_log("Current assignment: " . print_r($current_assignment, true));
        
        // Track transaction state
        $transaction_started = false;
        
        // Start transaction
        $conn->begin_transaction();
        $transaction_started = true;

        // Update the assignment
        $update_query = "UPDATE assignments SET 
            teacher_id = ?,
            subject_id = ?,
            time_start = ?,
            time_end = ?,
            location = ?,
            month_from = ?,
            month_to = ?,
            preferred_day = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ?";
            
        $update_stmt = $conn->prepare($update_query);
        if (!$update_stmt) {
            throw new Exception('Error preparing update statement: ' . $conn->error);
        }
        
        error_log("About to bind parameters for update query");
        error_log("Parameters: teacher_id=$teacher_id, subject_id=$subject_id, time_start=$time_start, time_end=$time_end, location=$location, month_from=$month_from, month_to=$month_to, preferred_days=$preferred_days, id=$id");
        
        $update_stmt->bind_param("iissssssi", 
            $teacher_id, 
            $subject_id,
            $time_start,
            $time_end,
            $location,
            $month_from,
            $month_to,
            $preferred_days,
            $id
        );
        
        if (!$update_stmt->execute()) {
            throw new Exception('Error updating assignment: ' . $update_stmt->error);
        }
        
        error_log("Assignment updated successfully");
        
        // Delete existing timetable entries
        $delete_query = "DELETE FROM timetable WHERE assignment_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        if (!$delete_stmt) {
            throw new Exception('Error preparing delete statement: ' . $conn->error);
        }
        
        $delete_stmt->bind_param("i", $id);
        if (!$delete_stmt->execute()) {
            throw new Exception('Error deleting timetable entries: ' . $delete_stmt->error);
        }
        
        error_log("Deleted existing timetable entries");
        
        // Create new timetable entries
        foreach ($days as $day) {
            $timetable_query = "INSERT INTO timetable (
                subject_id, assignment_id, teacher_id, day, start_time, end_time, room, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')";
            
            $timetable_stmt = $conn->prepare($timetable_query);
            if (!$timetable_stmt) {
                throw new Exception('Error preparing timetable statement: ' . $conn->error);
            }
            
            error_log("Creating timetable entry for day: $day");
            error_log("Parameters: subject_id=$subject_id, assignment_id=$id, teacher_id=$teacher_id, time_start=$time_start, time_end=$time_end, location=$location");
            
            $timetable_stmt->bind_param("iiissss", 
                $subject_id, 
                $id,
                $teacher_id,
                $day,
                $time_start, 
                $time_end, 
                $location
            );
            
            if (!$timetable_stmt->execute()) {
                throw new Exception('Error creating timetable entry: ' . $timetable_stmt->error);
            }
            
            error_log("Created timetable entry for day: $day");
        }
        
        // Commit transaction
        $conn->commit();
        $transaction_started = false;
        error_log("Transaction committed successfully");
        
        // Return success response
        return_json(true, 'Assignment updated successfully');
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($transaction_started) && $transaction_started && $conn) {
            $conn->rollback();
        }
        
        error_log("Assignment update error: " . $e->getMessage());
        return_json(false, $e->getMessage());
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    // Get assignment data for editing
    try {
        $id = (int)$_GET['id'];
        
        // Check if we need to get subject details
        if (isset($_GET['get_subject_details']) && $_GET['get_subject_details']) {
            $query = "SELECT a.subject_id, s.subject_code, s.subject_name 
                      FROM assignments a
                      JOIN subjects s ON a.subject_id = s.id  
                      WHERE a.id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return_json(false, 'Subject details not found');
            }
            
            $subject_details = $result->fetch_assoc();
            return_json(true, 'Subject details retrieved successfully', $subject_details);
        }
        
        $query = "SELECT * FROM assignments WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return_json(false, 'Assignment not found');
        }
        
        $assignment = $result->fetch_assoc();
        
        // Parse days from JSON
        try {
            $days = json_decode($assignment['preferred_day'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error: " . json_last_error_msg() . " for " . $assignment['preferred_day']);
                $days = [];
            }
            $assignment['days'] = $days;
        } catch (Exception $json_error) {
            error_log("Error decoding JSON: " . $json_error->getMessage());
            $assignment['days'] = [];
        }
        
        return_json(true, 'Assignment data retrieved successfully', $assignment);
    } catch (Exception $e) {
        error_log("Error retrieving assignment data: " . $e->getMessage());
        return_json(false, $e->getMessage());
    }
} else {
    return_json(false, 'Invalid request method');
}
?> 