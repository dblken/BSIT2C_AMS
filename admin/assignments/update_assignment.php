<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $teacher_id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
        $weekly_classes = mysqli_real_escape_string($conn, $_POST['weekly_classes']);

        $query = "UPDATE timetable SET 
                  teacher_id = '$teacher_id',
                  weekly_classes = '$weekly_classes'
                  WHERE id = '$id'";

        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception(mysqli_error($conn));
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?> 