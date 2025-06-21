<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/database.php';

// Start transaction
$conn->begin_transaction();

try {
    echo "<h1>Foreign Key Constraint Repair Tool</h1>";
    
    // 1. Find and log invalid attendance records (referencing non-existent assignments)
    $find_invalid_sql = "
        SELECT a.id, a.teacher_id, a.subject_id, a.assignment_id, a.attendance_date
        FROM attendance a
        LEFT JOIN assignments ass ON a.assignment_id = ass.id
        WHERE ass.id IS NULL";
    
    $result = $conn->query($find_invalid_sql);
    
    $invalid_count = $result->num_rows;
    echo "<p>Found $invalid_count attendance records with invalid assignment references.</p>";
    
    // If there are invalid records, remove them
    if ($invalid_count > 0) {
        echo "<h2>Invalid Records to be Removed:</h2>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Teacher ID</th><th>Subject ID</th><th>Assignment ID</th><th>Date</th></tr>";
        
        $invalid_ids = [];
        while ($row = $result->fetch_assoc()) {
            $invalid_ids[] = $row['id'];
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['teacher_id'] . "</td>";
            echo "<td>" . $row['subject_id'] . "</td>";
            echo "<td>" . $row['assignment_id'] . "</td>";
            echo "<td>" . $row['attendance_date'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Delete related attendance records first (due to foreign key constraints)
        foreach ($invalid_ids as $attendance_id) {
            $delete_records_sql = "DELETE FROM attendance_records WHERE attendance_id = ?";
            $stmt = $conn->prepare($delete_records_sql);
            $stmt->bind_param("i", $attendance_id);
            $stmt->execute();
            echo "<p>Deleted attendance records for attendance ID: $attendance_id</p>";
        }
        
        // Delete invalid attendance records
        $placeholders = implode(',', array_fill(0, count($invalid_ids), '?'));
        $delete_attendance_sql = "DELETE FROM attendance WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($delete_attendance_sql);
        
        // Bind all parameters (requires PHP 7.3+)
        $types = str_repeat('i', count($invalid_ids));
        $stmt->bind_param($types, ...$invalid_ids);
        $stmt->execute();
        
        echo "<p>Deleted $invalid_count invalid attendance records successfully.</p>";
    }
    
    // 2. Offer solution for teachers with no assignments
    echo "<h2>Teachers with No Assignments</h2>";
    $teachers_query = "
        SELECT t.id, CONCAT(t.first_name, ' ', t.last_name) as name
        FROM teachers t
        LEFT JOIN assignments a ON t.id = a.teacher_id
        WHERE a.id IS NULL";
    
    $result = $conn->query($teachers_query);
    $teacher_count = $result->num_rows;
    
    if ($teacher_count > 0) {
        echo "<p>Found $teacher_count active teachers with no assignments:</p>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['name'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p>Recommendation: Assign subjects to these teachers from the admin panel.</p>";
    } else {
        echo "<p>All active teachers have at least one assignment.</p>";
    }
    
    // 3. Check if there are any unassigned subjects that could be used
    echo "<h2>Unassigned Subjects</h2>";
    $subjects_query = "
        SELECT s.id, s.subject_code, s.subject_name
        FROM subjects s
        LEFT JOIN assignments a ON s.id = a.subject_id
        WHERE a.id IS NULL";
    
    $result = $conn->query($subjects_query);
    $subject_count = $result->num_rows;
    
    if ($subject_count > 0) {
        echo "<p>Found $subject_count active subjects with no assignments:</p>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Code</th><th>Name</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['subject_code'] . "</td>";
            echo "<td>" . $row['subject_name'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p>Recommendation: Assign these subjects to teachers from the admin panel.</p>";
    } else {
        echo "<p>All active subjects have at least one assignment.</p>";
    }
    
    // Commit the transaction
    $conn->commit();
    echo "<h2 style='color:green'>Database repair completed successfully!</h2>";
    echo "<p>All foreign key constraint issues have been resolved.</p>";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "<h2 style='color:red'>Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

// Add navigation buttons
echo "<div style='margin-top: 20px;'>";
echo "<a href='index.php' style='padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Go to Home</a>";
echo "<a href='admin/dashboard.php' style='padding: 10px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px;'>Go to Admin Panel</a>";
echo "</div>";
?> 