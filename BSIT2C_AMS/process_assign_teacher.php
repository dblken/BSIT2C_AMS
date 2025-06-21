<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get form data
$teacher_id = $_POST['teacher_id'];
$subject_id = $_POST['subject_id'];
$weekly_classes = $_POST['weekly_classes'];

// Validate inputs
if (empty($teacher_id) || empty($subject_id) || empty($weekly_classes)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

try {
    // Start transaction
    mysqli_begin_transaction($conn);

    // Check if subject is already assigned
    $check_query = "SELECT id FROM teacher_subjects 
                   WHERE subject_id = ? AND status = 'Active'";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "i", $subject_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        throw new Exception("This subject is already assigned to a teacher");
    }

    // Get teacher name
    $teacher_query = "SELECT CONCAT(first_name, ' ', last_name) as teacher_name 
                     FROM teachers WHERE id = ?";
    $stmt = mysqli_prepare($conn, $teacher_query);
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $teacher_result = mysqli_stmt_get_result($stmt);
    $teacher_data = mysqli_fetch_assoc($teacher_result);

    // Get subject name
    $subject_query = "SELECT subject_name FROM subjects WHERE id = ?";
    $stmt = mysqli_prepare($conn, $subject_query);
    mysqli_stmt_bind_param($stmt, "i", $subject_id);
    mysqli_stmt_execute($stmt);
    $subject_result = mysqli_stmt_get_result($stmt);
    $subject_data = mysqli_fetch_assoc($subject_result);

    // Insert into teacher_subjects table
    $insert_query = "INSERT INTO teacher_subjects (teacher_id, subject_id, weekly_classes, status) 
                    VALUES (?, ?, ?, 'Active')";
    $stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt, "iii", $teacher_id, $subject_id, $weekly_classes);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to assign teacher to subject");
    }

    // Commit transaction
    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => "Successfully assigned {$teacher_data['teacher_name']} to teach {$subject_data['subject_name']}"
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    mysqli_close($conn);
}
?> 