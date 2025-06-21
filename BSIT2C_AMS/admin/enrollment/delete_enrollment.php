<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

// Check if request is POST and has valid JSON
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['enrollment_id']) || !is_numeric($data['enrollment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid enrollment ID']);
    exit;
}

$enrollment_id = $data['enrollment_id'];

// Check if enrollment exists
$check_query = "SELECT * FROM enrollments WHERE enrollment_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("i", $enrollment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Enrollment not found']);
    exit;
}

// Delete the enrollment
$delete_query = "DELETE FROM enrollments WHERE enrollment_id = ?";
$stmt = $conn->prepare($delete_query);
$stmt->bind_param("i", $enrollment_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Enrollment deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete enrollment']);
}

$stmt->close();
$conn->close();