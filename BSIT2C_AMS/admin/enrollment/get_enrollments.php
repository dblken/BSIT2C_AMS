<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (isset($_GET['student_id'])) {
    try {
        $student_id = $_GET['student_id'];
        
        // First, check what columns are available in the timetable
        $timetable_structure = $conn->query("DESCRIBE timetable");
        $day_column = null;
        $room_column = null;
        
        while ($col = $timetable_structure->fetch_assoc()) {
            if (in_array($col['Field'], ['day', 'preferred_day', 'day_of_week'])) {
                $day_column = $col['Field'];
            }
            if (in_array($col['Field'], ['room', 'location'])) {
                $room_column = $col['Field'];
            }
        }
        
        if (!$day_column) $day_column = 'day'; // Default fallback
        if (!$room_column) $room_column = 'room'; // Default fallback
        
        // Build the query with the correct column names
        $query = "SELECT 
            e.id,
            s.subject_code,
            s.subject_name,
            tt.{$day_column} as day_value,
            tt.start_time,
            tt.end_time,
            tt.{$room_column} as location_value,
            a.id as assignment_id,
            a.location as assignment_location,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name
            FROM enrollments e
            JOIN timetable tt ON e.schedule_id = tt.id
            JOIN subjects s ON tt.subject_id = s.id
            LEFT JOIN assignments a ON (a.subject_id = s.id 
                                   AND a.preferred_day = tt.{$day_column}
                                   AND a.time_start = tt.start_time 
                                   AND a.time_end = tt.end_time)
            LEFT JOIN teachers t ON a.teacher_id = t.id
            WHERE e.student_id = ?
            ORDER BY tt.{$day_column}, tt.start_time";
            
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $enrollments = [];
        while ($row = $result->fetch_assoc()) {
            $day_value = $row['day_value'] ?? '';
            
            $day_name = [
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
            ][$day_value] ?? $day_value;
            
            // Prefer assignment location if available, fall back to timetable location
            $location = !empty($row['assignment_location']) ? $row['assignment_location'] : 
                      (!empty($row['location_value']) ? $row['location_value'] : 'TBA');
            
            $enrollments[] = [
                'id' => $row['id'],
                'subject_code' => $row['subject_code'],
                'subject_name' => $row['subject_name'],
                'teacher' => $row['teacher_name'] ?: 'Not Assigned',
                'schedule' => $day_name . ', ' . 
                            date('h:i A', strtotime($row['start_time'])) . ' - ' .
                            date('h:i A', strtotime($row['end_time'])),
                'location' => $location
            ];
        }
        
        echo json_encode([
            'success' => true,
            'enrollments' => $enrollments
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error loading enrollments: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Student ID is required'
    ]);
}
?> 