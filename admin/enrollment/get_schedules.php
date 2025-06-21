<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (isset($_GET['subject_id'])) {
    try {
        $subject_id = $_GET['subject_id'];
        
        $query = "SELECT 
            s.schedule_id,
            s.day,
            s.start_time,
            s.end_time,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name
            FROM schedules s
            JOIN teachers t ON s.teacher_id = t.id
            WHERE s.subject_id = ? AND s.status = 'Active'
            ORDER BY s.day, s.start_time";
            
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $schedules = [];
        while ($row = $result->fetch_assoc()) {
            $day_names = [
                1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
                4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'
            ];
            
            $schedules[] = [
                'schedule_id' => $row['schedule_id'],
                'day_name' => $day_names[$row['day']],
                'time' => date('h:i A', strtotime($row['start_time'])) . ' - ' . 
                         date('h:i A', strtotime($row['end_time'])),
                'teacher' => $row['teacher_name']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'schedules' => $schedules
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error loading schedules: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Subject ID is required'
    ]);
}
?> 