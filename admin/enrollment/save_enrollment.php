<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if required data is provided
if (!isset($_POST['student_id']) || empty($_POST['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit();
}

$student_id = intval($_POST['student_id']);

// Check if at least one subject is selected
if (!isset($_POST['subject_schedule']) || !is_array($_POST['subject_schedule']) || count($_POST['subject_schedule']) === 0) {
    echo json_encode(['success' => false, 'message' => 'Please select at least one subject']);
    exit();
}

// Verify that the student exists
$student_check = $conn->prepare("SELECT id FROM students WHERE id = ? AND status = 'Active'");
$student_check->bind_param("i", $student_id);
$student_check->execute();
$student_result = $student_check->get_result();

if ($student_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Student not found or inactive']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    $assignment_ids = $_POST['subject_schedule'];
    $enrolled_count = 0;
    $errors = [];
    
    foreach ($assignment_ids as $assignment_id) {
        $assignment_id = intval($assignment_id);
        
        // Get assignment details
        $assignment_query = $conn->prepare("
            SELECT a.id, a.subject_id, s.subject_code 
            FROM assignments a
            JOIN subjects s ON a.subject_id = s.id
            WHERE a.id = ?
        ");
        $assignment_query->bind_param("i", $assignment_id);
        $assignment_query->execute();
        $assignment_result = $assignment_query->get_result();
        
        if ($assignment_result->num_rows === 0) {
            $errors[] = "Assignment ID {$assignment_id} not found";
            continue;
        }
        
        $assignment = $assignment_result->fetch_assoc();
        $subject_id = $assignment['subject_id'];
        
        // Check if student is already enrolled in this subject
        $check_enrollment = $conn->prepare("
            SELECT id FROM enrollments 
            WHERE student_id = ? AND subject_id = ?
        ");
        $check_enrollment->bind_param("ii", $student_id, $subject_id);
        $check_enrollment->execute();
        $check_result = $check_enrollment->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Student is already enrolled in {$assignment['subject_code']}";
            continue;
        }
        
        // Get the timetable ID for this assignment
        $timetable_query = $conn->prepare("
            SELECT id FROM timetable 
            WHERE assignment_id = ? 
            LIMIT 1
        ");
        $timetable_query->bind_param("i", $assignment_id);
        $timetable_query->execute();
        $timetable_result = $timetable_query->get_result();
        
        if ($timetable_result->num_rows === 0) {
            $errors[] = "No timetable entry found for {$assignment['subject_code']}";
            continue;
        }
        
        $timetable = $timetable_result->fetch_assoc();
        $schedule_id = $timetable['id'];
        
        // Insert enrollment
        $insert = $conn->prepare("
            INSERT INTO enrollments 
            (student_id, subject_id, assignment_id, schedule_id, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $insert->bind_param("iiii", $student_id, $subject_id, $assignment_id, $schedule_id);
        
        if ($insert->execute()) {
            $enrolled_count++;
        } else {
            $errors[] = "Failed to enroll in {$assignment['subject_code']}: " . $conn->error;
        }
    }
    
    // Determine overall success
    if ($enrolled_count > 0) {
        $conn->commit();
        $message = "Successfully enrolled in {$enrolled_count} subject(s)";
        
        if (count($errors) > 0) {
            $message .= ". Some enrollments had issues: " . implode("; ", $errors);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'enrolled_count' => $enrolled_count
        ]);
    } else {
        $conn->rollback();
        echo json_encode([
            'success' => false, 
            'message' => "Failed to enroll in any subjects. " . implode("; ", $errors)
        ]);
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false, 
        'message' => "Error: " . $e->getMessage()
    ]);
}
?> 