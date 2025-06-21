<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $teacher_id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
        $subject_id = mysqli_real_escape_string($conn, $_POST['subject_id']);
        $month_from = mysqli_real_escape_string($conn, $_POST['month_from']);
        $month_to = mysqli_real_escape_string($conn, $_POST['month_to']);
        $preferred_day = mysqli_real_escape_string($conn, $_POST['preferred_day']);
        $time_start = mysqli_real_escape_string($conn, $_POST['time_start']);
        $time_end = mysqli_real_escape_string($conn, $_POST['time_end']);
        $location = mysqli_real_escape_string($conn, $_POST['location']);

        // Validate dates
        if (strtotime($month_to) < strtotime($month_from)) {
            throw new Exception('End date cannot be earlier than start date');
        }

        // Validate times
        if (strtotime($time_end) <= strtotime($time_start)) {
            throw new Exception('End time must be later than start time');
        }

        // Check for schedule conflicts
        $conflict_query = "SELECT * FROM assignments 
                          WHERE teacher_id = '$teacher_id' 
                          AND preferred_day = '$preferred_day'
                          AND ((time_start BETWEEN '$time_start' AND '$time_end')
                          OR (time_end BETWEEN '$time_start' AND '$time_end'))";
        
        $conflict_result = mysqli_query($conn, $conflict_query);
        if (mysqli_num_rows($conflict_result) > 0) {
            throw new Exception('Schedule conflict detected for this teacher');
        }

        $query = "INSERT INTO assignments (
            teacher_id, subject_id, month_from, month_to,
            preferred_day, time_start, time_end, location
        ) VALUES (
            '$teacher_id', '$subject_id', '$month_from', '$month_to',
            '$preferred_day', '$time_start', '$time_end', '$location'
        )";

        if (mysqli_query($conn, $query)) {
            // Add this after successful assignment creation
            $teacher_name_query = "SELECT CONCAT(first_name, ' ', last_name) as teacher_name 
                                  FROM teachers WHERE id = '$teacher_id'";
            $subject_name_query = "SELECT subject_code, subject_name FROM subjects WHERE id = '$subject_id'";

            $teacher_result = mysqli_query($conn, $teacher_name_query);
            $subject_result = mysqli_query($conn, $subject_name_query);

            $teacher = mysqli_fetch_assoc($teacher_result);
            $subject = mysqli_fetch_assoc($subject_result);

            // Create notification
            $notification_message = "You have been assigned to teach {$subject['subject_code']} - {$subject['subject_name']} 
                                    every {$preferred_day} from " . date('h:i A', strtotime($time_start)) . 
                                    " to " . date('h:i A', strtotime($time_end)) . 
                                    " at {$location}";

            $notification_query = "INSERT INTO notifications (teacher_id, message) 
                                  VALUES ('$teacher_id', '$notification_message')";
            mysqli_query($conn, $notification_query);

            echo json_encode(['success' => true]);
        } else {
            throw new Exception(mysqli_error($conn));
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?> 