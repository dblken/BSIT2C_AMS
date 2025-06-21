<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (isset($_GET['week_start']) && isset($_GET['teacher_id'])) {
    try {
        $week_start = mysqli_real_escape_string($conn, $_GET['week_start']);
        $teacher_id = mysqli_real_escape_string($conn, $_GET['teacher_id']);
        
        // Calculate week dates
        $start_date = new DateTime($week_start);
        $week_dates = [];
        for ($i = 0; $i < 6; $i++) {
            $date = clone $start_date;
            $date->modify("+$i days");
            $week_dates[] = $date->format('Y-m-d');
        }

        // Get schedules
        $schedule_query = "SELECT a.*, s.subject_code, s.subject_name 
                          FROM assignments a 
                          JOIN subjects s ON a.subject_id = s.id 
                          WHERE a.teacher_id = '$teacher_id' 
                          AND '$week_dates[0]' BETWEEN a.month_from AND a.month_to";
        
        $schedule_result = mysqli_query($conn, $schedule_query);
        $schedules = [];
        
        while ($row = mysqli_fetch_assoc($schedule_result)) {
            $schedules[] = [
                'preferred_day' => $row['preferred_day'],
                'subject_code' => $row['subject_code'],
                'subject_name' => $row['subject_name'],
                'location' => $row['location'],
                'time_start' => $row['time_start'],
                'time_end' => $row['time_end']
            ];
        }

        echo json_encode([
            'success' => true,
            'week_dates' => $week_dates,
            'schedules' => $schedules
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?> 