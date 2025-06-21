<?php
require_once '../config/database.php';
session_start();
header('Content-Type: application/json');

if (isset($_GET['week_start']) && isset($_SESSION['teacher_id'])) {
    try {
        $week_start = mysqli_real_escape_string($conn, $_GET['week_start']);
        $teacher_id = $_SESSION['teacher_id'];

        $query = "SELECT a.*, s.subject_code, s.subject_name 
                 FROM assignments a 
                 JOIN subjects s ON a.subject_id = s.id 
                 WHERE a.teacher_id = '$teacher_id' 
                 AND '$week_start' BETWEEN a.month_from AND a.month_to 
                 ORDER BY a.preferred_day, a.time_start";

        $result = mysqli_query($conn, $query);
        $schedule = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $schedule[] = [
                'subject_code' => $row['subject_code'],
                'subject_name' => $row['subject_name'],
                'preferred_day' => $row['preferred_day'],
                'time_start' => $row['time_start'],
                'time_end' => $row['time_end'],
                'location' => $row['location']
            ];
        }

        echo json_encode([
            'success' => true,
            'schedule' => $schedule
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?> 