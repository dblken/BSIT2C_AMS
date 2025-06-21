<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$enrollment_id = $data['enrollment_id'];

// Check if there's any attendance record
$check_query = "SELECT COUNT(*) as count FROM attendance WHERE enrollment_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("i", $enrollment_id);
$stmt->execute();
$result = $stmt->get_result();
$has_attendance = $result->fetch_assoc()['count'] > 0;

if ($has_attendance) {
    // Soft delete - just update status to inactive
    $query = "UPDATE enrollments SET status = 'inactive' WHERE enrollment_id = ?";
} else {
    // Hard delete - no attendance records exist
    $query = "DELETE FROM enrollments WHERE enrollment_id = ?";
}

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $enrollment_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
} 