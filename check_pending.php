<?php
require_once 'config/database.php';

// Check for pending records
$query = "SELECT COUNT(*) as count FROM attendance WHERE is_pending = 1";
$result = $conn->query($query);
$row = $result->fetch_assoc();
echo 'Total pending records: ' . $row['count'] . PHP_EOL;

// Create a test pending record if none exist
if ($row['count'] == 0) {
    echo "No pending records found. Creating a test record...\n";
    
    // Get a teacher ID
    $teacher_query = "SELECT id FROM teachers LIMIT 1";
    $teacher_result = $conn->query($teacher_query);
    $teacher_id = $teacher_result->fetch_assoc()['id'];
    
    // Get a subject ID
    $subject_query = "SELECT id FROM subjects LIMIT 1";
    $subject_result = $conn->query($subject_query);
    $subject_id = $subject_result->fetch_assoc()['id'];
    
    // Get an assignment ID
    $assignment_query = "SELECT id FROM assignments LIMIT 1";
    $assignment_result = $conn->query($assignment_query);
    $assignment_id = $assignment_result->fetch_assoc()['id'];
    
    // Insert a test pending record
    $insert_query = "INSERT INTO attendance (teacher_id, subject_id, assignment_id, attendance_date, is_pending, created_at) 
                     VALUES (?, ?, ?, CURDATE(), 1, NOW())";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("iii", $teacher_id, $subject_id, $assignment_id);
    
    if ($stmt->execute()) {
        echo "Test pending record created successfully!\n";
    } else {
        echo "Error creating test record: " . $stmt->error . "\n";
    }
    
    // Check again
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    echo 'Total pending records after test: ' . $row['count'] . PHP_EOL;
}

// Show pending records
$details_query = "SELECT a.id, a.teacher_id, t.first_name, t.last_name, s.subject_code, s.subject_name, a.attendance_date, a.is_pending 
                 FROM attendance a 
                 JOIN teachers t ON a.teacher_id = t.id
                 JOIN subjects s ON a.subject_id = s.id
                 WHERE a.is_pending = 1";
$details_result = $conn->query($details_query);

if ($details_result->num_rows > 0) {
    echo "\nPending records details:\n";
    echo "=======================\n";
    
    while ($row = $details_result->fetch_assoc()) {
        echo "ID: " . $row['id'] . "\n";
        echo "Teacher: " . $row['first_name'] . " " . $row['last_name'] . " (ID: " . $row['teacher_id'] . ")\n";
        echo "Subject: " . $row['subject_code'] . " - " . $row['subject_name'] . "\n";
        echo "Date: " . $row['attendance_date'] . "\n";
        echo "Is Pending: " . $row['is_pending'] . "\n";
        echo "-----------------------\n";
    }
}
?> 