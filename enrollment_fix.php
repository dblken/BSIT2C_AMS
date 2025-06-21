<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "ENROLLMENT REPAIR SCRIPT\n";
echo "=======================\n\n";

// Include database connection
require_once 'config/database.php';

// Step 1: Count enrollments missing schedule_id
$count_query = "SELECT COUNT(*) as count FROM enrollments WHERE schedule_id IS NULL OR schedule_id = 0";
$count_result = mysqli_query($conn, $count_query);
$missing_count = mysqli_fetch_assoc($count_result)['count'];

echo "Found {$missing_count} enrollments with missing schedule_id\n\n";

if ($missing_count == 0) {
    echo "✅ No enrollments need fixing. All enrollments have a valid schedule_id.\n";
    exit;
}

// Step 2: Get all enrollments with missing schedule_id
$missing_enrollments_query = "
    SELECT e.id, e.student_id, e.subject_id, e.assignment_id, 
           CONCAT(s.first_name, ' ', s.last_name) as student_name,
           sub.subject_code, sub.subject_name
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    JOIN subjects sub ON e.subject_id = sub.id
    WHERE e.schedule_id IS NULL OR e.schedule_id = 0
";

$missing_enrollments = mysqli_query($conn, $missing_enrollments_query);

echo "ENROLLMENTS REQUIRING REPAIR:\n";
echo "---------------------------\n";

$fixed_count = 0;
$failed_count = 0;

// Step 3: Process each enrollment and try to fix it
while ($enrollment = mysqli_fetch_assoc($missing_enrollments)) {
    echo "\nEnrollment #{$enrollment['id']}:\n";
    echo "- Student: #{$enrollment['student_id']} {$enrollment['student_name']}\n";
    echo "- Subject: {$enrollment['subject_code']} - {$enrollment['subject_name']}\n";
    echo "- Assignment ID: {$enrollment['assignment_id']}\n";
    
    // Check if the assignment exists
    $assignment_check = mysqli_query($conn, "SELECT id FROM assignments WHERE id = {$enrollment['assignment_id']}");
    
    if (mysqli_num_rows($assignment_check) == 0) {
        echo "❌ ERROR: Assignment #{$enrollment['assignment_id']} does not exist!\n";
        echo "  Cannot fix this enrollment without a valid assignment.\n";
        $failed_count++;
        continue;
    }
    
    // Find a matching timetable entry for this assignment
    $timetable_query = "SELECT id FROM timetable WHERE assignment_id = {$enrollment['assignment_id']} LIMIT 1";
    $timetable_result = mysqli_query($conn, $timetable_query);
    
    if (mysqli_num_rows($timetable_result) > 0) {
        $timetable = mysqli_fetch_assoc($timetable_result);
        $schedule_id = $timetable['id'];
        
        echo "✓ Found matching timetable entry: #{$schedule_id}\n";
        
        // Update the enrollment with the correct schedule_id
        $update_query = "UPDATE enrollments SET schedule_id = {$schedule_id} WHERE id = {$enrollment['id']}";
        
        if (mysqli_query($conn, $update_query)) {
            echo "✅ FIXED: Updated enrollment with correct schedule_id\n";
            $fixed_count++;
        } else {
            echo "❌ ERROR: Failed to update enrollment: " . mysqli_error($conn) . "\n";
            $failed_count++;
        }
    } else {
        echo "❌ ERROR: No timetable entry found for assignment #{$enrollment['assignment_id']}\n";
        echo "  Cannot fix this enrollment without a valid timetable entry.\n";
        
        // Check if we can find details about this assignment to help diagnose
        $assignment_details_query = "
            SELECT a.id, a.preferred_day, 
                   CONCAT(t.first_name, ' ', t.last_name) as teacher_name
            FROM assignments a
            JOIN teachers t ON a.teacher_id = t.id
            WHERE a.id = {$enrollment['assignment_id']}
        ";
        
        $assignment_details = mysqli_query($conn, $assignment_details_query);
        if ($details = mysqli_fetch_assoc($assignment_details)) {
            echo "  Assignment details:\n";
            echo "  - Teacher: {$details['teacher_name']}\n";
            echo "  - Preferred Day: {$details['preferred_day']}\n";
            echo "  Consider running add_missing_timetable.php to create timetable entries for this assignment.\n";
        }
        
        $failed_count++;
    }
}

// Step 4: Summary
echo "\n\nREPAIR SUMMARY:\n";
echo "--------------\n";
echo "Total enrollments requiring repair: {$missing_count}\n";
echo "Successfully fixed: {$fixed_count}\n";
echo "Failed to fix: {$failed_count}\n";

if ($fixed_count > 0) {
    echo "\n✅ Successfully repaired {$fixed_count} enrollments!\n";
}

if ($failed_count > 0) {
    echo "\n⚠️ {$failed_count} enrollments could not be repaired automatically.\n";
    echo "These may require manual intervention or creating missing timetable entries first.\n";
}

echo "\nDONE. ENROLLMENT REPAIR COMPLETE.\n";
?> 