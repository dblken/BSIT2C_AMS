<?php
require_once '../config/database.php';
session_start();
header('Content-Type: application/json');

if (isset($_GET['year']) && isset($_GET['month']) && isset($_SESSION['teacher_id'])) {
    try {
        $year = mysqli_real_escape_string($conn, $_GET['year']);
        $month = mysqli_real_escape_string($conn, $_GET['month']);
        $teacher_id = $_SESSION['teacher_id'];

        // Get all assignments for the month
        $query = "SELECT a.*, s.subject_code, s.subject_name 
                 FROM assignments a 
                 JOIN subjects s ON a.subject_id = s.id 
                 WHERE a.teacher_id = '$teacher_id' 
                 AND (
                     (YEAR(a.month_from) = $year AND MONTH(a.month_from) = $month)
                     OR 
                     (YEAR(a.month_to) = $year AND MONTH(a.month_to) = $month)
                     OR 
                     (a.month_from <= '$year-$month-01' AND a.month_to >= '$year-$month-31')
                 )
                 ORDER BY a.time_start";

        $result = mysqli_query($conn, $query);
        $schedule = [];

        // Group schedules by date
        while ($row = mysqli_fetch_assoc($result)) {
            // Calculate all dates for this assignment in the month
            $start_date = new DateTime($row['month_from']);
            $end_date = new DateTime($row['month_to']);
            $interval = new DateInterval('P1D');
            $date_range = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));

            foreach ($date_range as $date) {
                if ($date->format('Y-m') == "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) 
                    && $date->format('l') == $row['preferred_day']) {
                    $schedule[$date->format('Y-m-d')][] = [
                        'subject_code' => $row['subject_code'],
                        'subject_name' => $row['subject_name'],
                        'time_start' => date('h:i A', strtotime($row['time_start'])),
                        'time_end' => date('h:i A', strtotime($row['time_end'])),
                        'location' => $row['location']
                    ];
                }
            }
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