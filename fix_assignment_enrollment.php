<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "FIXING ASSIGNMENT-ENROLLMENT RELATIONSHIP\n";
echo "======================================\n\n";

// Include database connection
require_once 'config/database.php';

// First, check if the assignment_id column exists in the timetable table
$check_assignment_column = mysqli_query($conn, "SHOW COLUMNS FROM timetable LIKE 'assignment_id'");
if (mysqli_num_rows($check_assignment_column) == 0) {
    echo "❌ The timetable table is missing the assignment_id column. Please run fix_timetable_structure.php first.\n";
    exit;
}

// Count enrollments and assignments to see what we're working with
$enrollment_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM enrollments");
$enrollment_total = mysqli_fetch_assoc($enrollment_count)['count'];
echo "Total enrollments: $enrollment_total\n";

$assignment_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM assignments");
$assignment_total = mysqli_fetch_assoc($assignment_count)['count'];
echo "Total assignments: $assignment_total\n";

// Begin transaction for safety
mysqli_begin_transaction($conn);

try {
    // 1. Check the enrollments table structure
    $enrollment_structure = mysqli_query($conn, "DESCRIBE enrollments");
    $columns = [];
    while ($col = mysqli_fetch_assoc($enrollment_structure)) {
        $columns[] = $col['Field'];
    }

    // Check if we have both subject_id and assignment_id columns in the enrollments table
    if (!in_array('subject_id', $columns)) {
        echo "❌ The enrollments table is missing the subject_id column.\n";
        throw new Exception("Missing required column: subject_id");
    }

    if (!in_array('assignment_id', $columns)) {
        echo "❌ The enrollments table is missing the assignment_id column.\n";
        throw new Exception("Missing required column: assignment_id");
    }

    echo "✅ Enrollments table has the required columns\n\n";

    // 2. Fix any NULL assignment_id values in enrollments 
    // (this might happen if enrollments were created before we added the assignment_id column)
    $null_assignments = mysqli_query($conn, "SELECT COUNT(*) as count FROM enrollments WHERE assignment_id IS NULL");
    $null_count = mysqli_fetch_assoc($null_assignments)['count'];

    if ($null_count > 0) {
        echo "Found $null_count enrollments with NULL assignment_id\n";
        echo "Attempting to fix them...\n";

        // Try to find a matching assignment based on subject_id
        $fix_query = "
            UPDATE enrollments e
            JOIN assignments a ON e.subject_id = a.subject_id
            SET e.assignment_id = a.id
            WHERE e.assignment_id IS NULL
            LIMIT $null_count
        ";

        if (mysqli_query($conn, $fix_query)) {
            $fixed = mysqli_affected_rows($conn);
            echo "✅ Fixed $fixed enrollments with NULL assignment_id\n";
        } else {
            echo "❌ Error fixing NULL assignment_ids: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "✅ No enrollments with NULL assignment_id found\n";
    }

    // 3. Verify that all timetable entries have assignment_id values
    $null_timetable = mysqli_query($conn, "SELECT COUNT(*) as count FROM timetable WHERE assignment_id IS NULL");
    $null_timetable_count = mysqli_fetch_assoc($null_timetable)['count'];

    if ($null_timetable_count > 0) {
        echo "\nFound $null_timetable_count timetable entries without assignment_id\n";
        echo "Attempting to fix them...\n";

        // Match timetable entries to assignments based on subject_id, time_start, and time_end
        $fix_timetable_query = "
            UPDATE timetable tt
            JOIN assignments a ON tt.subject_id = a.subject_id 
                               AND tt.time_start = a.time_start 
                               AND tt.time_end = a.time_end
            SET tt.assignment_id = a.id
            WHERE tt.assignment_id IS NULL
        ";

        if (mysqli_query($conn, $fix_timetable_query)) {
            $fixed_timetable = mysqli_affected_rows($conn);
            echo "✅ Fixed $fixed_timetable timetable entries with NULL assignment_id\n";
        } else {
            echo "❌ Error fixing timetable: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "\n✅ All timetable entries have assignment_id values\n";
    }

    // Commit all changes
    mysqli_commit($conn);
    echo "\n✅ All changes committed successfully\n";

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "All changes have been rolled back.\n";
}

// Display current enrollment status
$enrollment_query = mysqli_query($conn, "
    SELECT e.id, e.student_id, e.subject_id, e.assignment_id, 
           s.first_name, s.last_name, 
           subj.subject_code, subj.subject_name
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    JOIN subjects subj ON e.subject_id = subj.id
    LIMIT 5
");

echo "\nSample enrollments:\n";
if (mysqli_num_rows($enrollment_query) > 0) {
    $i = 1;
    while ($row = mysqli_fetch_assoc($enrollment_query)) {
        echo "\nEnrollment #$i:\n";
        echo "- ID: {$row['id']}\n";
        echo "- Student: {$row['first_name']} {$row['last_name']} (ID: {$row['student_id']})\n";
        echo "- Subject: {$row['subject_code']} - {$row['subject_name']} (ID: {$row['subject_id']})\n";
        echo "- Assignment ID: {$row['assignment_id']}\n";
        
        // Check if there's a corresponding timetable entry
        $check_timetable = mysqli_query($conn, "SELECT id FROM timetable WHERE assignment_id = {$row['assignment_id']}");
        if (mysqli_num_rows($check_timetable) > 0) {
            echo "- ✅ Has timetable entry\n";
        } else {
            echo "- ❌ No timetable entry found\n";
        }
        
        $i++;
    }
} else {
    echo "No enrollments found.\n";
}

echo "\nDone.\n";
?> 