<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check for required fields
        $required_fields = ['teacher_id', 'subject_id', 'time_start', 'time_end', 'location', 'month_from', 'month_to'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception('Missing required fields: ' . implode(', ', $missing_fields));
        }
        
        if (!isset($_POST['days']) || !is_array($_POST['days']) || count($_POST['days']) == 0) {
            throw new Exception('Please select at least one day for the schedule');
        }
        
        // Sanitize inputs
        $teacher_id = (int)$_POST['teacher_id'];
        $subject_id = (int)$_POST['subject_id'];
        $time_start = $_POST['time_start'];
        $time_end = $_POST['time_end'];
        $location = trim($_POST['location']);
        $month_from = $_POST['month_from'];
        $month_to = $_POST['month_to'];
        $days = array_map('trim', $_POST['days']);
        
        // Convert days array to JSON string
        $preferred_days = json_encode($days);
        
        // Validate dates and times
        if (strtotime($month_from) > strtotime($month_to)) {
            throw new Exception('Start date must be earlier than end date');
        }
        
        if (strtotime($time_end) <= strtotime($time_start)) {
            throw new Exception('End time must be later than start time');
        }

        // Start transaction
        $conn->begin_transaction();

        // Check for duplicate subject assignments first - using a simpler query
        $check_query = "SELECT COUNT(*) as count FROM assignments WHERE subject_id = ?";
        $stmt = $conn->prepare($check_query);
        if (!$stmt) {
            throw new Exception('Error preparing statement: ' . $conn->error);
        }
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            // Only fetch subject details if there's a conflict
            $subject_query = "SELECT subject_code, subject_name FROM subjects WHERE id = ?";
            $subject_stmt = $conn->prepare($subject_query);
            $subject_stmt->bind_param("i", $subject_id);
            $subject_stmt->execute();
            $subject_result = $subject_stmt->get_result();
            $subject = $subject_result->fetch_assoc();
            
            throw new Exception("This subject ({$subject['subject_code']} - {$subject['subject_name']}) is already assigned to another teacher.");
        }

        // Optimized conflict check query - using a simpler approach
        $conflict_query = "SELECT a.id, a.time_start, a.time_end, a.preferred_day
                          FROM assignments a 
                          WHERE a.teacher_id = ? 
                          AND (
                              (a.time_start <= ? AND a.time_end > ?) OR
                              (a.time_start < ? AND a.time_end >= ?) OR
                              (a.time_start >= ? AND a.time_end <= ?)
                          )
                          AND JSON_OVERLAP(a.preferred_day, ?)";
        
        $stmt = $conn->prepare($conflict_query);
        if (!$stmt) {
            throw new Exception('Error preparing statement: ' . $conn->error);
        }
        $stmt->bind_param("isssssss", 
            $teacher_id, 
            $time_end, $time_start, 
            $time_end, $time_start, 
            $time_start, $time_end,
            $preferred_days
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Error executing conflict check: ' . $stmt->error);
        }
        
        $conflicts = $stmt->get_result();
        
        if ($conflicts->num_rows > 0) {
            // Only fetch additional details if there are conflicts
            $conflict_message = "Schedule Conflict Detected!\n\n";
            $conflict_message .= "The teacher has existing assignments at the same time on the following days:\n\n";
            
            while ($conflict = $conflicts->fetch_assoc()) {
                $conflict_days = json_decode($conflict['preferred_day'], true);
                $common_days = array_intersect($days, $conflict_days);
                
                if (!empty($common_days)) {
                    // Fetch subject and teacher details only for conflicts
                    $details_query = "SELECT s.subject_code, s.subject_name, 
                                     CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                                     FROM assignments a 
                                     JOIN subjects s ON a.subject_id = s.id
                                     JOIN teachers t ON a.teacher_id = t.id
                                     WHERE a.id = ?";
                    $details_stmt = $conn->prepare($details_query);
                    $details_stmt->bind_param("i", $conflict['id']);
                    $details_stmt->execute();
                    $details_result = $details_stmt->get_result();
                    $details = $details_result->fetch_assoc();
                    
                    $conflict_message .= "• " . implode(', ', $common_days) . "\n";
                    $conflict_message .= "  Subject: {$details['subject_code']} - {$details['subject_name']}\n";
                    $conflict_message .= "  Time: " . date('h:i A', strtotime($conflict['time_start'])) . " - " . date('h:i A', strtotime($conflict['time_end'])) . "\n\n";
                }
            }
            
            $conflict_message .= "Please adjust the schedule to avoid conflicts.";
            throw new Exception($conflict_message);
        }

        // Insert the assignment
        $query = "INSERT INTO assignments (teacher_id, subject_id, month_from, month_to, preferred_day, time_start, time_end, location) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error preparing statement: ' . $conn->error);
        }
        
        $stmt->bind_param("iissssss", $teacher_id, $subject_id, $month_from, $month_to, $preferred_days, $time_start, $time_end, $location);
        
        if (!$stmt->execute()) {
            throw new Exception('Error creating assignment: ' . $stmt->error);
        }
        
        $assignment_id = $conn->insert_id;

        // Create notification - using a simpler message
        $notification_message = "New assignment created";
        $notification_query = "INSERT INTO notifications (teacher_id, message) VALUES (?, ?)";
        $notification_stmt = $conn->prepare($notification_query);
        if (!$notification_stmt) {
            throw new Exception('Error preparing notification statement: ' . $conn->error);
        }
        $notification_stmt->bind_param("is", $teacher_id, $notification_message);
        
        if (!$notification_stmt->execute()) {
            throw new Exception('Error creating notification: ' . $notification_stmt->error);
        }
        
        // Commit transaction
        if (!$conn->commit()) {
            throw new Exception('Error committing transaction: ' . $conn->error);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Assignment created successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method'
    ]);
}
?> 