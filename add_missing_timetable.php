<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "ADDING MISSING TIMETABLE ENTRIES\n";
echo "==============================\n\n";

// Include database connection
require_once 'config/database.php';

// Find assignments without timetable entries
$query = "SELECT a.id, a.subject_id, a.preferred_day, a.time_start, a.time_end
          FROM assignments a
          LEFT JOIN timetable t ON a.id = t.assignment_id
          WHERE t.id IS NULL";

$result = mysqli_query($conn, $query);
if ($result && mysqli_num_rows($result) > 0) {
    echo "Found " . mysqli_num_rows($result) . " assignments without timetable entries\n";
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        $added = 0;
        
        while ($row = mysqli_fetch_assoc($result)) {
            $assignment_id = $row['id'];
            $subject_id = $row['subject_id'];
            $preferred_day = $row['preferred_day'];
            $time_start = $row['time_start'];
            $time_end = $row['time_end'];
            
            echo "\nProcessing Assignment #$assignment_id...\n";
            echo "- Preferred day: $preferred_day\n";
            
            // Check if preferred_day is in JSON format
            $days = [];
            $decoded = json_decode($preferred_day, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // It's a valid JSON array
                $days = $decoded;
                echo "- Decoded as JSON: " . implode(", ", $days) . "\n";
            } else {
                // Not JSON, try to parse it as a simple string
                $days = [$preferred_day];
                echo "- Treating as string\n";
            }
            
            // Add timetable entries for each day
            foreach ($days as $day) {
                // Convert day name to numeric value
                $day_num = null;
                
                if ($day == 'Monday' || $day == 'M') {
                    $day_num = 1;
                } else if ($day == 'Tuesday' || $day == 'T') {
                    $day_num = 2;
                } else if ($day == 'Wednesday' || $day == 'W') {
                    $day_num = 3;
                } else if ($day == 'Thursday' || $day == 'TH') {
                    $day_num = 4;
                } else if ($day == 'Friday' || $day == 'F') {
                    $day_num = 5;
                } else if ($day == 'Saturday' || $day == 'SAT') {
                    $day_num = 6;
                } else if ($day == 'Sunday' || $day == 'SUN') {
                    $day_num = 0;
                }
                
                if ($day_num !== null) {
                    // Insert new timetable entry
                    $insert_query = "INSERT INTO timetable 
                                    (subject_id, assignment_id, day, time_start, time_end, created_at, updated_at) 
                                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                    
                    $stmt = mysqli_prepare($conn, $insert_query);
                    mysqli_stmt_bind_param($stmt, "iiiss", $subject_id, $assignment_id, $day_num, $time_start, $time_end);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $added++;
                        echo "- ✅ Added timetable entry for day $day ($day_num)\n";
                    } else {
                        echo "- ❌ Failed to add timetable entry for day $day: " . mysqli_error($conn) . "\n";
                    }
                } else {
                    echo "- ❌ Could not determine day number for '$day'\n";
                }
            }
        }
        
        // Commit changes
        mysqli_commit($conn);
        echo "\n✅ Added $added timetable entries successfully\n";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "\n❌ Error: " . $e->getMessage() . "\n";
        echo "All changes have been rolled back.\n";
    }
} else {
    echo "No assignments without timetable entries found.\n";
}

echo "\nDone.\n";
?> 