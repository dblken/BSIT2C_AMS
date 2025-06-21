<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "FIXING TIMETABLE STRUCTURE\n";
echo "=========================\n\n";

// Include database connection
require_once 'config/database.php';

// Check if timetable table exists
$check_timetable = mysqli_query($conn, "SHOW TABLES LIKE 'timetable'");
if (mysqli_num_rows($check_timetable) == 0) {
    echo "❌ Timetable table does not exist!\n";
    exit;
}

// Check if assignment_id column already exists
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM timetable LIKE 'assignment_id'");
if (mysqli_num_rows($check_column) > 0) {
    echo "✅ assignment_id column already exists in timetable table\n";
} else {
    echo "Adding assignment_id column to timetable table...\n";
    
    // Add column
    $alter_query = "ALTER TABLE timetable ADD COLUMN assignment_id INT NULL AFTER subject_id";
    if (mysqli_query($conn, $alter_query)) {
        echo "✅ Successfully added assignment_id column\n";
        
        // Now try to populate the assignment_id based on matching subject_id, day, time_start, and time_end
        echo "\nAttempting to populate assignment_id in timetable...\n";
        
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // First, check if preferred_day in assignments table is in JSON format
            $check_format = mysqli_query($conn, "SELECT preferred_day FROM assignments LIMIT 1");
            $is_json = false;
            
            if ($check_format && mysqli_num_rows($check_format) > 0) {
                $sample = mysqli_fetch_assoc($check_format)['preferred_day'];
                // Check if it looks like JSON
                if (substr($sample, 0, 1) === '[' || substr($sample, 0, 1) === '{') {
                    $is_json = true;
                    echo "ℹ️ Assignment preferred_day is in JSON format\n";
                }
            }
            
            $updated = 0;
            
            // If JSON format, we need a more complex query with JSON_CONTAINS
            if ($is_json) {
                // Get all timetable entries
                $timetable_query = "SELECT id, subject_id, day, time_start, time_end FROM timetable";
                $timetable_result = mysqli_query($conn, $timetable_query);
                
                while ($row = mysqli_fetch_assoc($timetable_result)) {
                    $id = $row['id'];
                    $subject_id = $row['subject_id'];
                    $day = $row['day'];
                    $time_start = $row['time_start'];
                    $time_end = $row['time_end'];
                    
                    // Convert day number to day name for checking
                    $day_name = '';
                    switch ($day) {
                        case 1: $day_name = 'Monday'; break;
                        case 2: $day_name = 'Tuesday'; break;
                        case 3: $day_name = 'Wednesday'; break;
                        case 4: $day_name = 'Thursday'; break;
                        case 5: $day_name = 'Friday'; break;
                        case 6: $day_name = 'Saturday'; break;
                        case 0: case 7: $day_name = 'Sunday'; break;
                    }
                    
                    // Find matching assignment based on day, time, and subject
                    $assignment_query = "
                        SELECT id FROM assignments 
                        WHERE subject_id = ? 
                        AND time_start = ? 
                        AND time_end = ?
                        AND (
                            JSON_CONTAINS(preferred_day, ?) 
                            OR JSON_CONTAINS(preferred_day, ?) 
                            OR preferred_day LIKE ?
                        )
                        LIMIT 1
                    ";
                    
                    $day_json = json_encode($day_name);
                    $day_short = substr($day_name, 0, 3); // Mon, Tue, etc.
                    $day_like = "%$day_name%";
                    
                    $stmt = mysqli_prepare($conn, $assignment_query);
                    mysqli_stmt_bind_param($stmt, "isssss", $subject_id, $time_start, $time_end, $day_json, $day_short, $day_like);
                    mysqli_stmt_execute($stmt);
                    $assignment_result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($assignment_result) > 0) {
                        $assignment = mysqli_fetch_assoc($assignment_result);
                        $assignment_id = $assignment['id'];
                        
                        // Update timetable record
                        $update = mysqli_query($conn, "UPDATE timetable SET assignment_id = $assignment_id WHERE id = $id");
                        if ($update) {
                            $updated++;
                        }
                    }
                }
            } else {
                // Simpler approach for non-JSON format
                $update_query = "
                    UPDATE timetable tt
                    JOIN assignments a ON 
                        tt.subject_id = a.subject_id AND
                        tt.time_start = a.time_start AND
                        tt.time_end = a.time_end
                    SET tt.assignment_id = a.id
                ";
                
                if (mysqli_query($conn, $update_query)) {
                    $updated = mysqli_affected_rows($conn);
                }
            }
            
            echo "✅ Updated $updated timetable entries with assignment_id\n";
            
            // Add foreign key constraint if needed
            echo "\nAdding foreign key constraint to assignment_id...\n";
            $add_fk = "
                ALTER TABLE timetable
                ADD CONSTRAINT fk_timetable_assignment
                FOREIGN KEY (assignment_id) REFERENCES assignments(id)
                ON DELETE SET NULL
            ";
            
            if (mysqli_query($conn, $add_fk)) {
                echo "✅ Added foreign key constraint\n";
            } else {
                echo "⚠️ Could not add foreign key constraint: " . mysqli_error($conn) . "\n";
                echo "This is not critical but would help maintain data integrity.\n";
            }
            
            mysqli_commit($conn);
            echo "✅ All changes committed\n";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "❌ Error: " . $e->getMessage() . "\n";
            echo "Changes have been rolled back.\n";
        }
    } else {
        echo "❌ Error adding assignment_id column: " . mysqli_error($conn) . "\n";
    }
}

// Display final timetable structure
echo "\nFinal timetable structure:\n";
$result = mysqli_query($conn, "DESCRIBE timetable");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

// Check how many timetable entries now have an assignment_id
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM timetable");
$total = mysqli_fetch_assoc($count_query)['total'];

$populated_query = mysqli_query($conn, "SELECT COUNT(*) as populated FROM timetable WHERE assignment_id IS NOT NULL");
$populated = mysqli_fetch_assoc($populated_query)['populated'];

echo "\nTimetable Statistics:\n";
echo "- Total entries: $total\n";
echo "- Entries with assignment_id: $populated (" . round(($populated/$total) * 100, 2) . "%)\n";

echo "\nDone.\n";
?> 