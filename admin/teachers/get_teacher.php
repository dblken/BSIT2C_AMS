<?php
session_start();
require_once '../../config/database.php';
header('Content-Type: application/json');

// Helper function to format day name
function getDayName($day) {
    $days = [
        '0' => 'Sunday',
        '1' => 'Monday',
        '2' => 'Tuesday',
        '3' => 'Wednesday',
        '4' => 'Thursday',
        '5' => 'Friday',
        '6' => 'Saturday',
    ];
    
    if (is_numeric($day) && isset($days[$day])) {
        return $days[$day];
    }
    
    return $day;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Prepare the SQL statement for teacher data
    $stmt = $conn->prepare("SELECT t.*, u.username 
            FROM teachers t 
            LEFT JOIN users u ON t.user_id = u.id 
            WHERE t.id = ?");
    
    // Bind the parameter
    $stmt->bind_param("i", $id);
    
    // Execute the statement
    $stmt->execute();
    
    // Get the result
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $teacher = $result->fetch_assoc();
        
        // Get count of subjects taught by this teacher
        $subjectsQuery = "SELECT COUNT(DISTINCT subject_id) AS subject_count 
                         FROM assignments 
                         WHERE teacher_id = ?";
        $subjectsStmt = $conn->prepare($subjectsQuery);
        $subjectsStmt->bind_param("i", $id);
        $subjectsStmt->execute();
        $subjectsResult = $subjectsStmt->get_result();
        $subjectsCount = $subjectsResult->fetch_assoc()['subject_count'] ?? 0;
        
        // Get count of classes (schedules) taught by this teacher
        $classesQuery = "SELECT COUNT(*) AS class_count 
                        FROM timetable t
                        JOIN assignments a ON (
                            t.subject_id = a.subject_id AND 
                            t.time_start = a.time_start AND 
                            t.time_end = a.time_end AND
                            (t.day = a.preferred_day OR JSON_CONTAINS(a.preferred_day, CONCAT('\"', CASE 
                                WHEN t.day = 0 THEN 'Sunday'
                                WHEN t.day = 1 THEN 'Monday'
                                WHEN t.day = 2 THEN 'Tuesday'
                                WHEN t.day = 3 THEN 'Wednesday'
                                WHEN t.day = 4 THEN 'Thursday'
                                WHEN t.day = 5 THEN 'Friday'
                                WHEN t.day = 6 THEN 'Saturday'
                                ELSE CAST(t.day AS CHAR)
                            END, '\"')))
                        )
                        WHERE a.teacher_id = ?";
        $classesStmt = $conn->prepare($classesQuery);
        $classesStmt->bind_param("i", $id);
        $classesStmt->execute();
        $classesResult = $classesStmt->get_result();
        $classesCount = $classesResult->fetch_assoc()['class_count'] ?? 0;
        
        // Get count of students enrolled in subjects taught by this teacher
        $studentsQuery = "SELECT COUNT(DISTINCT e.student_id) AS student_count 
                         FROM enrollments e
                         JOIN timetable t ON e.schedule_id = t.id
                         JOIN assignments a ON (
                             t.subject_id = a.subject_id AND 
                             t.time_start = a.time_start AND 
                             t.time_end = a.time_end AND
                             (t.day = a.preferred_day OR JSON_CONTAINS(a.preferred_day, CONCAT('\"', CASE 
                                 WHEN t.day = 0 THEN 'Sunday'
                                 WHEN t.day = 1 THEN 'Monday'
                                 WHEN t.day = 2 THEN 'Tuesday'
                                 WHEN t.day = 3 THEN 'Wednesday'
                                 WHEN t.day = 4 THEN 'Thursday'
                                 WHEN t.day = 5 THEN 'Friday'
                                 WHEN t.day = 6 THEN 'Saturday'
                                 ELSE CAST(t.day AS CHAR)
                             END, '\"')))
                         )
                         WHERE a.teacher_id = ?";
        $studentsStmt = $conn->prepare($studentsQuery);
        $studentsStmt->bind_param("i", $id);
        $studentsStmt->execute();
        $studentsResult = $studentsStmt->get_result();
        $studentsCount = $studentsResult->fetch_assoc()['student_count'] ?? 0;
        
        // Get teacher's assignments with subject details
        $assignmentsQuery = "SELECT a.id, s.subject_code, s.subject_name, a.preferred_day, 
                           a.time_start, a.time_end, a.location,
                           (SELECT COUNT(DISTINCT e.student_id) 
                            FROM enrollments e 
                            JOIN timetable tt ON e.schedule_id = tt.id 
                            WHERE tt.subject_id = s.id) AS enrolled_students
                           FROM assignments a
                           JOIN subjects s ON a.subject_id = s.id
                           WHERE a.teacher_id = ?
                           ORDER BY s.subject_code";
        $assignmentsStmt = $conn->prepare($assignmentsQuery);
        $assignmentsStmt->bind_param("i", $id);
        $assignmentsStmt->execute();
        $assignmentsResult = $assignmentsStmt->get_result();
        
        $assignments = [];
        while ($row = $assignmentsResult->fetch_assoc()) {
            // Format the schedule info
            $days = json_decode($row['preferred_day'], true);
            if (is_array($days)) {
                $dayNames = array_map('getDayName', $days);
                $formattedDays = implode(', ', $dayNames);
            } else {
                $formattedDays = getDayName($row['preferred_day']);
            }
            
            $timeStart = date('h:i A', strtotime($row['time_start']));
            $timeEnd = date('h:i A', strtotime($row['time_end']));
            
            $row['formatted_schedule'] = $formattedDays . ', ' . $timeStart . ' - ' . $timeEnd;
            $row['formatted_location'] = $row['location'] ?: 'TBA';
            
            $assignments[] = $row;
        }
        
        // Add counts and assignments to teacher data
        $teacher['subject_count'] = $subjectsCount;
        $teacher['class_count'] = $classesCount;
        $teacher['student_count'] = $studentsCount;
        $teacher['assignments'] = $assignments;
        
        echo json_encode([
            'success' => true,
            'teacher' => $teacher
        ]);
        
        // Close statements
        $subjectsStmt->close();
        $classesStmt->close();
        $studentsStmt->close();
        $assignmentsStmt->close();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Teacher not found'
        ]);
    }
    
    $stmt->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No ID provided'
    ]);
}

$conn->close();
?>