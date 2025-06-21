<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $student_id = $_POST['student_id'];
        $assignment_ids = $_POST['assignment_ids'] ?? [];
        
        if (empty($assignment_ids)) {
            throw new Exception("Please select at least one subject");
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        $current_date = date('Y-m-d H:i:s');
        
        foreach ($assignment_ids as $assignment_id) {
            // First, check subject and teacher status
            $check_status = "SELECT 
                a.id,
                a.subject_id, 
                a.preferred_day,
                a.time_start,
                a.time_end,
                a.location,
                s.subject_code, 
                s.subject_name,
                t.status as teacher_status,
                CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                FROM assignments a 
                JOIN subjects s ON a.subject_id = s.id 
                JOIN teachers t ON a.teacher_id = t.id
                WHERE a.id = ?";
            $stmt = $conn->prepare($check_status);
            $stmt->bind_param("i", $assignment_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if (!$result) {
                continue; // Skip if assignment not found
            }

            // Check teacher status
            if ($result['teacher_status'] !== 'Active') {
                throw new Exception("Cannot enroll in " . 
                    $result['subject_code'] . " - " . 
                    $result['subject_name'] . 
                    " because teacher " . $result['teacher_name'] . 
                    " is currently " . strtolower($result['teacher_status']));
            }

            // Check if student is already enrolled in this subject
            $check_subject = "SELECT e.id 
                            FROM enrollments e 
                            JOIN timetable tt ON e.schedule_id = tt.id 
                            WHERE e.student_id = ? AND tt.subject_id = ?";
            $stmt = $conn->prepare($check_subject);
            $stmt->bind_param("ii", $student_id, $result['subject_id']);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Student is already enrolled in " . 
                    $result['subject_code'] . " - " . 
                    $result['subject_name']);
            }
            
            // Get the timetable structure
            $timetable_cols = $conn->query("DESCRIBE timetable");
            $columns = [];
            while ($col = $timetable_cols->fetch_assoc()) {
                $columns[] = $col['Field'];
            }
            
            // Find the day column
            $day_column = null;
            if (in_array('preferred_day', $columns)) {
                $day_column = 'preferred_day';
            } elseif (in_array('day', $columns)) {
                $day_column = 'day';
            } elseif (in_array('day_of_week', $columns)) {
                $day_column = 'day_of_week';
            }
            
            // Room/location column
            $room_field = null;
            if (in_array('room', $columns)) {
                $room_field = 'room';
            } elseif (in_array('location', $columns)) {
                $room_field = 'location';
            }
            
            // Location value from assignment
            $location_value = $result['location'] ?? 'TBA';
            
            // Check if a matching timetable entry exists
            $check_timetable_sql = "SELECT id FROM timetable WHERE subject_id = ?";
            $check_timetable_params = [$result['subject_id']];
            $check_timetable_types = "i";
            
            if ($day_column) {
                $check_timetable_sql .= " AND {$day_column} = ?";
                $check_timetable_params[] = $result['preferred_day'];
                $check_timetable_types .= "s";
            }
            
            $check_timetable_sql .= " AND start_time = ? AND end_time = ?";
            $check_timetable_params[] = $result['time_start'];
            $check_timetable_params[] = $result['time_end'];
            $check_timetable_types .= "ss";
            
            if ($room_field) {
                $check_timetable_sql .= " AND {$room_field} = ?";
                $check_timetable_params[] = $location_value;
                $check_timetable_types .= "s";
            }
            
            $stmt = $conn->prepare($check_timetable_sql);
            $stmt->bind_param($check_timetable_types, ...$check_timetable_params);
            $stmt->execute();
            $timetable_result = $stmt->get_result();
            
            if ($timetable_result->num_rows > 0) {
                // Use existing timetable entry
                $timetable_id = $timetable_result->fetch_assoc()['id'];
            } else {
                // Create a new timetable entry
                $insert_fields = ["subject_id", "start_time", "end_time"];
                $insert_values = ["?", "?", "?"];
                $insert_params = [$result['subject_id'], $result['time_start'], $result['time_end']];
                $insert_types = "iss";
                
                if ($day_column) {
                    $insert_fields[] = $day_column;
                    $insert_values[] = "?";
                    $insert_params[] = $result['preferred_day'];
                    $insert_types .= "s";
                }
                
                if ($room_field) {
                    $insert_fields[] = $room_field;
                    $insert_values[] = "?";
                    $insert_params[] = $location_value;
                    $insert_types .= "s";
                }
                
                // Add created_at if the column exists
                if (in_array('created_at', $columns)) {
                    $insert_fields[] = "created_at";
                    $insert_values[] = "?";
                    $insert_params[] = $current_date;
                    $insert_types .= "s";
                }
                
                $insert_timetable = "INSERT INTO timetable (" . implode(", ", $insert_fields) . ") 
                                   VALUES (" . implode(", ", $insert_values) . ")";
                
                $stmt = $conn->prepare($insert_timetable);
                $stmt->bind_param($insert_types, ...$insert_params);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to create timetable entry: " . $stmt->error);
                }
                
                $timetable_id = $conn->insert_id;
                
                if (!$timetable_id) {
                    throw new Exception("Failed to get timetable ID");
                }
            }
            
            // Insert into enrollments using the timetable_id
            $insert_enrollment = "INSERT INTO enrollments 
                                (student_id, schedule_id, enrollment_date, created_at, updated_at)
                                VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_enrollment);
            $stmt->bind_param("iisss", 
                $student_id, 
                $timetable_id,
                $current_date,
                $current_date,
                $current_date
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create enrollment record: " . $stmt->error);
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Student successfully enrolled in selected subjects'
        ]);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}