<?php
require_once '../config/database.php';

if (isset($_GET['teacher_id']) && isset($_GET['subject_id'])) {
    $teacher_id = (int)$_GET['teacher_id'];
    $subject_id = (int)$_GET['subject_id'];

    // Get current weekly classes for this teacher-subject combination
    $sql = "SELECT COUNT(*) as count 
            FROM timetable 
            WHERE subject_id IN (SELECT id FROM subjects WHERE teacher_id = ?) 
            AND subject_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $teacher_id, $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_classes = $result->fetch_assoc()['count'];

    header('Content-Type: application/json');
    echo json_encode(['current_classes' => $current_classes]);
}
?> 