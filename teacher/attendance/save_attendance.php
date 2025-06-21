<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$date = $_POST['date'];
$schedule_id = $_POST['schedule_id'];
$statuses = $_POST['status'];
$remarks = $_POST['remarks'];

$conn->begin_transaction();

try {
    // Delete existing attendance records for this date and schedule
    $delete_query = "DELETE a FROM attendance a 
                     JOIN enrollments e ON a.enrollment_id = e.enrollment_id 
                     WHERE e.schedule_id = ? AND a.date = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("is", $schedule_id, $date);
    $stmt->execute();

    // Insert new attendance records
    $insert_query = "INSERT INTO attendance (enrollment_id, date, status, remarks) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);

    foreach ($statuses as $enrollment_id => $status) {
        $remark = $remarks[$enrollment_id] ?? '';
        $stmt->bind_param("isss", $enrollment_id, $date, $status, $remark);
        $stmt->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 