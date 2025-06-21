<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/database.php';

// Start transaction
$conn->begin_transaction();

try {
    echo "<h1>Attendance Table Schema Upgrade</h1>";
    
    // Check if assignment_id column exists
    $column_check_result = $conn->query("SHOW COLUMNS FROM attendance LIKE 'assignment_id'");
    $has_assignment_id = $column_check_result->num_rows > 0;
    
    // Check if teacher_id column exists
    $teacher_id_check = $conn->query("SHOW COLUMNS FROM attendance LIKE 'teacher_id'");
    $has_teacher_id = $teacher_id_check->num_rows > 0;
    
    $changes_made = false;
    
    // Step 1: Add assignment_id column if it doesn't exist
    if (!$has_assignment_id) {
        $conn->query("ALTER TABLE attendance ADD COLUMN assignment_id INT AFTER subject_id");
        echo "<p>✓ Added assignment_id column to attendance table.</p>";
        $changes_made = true;
        
        // Now check for the foreign key constraint
        $fk_check = $conn->query("
            SELECT *
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'attendance'
            AND COLUMN_NAME = 'assignment_id'
            AND REFERENCED_TABLE_NAME = 'assignments'
        ");
        
        if ($fk_check->num_rows == 0) {
            $conn->query("ALTER TABLE attendance ADD CONSTRAINT attendance_ibfk_3 FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE");
            echo "<p>✓ Added foreign key constraint for assignment_id column.</p>";
        }
    } else {
        echo "<p>✓ The assignment_id column already exists.</p>";
    }
    
    // Step 2: Add teacher_id column if it doesn't exist
    if (!$has_teacher_id) {
        $conn->query("ALTER TABLE attendance ADD COLUMN teacher_id INT AFTER id");
        echo "<p>✓ Added teacher_id column to attendance table.</p>";
        $changes_made = true;
        
        // Now check for the foreign key constraint
        $fk_check = $conn->query("
            SELECT *
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'attendance'
            AND COLUMN_NAME = 'teacher_id'
            AND REFERENCED_TABLE_NAME = 'teachers'
        ");
        
        if ($fk_check->num_rows == 0) {
            $conn->query("ALTER TABLE attendance ADD CONSTRAINT attendance_ibfk_4 FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE");
            echo "<p>✓ Added foreign key constraint for teacher_id column.</p>";
        }
    } else {
        echo "<p>✓ The teacher_id column already exists.</p>";
    }
    
    // Step 3: Check for student_id and rename to attendance_date if needed
    $date_check = $conn->query("SHOW COLUMNS FROM attendance LIKE 'date'");
    $attendance_date_check = $conn->query("SHOW COLUMNS FROM attendance LIKE 'attendance_date'");
    
    if ($date_check->num_rows > 0 && $attendance_date_check->num_rows == 0) {
        $conn->query("ALTER TABLE attendance CHANGE COLUMN `date` `attendance_date` DATE NOT NULL");
        echo "<p>✓ Renamed 'date' column to 'attendance_date' for consistency.</p>";
        $changes_made = true;
    }
    
    // Step 4: Check for is_pending column
    $pending_check = $conn->query("SHOW COLUMNS FROM attendance LIKE 'is_pending'");
    if ($pending_check->num_rows == 0) {
        $conn->query("ALTER TABLE attendance ADD COLUMN is_pending TINYINT(1) DEFAULT 0 AFTER attendance_date");
        echo "<p>✓ Added is_pending column to attendance table.</p>";
        $changes_made = true;
    } else {
        echo "<p>✓ The is_pending column already exists.</p>";
    }
    
    // Step 5: Create the attendance_records table if it doesn't exist
    $table_check = $conn->query("SHOW TABLES LIKE 'attendance_records'");
    if ($table_check->num_rows == 0) {
        $create_records_table = "
            CREATE TABLE attendance_records (
                id INT PRIMARY KEY AUTO_INCREMENT,
                attendance_id INT NOT NULL,
                student_id INT NOT NULL,
                status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
                remarks TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
            )
        ";
        $conn->query($create_records_table);
        echo "<p>✓ Created attendance_records table.</p>";
        $changes_made = true;
    } else {
        echo "<p>✓ The attendance_records table already exists.</p>";
    }
    
    if ($changes_made) {
        // Commit changes to the database
        $conn->commit();
        echo "<div style='background-color: #dff0d8; padding: 10px; margin-top: 20px;'>";
        echo "<h3 style='color: #3c763d;'>✓ Database schema upgrade completed successfully!</h3>";
        echo "<p>Your attendance system database schema has been updated to the latest version.</p>";
        echo "</div>";
    } else {
        $conn->rollback(); // No changes needed
        echo "<div style='background-color: #d9edf7; padding: 10px; margin-top: 20px;'>";
        echo "<h3 style='color: #31708f;'>ℹ️ No schema changes were needed</h3>";
        echo "<p>Your database schema is already up to date.</p>";
        echo "</div>";
    }
    
    // Now check for and fix any existing constraint violations
    echo "<h2>Checking for Invalid Records</h2>";
    
    // Find invalid records
    $invalid_check = "
        SELECT a.id
        FROM attendance a
        LEFT JOIN assignments ass ON a.assignment_id = ass.id
        WHERE a.assignment_id IS NOT NULL
        AND ass.id IS NULL
    ";
    $invalid_result = $conn->query($invalid_check);
    $invalid_count = $invalid_result->num_rows;
    
    if ($invalid_count > 0) {
        echo "<p>Found $invalid_count invalid attendance records. Would you like to fix them? <a href='fix_broken_constraints.php' class='btn' style='background-color: #d9534f; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>Fix Invalid Records</a></p>";
    } else {
        echo "<p>✓ No invalid attendance records found.</p>";
    }
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "<div style='color: red; font-weight: bold;'>";
    echo "Error: " . $e->getMessage();
    echo "</div>";
}

// Add navigation buttons
echo "<div style='margin-top: 20px;'>";
echo "<a href='index.php' style='padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Go to Home</a>";
echo "<a href='admin/dashboard.php' style='padding: 10px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px;'>Go to Admin Panel</a>";
echo "</div>";
?> 