<?php
require_once '../config/database.php';

if (isset($_GET['teacher_id']) && isset($_GET['subject_id'])) {
    $teacher_id = (int)$_GET['teacher_id'];
    $subject_id = (int)$_GET['subject_id'];

    // Optimized query - directly joining with subjects table instead of using subquery
    $sql = "SELECT COUNT(*) as count 
            FROM timetable t
            JOIN subjects s ON t.subject_id = s.id 
            WHERE s.teacher_id = ? AND t.subject_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $teacher_id, $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_classes = $result->fetch_assoc()['count'];

    header('Content-Type: application/json');
    echo json_encode(['current_classes' => $current_classes]);
}
?> 