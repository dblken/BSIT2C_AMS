<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$enrollment_id = $_POST['enrollment_id'] ?? '';
$status = $_POST['status'] ?? '';

if (empty($enrollment_id) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$stmt = $conn->prepare("UPDATE enrollments SET status = ? WHERE enrollment_id = ?");
$stmt->bind_param("si", $status, $enrollment_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating status']);
} 