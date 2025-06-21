<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once 'config/database.php';

// HTML header
echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Timetable Entries</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { text-align: left; padding: 8px; border: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
    </style>
</head>
<body>
    <h1>Fix Timetable Entries</h1>";

try {
    // Check if timetable table exists
    $check_timetable = $conn->query("SHOW TABLES LIKE 'timetable'");
    if ($check_timetable->num_rows === 0) {
        // Create timetable table if it doesn't exist
        echo "<p>Creating timetable table...</p>";
        
        $create_timetable = "CREATE TABLE timetable (
            id INT PRIMARY KEY AUTO_INCREMENT,
            subject_id INT,
            teacher_id INT,
            day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            room VARCHAR(50) NOT NULL,
            status ENUM('Active', 'Cancelled', 'Rescheduled') DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (subject_id) REFERENCES subjects(id),
            FOREIGN KEY (teacher_id) REFERENCES teachers(id)
        )";
        
        if (!$conn->query($create_timetable)) {
            throw new Exception("Failed to create timetable table: " . $conn->error);
        }
        
        echo "<p class='success'>Timetable table created successfully.</p>";
    } else {
        echo "<p>Timetable table already exists.</p>";
    }
    
    // Get day number mapping for timetable
    $day_mapping = [
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
        'Saturday' => 6
    ];
    
    // Start transaction
    $conn->begin_transaction();
    
    // Get all assignments
    $assignments_query = "SELECT a.*, s.subject_code, s.subject_name 
                         FROM assignments a 
                         JOIN subjects s ON a.subject_id = s.id 
                         ORDER BY a.id";
    $assignments_result = $conn->query($assignments_query);
    
    if (!$assignments_result) {
        throw new Exception("Error fetching assignments: " . $conn->error);
    }
    
    // Delete all existing timetable entries
    $conn->query("DELETE FROM timetable");
    echo "<p>Deleted existing timetable entries.</p>";
    
    echo "<h2>Creating timetable entries for assignments...</h2>";
    echo "<table>
        <tr>
            <th>Assignment ID</th>
            <th>Subject</th>
            <th>Teacher</th>
            <th>Days</th>
            <th>Status</th>
        </tr>";
    
    $total_count = 0;
    $processed_count = 0;
    $error_count = 0;
    
    // Process each assignment
    while ($assignment = $assignments_result->fetch_assoc()) {
        $total_count++;
        $assignment_id = $assignment['id'];
        $teacher_id = $assignment['teacher_id'];
        $subject_id = $assignment['subject_id'];
        $time_start = $assignment['time_start'];
        $time_end = $assignment['time_end'];
        $location = $assignment['location'] ?: 'TBA';
        $subject_name = $assignment['subject_code'] . ' - ' . $assignment['subject_name'];
        
        // Get teacher name
        $teacher_query = "SELECT CONCAT(first_name, ' ', last_name) as teacher_name FROM teachers WHERE id = ?";
        $teacher_stmt = $conn->prepare($teacher_query);
        $teacher_stmt->bind_param("i", $teacher_id);
        $teacher_stmt->execute();
        $teacher_result = $teacher_stmt->get_result();
        $teacher_name = $teacher_result->fetch_assoc()['teacher_name'] ?? 'Unknown';
        
        echo "<tr>
            <td>{$assignment_id}</td>
            <td>{$subject_name}</td>
            <td>{$teacher_name}</td>";
            
        // Process preferred days
        $preferred_days = json_decode($assignment['preferred_day'], true);
        $days_text = '';
        $status_class = 'success';
        $status_text = 'Success';
        
        if (!is_array($preferred_days) || empty($preferred_days)) {
            $days_text = 'Invalid days format';
            $status_class = 'error';
            $status_text = 'Error: Invalid days format';
            $error_count++;
        } else {
            $days_processed = 0;
            $days_text = implode(', ', $preferred_days);
            
            foreach ($preferred_days as $day) {
                $day_number = $day_mapping[$day] ?? null;
                $day_value = isset($day_number) ? $day_number : $day;
                
                // Insert timetable entry
                $insert_timetable = $conn->prepare("
                    INSERT INTO timetable (subject_id, teacher_id, day_of_week, start_time, end_time, room, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'Active')
                ");
                
                $insert_timetable->bind_param(
                    "iissss", 
                    $subject_id,
                    $teacher_id,
                    $day_value,
                    $time_start,
                    $time_end,
                    $location
                );
                
                if (!$insert_timetable->execute()) {
                    $status_class = 'error';
                    $status_text = 'Error: ' . $insert_timetable->error;
                    $error_count++;
                    break;
                }
                
                $days_processed++;
            }
            
            if ($days_processed > 0) {
                $processed_count++;
            }
        }
        
        echo "<td>{$days_text}</td>
            <td class='{$status_class}'>{$status_text}</td>
        </tr>";
    }
    
    echo "</table>";
    
    // Commit the transaction
    $conn->commit();
    
    echo "<h2>Summary</h2>";
    echo "<p>Total assignments: {$total_count}</p>";
    echo "<p class='success'>Successfully processed: {$processed_count}</p>";
    
    if ($error_count > 0) {
        echo "<p class='error'>Errors encountered: {$error_count}</p>";
    }
    
    echo "<h2>Next Steps</h2>";
    echo "<p class='success'>The timetable entries have been created successfully. You should now be able to enroll students in these subjects.</p>";
    echo "<p>You can now <a href='admin/enrollment/index.php' id='returnLink'>return to the enrollment page</a> and enroll students.</p>";
    echo "<script>
        // Check if we were opened from the enrollment page
        if (window.opener && !window.opener.closed) {
            // Add an auto-redirect with countdown
            let countdown = 5;
            const countdownElement = document.createElement('p');
            countdownElement.className = 'warning';
            countdownElement.innerHTML = `Automatically returning to enrollment page in <span id='countdown'>${countdown}</span> seconds...`;
            document.body.appendChild(countdownElement);
            
            const interval = setInterval(() => {
                countdown--;
                document.getElementById('countdown').textContent = countdown;
                if (countdown <= 0) {
                    clearInterval(interval);
                    // Refresh the opener window and close this one
                    window.opener.location.reload();
                    window.close();
                }
            }, 1000);
        }
    </script>";
    
} catch (Exception $e) {
    // Rollback on error
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    echo "<div class='error'>
        <h3>Error</h3>
        <p>{$e->getMessage()}</p>
        <pre>{$e->getTraceAsString()}</pre>
    </div>";
}

// HTML footer
echo "</body></html>";
?> 