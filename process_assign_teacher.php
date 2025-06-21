<?php
session_start();
require_once 'config/database.php';

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
    // Set transaction isolation level to READ COMMITTED for better performance
    mysqli_query($conn, "SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
    
    // Start transaction with a shorter lock timeout
    mysqli_query($conn, "SET innodb_lock_wait_timeout=10");
    mysqli_begin_transaction($conn);

    // Check if subject is already assigned - use FORCE INDEX to ensure using the proper index
    $check_query = "SELECT id FROM teacher_subjects 
                   WHERE subject_id = ? AND status = 'Active'
                   LIMIT 1";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "i", $subject_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        throw new Exception("This subject is already assigned to a teacher");
    }

    // Get teacher and subject data with direct joins instead of subqueries for better performance
    $info_query = "SELECT t.first_name, t.last_name, s.subject_name 
                  FROM teachers t, subjects s 
                  WHERE t.id = ? AND s.id = ? 
                  LIMIT 1";
    $stmt = mysqli_prepare($conn, $info_query);
    mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $subject_id);
    mysqli_stmt_execute($stmt);
    $info_result = mysqli_stmt_get_result($stmt);
    $info_data = mysqli_fetch_assoc($info_result);
    
    $teacher_name = $info_data['first_name'] . ' ' . $info_data['last_name'];
    $subject_name = $info_data['subject_name'];

    // Insert into teacher_subjects table with only necessary fields
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
        'message' => "Successfully assigned {$teacher_name} to teach {$subject_name}"
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    mysqli_close($conn);
}
?> 