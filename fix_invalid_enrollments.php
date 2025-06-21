<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "FIXING INVALID ENROLLMENTS\n";
echo "========================\n\n";

// Include database connection
require_once 'config/database.php';

// Find enrollments with invalid subject_id or assignment_id
$query = "SELECT e.id, e.student_id, e.subject_id, e.assignment_id, e.schedule_id,
                 s.first_name, s.last_name
          FROM enrollments e
          LEFT JOIN students s ON e.student_id = s.id
          WHERE e.subject_id = 0 OR e.assignment_id = 0";

$result = mysqli_query($conn, $query);
if ($result && mysqli_num_rows($result) > 0) {
    echo "Found " . mysqli_num_rows($result) . " enrollments with invalid subject_id or assignment_id\n";
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        $fixed = 0;
        $deleted = 0;
        
        while ($row = mysqli_fetch_assoc($result)) {
            $enrollment_id = $row['id'];
            $student_id = $row['student_id'];
            $student_name = $row['first_name'] . ' ' . $row['last_name'];
            $schedule_id = $row['schedule_id'];
            
            echo "\nProcessing Enrollment #$enrollment_id for $student_name...\n";
            
            // Check if we can update the enrollment using schedule_id
            if ($schedule_id > 0) {
                // Try to get subject_id and assignment_id from timetable and assignment
                $fix_query = "
                    SELECT t.subject_id, t.assignment_id
                    FROM timetable t
                    WHERE t.id = ?
                ";
                
                $stmt = mysqli_prepare($conn, $fix_query);
                mysqli_stmt_bind_param($stmt, "i", $schedule_id);
                mysqli_stmt_execute($stmt);
                $fix_result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($fix_result) > 0) {
                    $fix_data = mysqli_fetch_assoc($fix_result);
                    $subject_id = $fix_data['subject_id'];
                    $assignment_id = $fix_data['assignment_id'];
                    
                    if ($subject_id > 0 && $assignment_id > 0) {
                        // Update the enrollment
                        $update_query = "
                            UPDATE enrollments
                            SET subject_id = ?, assignment_id = ?
                            WHERE id = ?
                        ";
                        
                        $update_stmt = mysqli_prepare($conn, $update_query);
                        mysqli_stmt_bind_param($update_stmt, "iii", $subject_id, $assignment_id, $enrollment_id);
                        
                        if (mysqli_stmt_execute($update_stmt)) {
                            $fixed++;
                            echo "- ✅ Fixed enrollment with subject_id = $subject_id, assignment_id = $assignment_id\n";
                        } else {
                            echo "- ❌ Failed to update enrollment: " . mysqli_error($conn) . "\n";
                        }
                    } else {
                        echo "- ❌ Could not determine valid subject_id or assignment_id from schedule_id\n";
                        // Delete invalid enrollment
                        $delete_query = "DELETE FROM enrollments WHERE id = ?";
                        $delete_stmt = mysqli_prepare($conn, $delete_query);
                        mysqli_stmt_bind_param($delete_stmt, "i", $enrollment_id);
                        
                        if (mysqli_stmt_execute($delete_stmt)) {
                            $deleted++;
                            echo "- ✅ Deleted invalid enrollment\n";
                        } else {
                            echo "- ❌ Failed to delete enrollment: " . mysqli_error($conn) . "\n";
                        }
                    }
                } else {
                    echo "- ❌ No timetable entry found for schedule_id = $schedule_id\n";
                    // Delete invalid enrollment
                    $delete_query = "DELETE FROM enrollments WHERE id = ?";
                    $delete_stmt = mysqli_prepare($conn, $delete_query);
                    mysqli_stmt_bind_param($delete_stmt, "i", $enrollment_id);
                    
                    if (mysqli_stmt_execute($delete_stmt)) {
                        $deleted++;
                        echo "- ✅ Deleted invalid enrollment\n";
                    } else {
                        echo "- ❌ Failed to delete enrollment: " . mysqli_error($conn) . "\n";
                    }
                }
            } else {
                echo "- ❌ No valid schedule_id found\n";
                // Delete invalid enrollment
                $delete_query = "DELETE FROM enrollments WHERE id = ?";
                $delete_stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($delete_stmt, "i", $enrollment_id);
                
                if (mysqli_stmt_execute($delete_stmt)) {
                    $deleted++;
                    echo "- ✅ Deleted invalid enrollment\n";
                } else {
                    echo "- ❌ Failed to delete enrollment: " . mysqli_error($conn) . "\n";
                }
            }
        }
        
        // Commit changes
        mysqli_commit($conn);
        echo "\n✅ Fixed $fixed enrollments and deleted $deleted invalid enrollments\n";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "\n❌ Error: " . $e->getMessage() . "\n";
        echo "All changes have been rolled back.\n";
    }
} else {
    echo "No invalid enrollments found.\n";
}

// Show current valid enrollments
$valid_query = "SELECT e.id, e.student_id, e.subject_id, e.assignment_id,
                       s.first_name, s.last_name, 
                       subj.subject_code, subj.subject_name
                FROM enrollments e
                JOIN students s ON e.student_id = s.id
                JOIN subjects subj ON e.subject_id = subj.id
                LIMIT 5";

$valid_result = mysqli_query($conn, $valid_query);
echo "\nCurrent valid enrollments:\n";
if ($valid_result && mysqli_num_rows($valid_result) > 0) {
    while ($row = mysqli_fetch_assoc($valid_result)) {
        echo "\nEnrollment #{$row['id']}:\n";
        echo "- Student: {$row['first_name']} {$row['last_name']} (ID: {$row['student_id']})\n";
        echo "- Subject: {$row['subject_code']} - {$row['subject_name']} (ID: {$row['subject_id']})\n";
        echo "- Assignment ID: {$row['assignment_id']}\n";
    }
} else {
    echo "No valid enrollments found.\n";
}

echo "\nDone.\n";
?> 