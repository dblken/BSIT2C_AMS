<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $response = ['success' => false, 'message' => ''];

    try {
        $teacher_id = (int)$_POST['teacher_id'];
        $subject_id = (int)$_POST['subject_id'];
        $weekly_classes = (int)$_POST['weekly_classes'];

        // Check weekly classes limit
        $sql = "SELECT COUNT(*) as count FROM timetable WHERE subject_id IN 
                (SELECT id FROM subjects WHERE teacher_id = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $current_classes = $stmt->get_result()->fetch_assoc()['count'];

        if (($current_classes + $weekly_classes) > 3) {
            throw new Exception("Teacher already has maximum allowed classes.");
        }

        $conn->begin_transaction();

        // Update subject with teacher assignment
        $sql = "UPDATE subjects SET teacher_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $teacher_id, $subject_id);
        $stmt->execute();

        // Insert into timetable
        $sql = "INSERT INTO timetable (subject_id, weekly_classes) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $subject_id, $weekly_classes);
        $stmt->execute();

        $conn->commit();
        $response['success'] = true;
        $response['message'] = "Teacher assigned successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = "Error: " . $e->getMessage();
    }

    echo json_encode($response);
    exit();
}
?> 