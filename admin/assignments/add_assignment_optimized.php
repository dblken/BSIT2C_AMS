<?php
require_once __DIR__ . '/../../config/database.php';

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Set headers for JSON response
header('Content-Type: application/json');

// Function to validate input
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

try {
    // OPTIMIZATION: Cache frequently accessed data
    $start_time = microtime(true);
    
    // Validate required fields
    $required_fields = ['subject_id', 'teacher_id', 'month_from', 'month_to', 'time_start', 'time_end', 'days'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Sanitize and validate input
    $subject_id = validateInput($_POST['subject_id']);
    $teacher_id = validateInput($_POST['teacher_id']);
    $month_from = validateInput($_POST['month_from']);
    $month_to = validateInput($_POST['month_to']);
    $time_start = validateInput($_POST['time_start']);
    $time_end = validateInput($_POST['time_end']);
    $days = isset($_POST['days']) ? $_POST['days'] : [];
    $location = isset($_POST['location']) ? validateInput($_POST['location']) : '';

    // Convert days array to JSON - more efficient storage
    $preferred_day = json_encode($days);

    // Validate dates
    $from_date = new DateTime($month_from);
    $to_date = new DateTime($month_to);
    if ($to_date < $from_date) {
        throw new Exception("End date must be after start date");
    }

    // Validate time
    if ($time_end <= $time_start) {
        throw new Exception("End time must be after start time");
    }

    // Validate days
    if (empty($days)) {
        throw new Exception("Please select at least one day");
    }

    // Start transaction with ISOLATION LEVEL to prevent conflicts
    $conn->query("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
    $conn->begin_transaction();

    // OPTIMIZATION 1: More efficient duplicate check using indexing
    $duplicate_check = $conn->prepare("SELECT id FROM assignments WHERE subject_id = ? LIMIT 1");
    if (!$duplicate_check) {
        throw new Exception("Error preparing duplicate check: " . $conn->error);
    }
    
    $duplicate_check->bind_param("i", $subject_id);
    if (!$duplicate_check->execute()) {
        throw new Exception("Error checking for duplicates: " . $duplicate_check->error);
    }
    
    $duplicate_result = $duplicate_check->get_result();
    if ($duplicate_result->num_rows > 0) {
        // Use efficient query to get subject details
        $subject_query = "SELECT subject_code, subject_name FROM subjects WHERE id = ? LIMIT 1";
        $subject_stmt = $conn->prepare($subject_query);
        $subject_stmt->bind_param("i", $subject_id);
        $subject_stmt->execute();
        $subject_result = $subject_stmt->get_result();
        $subject = $subject_result->fetch_assoc();
        
        $conn->rollback();
        throw new Exception("This subject ({$subject['subject_code']} - {$subject['subject_name']}) is already assigned to another teacher.");
    }

    // OPTIMIZATION 2: More efficient conflict check
    // Use prepared statement with parameter binding and improved query
    $conflict_check = $conn->prepare("
        SELECT a.id, a.time_start, a.time_end, s.subject_name, t.first_name as firstname, t.last_name as lastname, a.preferred_day
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.id
        JOIN teachers t ON a.teacher_id = t.id
        WHERE a.teacher_id = ? 
        AND ((a.month_from <= ? AND a.month_to >= ?) 
             OR (a.month_from <= ? AND a.month_to >= ?) 
             OR (a.month_from >= ? AND a.month_to <= ?))
        ORDER BY a.id
    ");

    if (!$conflict_check) {
        throw new Exception("Error preparing conflict check: " . $conn->error);
    }

    $conflict_check->bind_param("issssss", 
        $teacher_id, 
        $month_to,    // End of new assignment overlaps with existing
        $month_to,
        $month_from,  // Start of new assignment overlaps with existing
        $month_from,
        $month_from,  // New assignment completely contains existing
        $month_to
    );

    if (!$conflict_check->execute()) {
        throw new Exception("Error executing conflict check: " . $conflict_check->error);
    }

    $conflict_result = $conflict_check->get_result();

    $conflicts = [];
    while ($row = $conflict_result->fetch_assoc()) {
        // Check for day conflicts more efficiently
        $conflict_days = json_decode($row['preferred_day'], true);
        if (!is_array($conflict_days)) {
            // Handle legacy ENUM format
            $conflict_days = [$row['preferred_day']];
        }
        
        $day_conflict = false;
        foreach ($days as $day) {
            if (in_array($day, $conflict_days)) {
                $day_conflict = true;
                break;
            }
        }

        if ($day_conflict) {
            // Check time overlap with efficient comparison
            if (
                ($time_start >= $row['time_start'] && $time_start < $row['time_end']) ||
                ($time_end > $row['time_start'] && $time_end <= $row['time_end']) ||
                ($time_start <= $row['time_start'] && $time_end >= $row['time_end'])
            ) {
                // Only add necessary information to the conflicts array
                $conflicts[] = [
                    'firstname' => $row['firstname'],
                    'lastname' => $row['lastname'],
                    'subject_name' => $row['subject_name'],
                    'time_start' => $row['time_start'],
                    'time_end' => $row['time_end'],
                    'preferred_day' => $row['preferred_day']
                ];
            }
        }
    }

    if (!empty($conflicts)) {
        // Rollback the transaction
        $conn->rollback();
        
        // Format conflict message
        $conflict_message = "Schedule Conflict Detected!\n\n";
        foreach ($conflicts as $conflict) {
            $conflict_days = json_decode($conflict['preferred_day'], true);
            if (!is_array($conflict_days)) {
                $conflict_days = [$conflict['preferred_day']];
            }
            
            $conflict_message .= sprintf(
                "Teacher: %s %s\nSubject: %s\nTime: %s - %s\nDays: %s\n\n",
                $conflict['firstname'],
                $conflict['lastname'],
                $conflict['subject_name'],
                date('h:i A', strtotime($conflict['time_start'])),
                date('h:i A', strtotime($conflict['time_end'])),
                implode(', ', $conflict_days)
            );
        }
        
        echo json_encode([
            'success' => false,
            'message' => $conflict_message
        ]);
        exit;
    }

    // OPTIMIZATION 3: More efficient assignment insert with created_at timestamp
    $insert_query = "INSERT INTO assignments 
                    (subject_id, teacher_id, month_from, month_to, time_start, time_end, location, preferred_day, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    if (!$insert_stmt) {
        throw new Exception("Error preparing assignment insert: " . $conn->error);
    }
    
    $insert_stmt->bind_param("iissssss", 
        $subject_id, 
        $teacher_id, 
        $month_from, 
        $month_to, 
        $time_start, 
        $time_end, 
        $location, 
        $preferred_day
    );
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Error creating assignment: " . $insert_stmt->error);
    }

    $assignment_id = $conn->insert_id;

    // OPTIMIZATION 4: Create notification with a simple query
    $notification_message = "New assignment created";
    $notification_query = "INSERT INTO notifications (teacher_id, message, created_at) VALUES (?, ?, NOW())";
    $notification_stmt = $conn->prepare($notification_query);
    if (!$notification_stmt) {
        throw new Exception("Error preparing notification statement: " . $conn->error);
    }
    $notification_stmt->bind_param("is", $teacher_id, $notification_message);
    
    if (!$notification_stmt->execute()) {
        throw new Exception("Error creating notification: " . $notification_stmt->error);
    }

    // Commit the transaction
    if (!$conn->commit()) {
        throw new Exception("Error committing transaction: " . $conn->error);
    }

    // Clear output buffer
    ob_clean();
    
    $end_time = microtime(true);
    $time_taken = round(($end_time - $start_time) * 1000, 2); // in ms

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Assignment created successfully',
        'processing_time' => $time_taken . 'ms' // Include processing time for debugging
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }

    // Clear output buffer
    ob_clean();

    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}
?> 