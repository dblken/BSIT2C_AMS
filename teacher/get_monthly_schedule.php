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

        // Helper function to get the day name from a code
        function getDayName($day_code) {
            $days = [
                'M' => 'Monday',
                'T' => 'Tuesday',
                'W' => 'Wednesday',
                'TH' => 'Thursday',
                'F' => 'Friday',
                'SAT' => 'Saturday',
                'SUN' => 'Sunday',
                '1' => 'Monday',
                '2' => 'Tuesday',
                '3' => 'Wednesday',
                '4' => 'Thursday',
                '5' => 'Friday',
                '6' => 'Saturday',
                '7' => 'Sunday'
            ];
            
            return $days[$day_code] ?? $day_code;
        }

        // Group schedules by date
        while ($row = mysqli_fetch_assoc($result)) {
            // Calculate all dates for this assignment in the month
            $start_date = new DateTime($row['month_from']);
            $end_date = new DateTime($row['month_to']);
            $interval = new DateInterval('P1D');
            $date_range = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));

            // Check if preferred_day is in JSON format
            $preferred_days = @json_decode($row['preferred_day'], true);
            
            if (is_array($preferred_days)) {
                // Use our new formatDays function
                $day_names = [formatDays($row['preferred_day'])];
            } else {
                $day_names = [getDayName($row['preferred_day'])];
            }
            
            foreach ($date_range as $date) {
                $month_year_match = $date->format('Y-m') == "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT);
                $date_day_name = $date->format('l');
                
                if ($month_year_match && in_array($date_day_name, $day_names)) {
                    $schedule[$date->format('Y-m-d')][] = [
                        'id' => $row['id'],
                        'subject_id' => $row['subject_id'],
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