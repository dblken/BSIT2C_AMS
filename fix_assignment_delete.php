<?php
// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/database.php';

// Set response type
header('Content-Type: text/html');

echo "<h1>Assignment Delete Diagnostic Tool</h1>";

// Function to create a styled section header
function section($title) {
    echo "<h2 style='margin-top: 20px; padding-bottom: 5px; border-bottom: 1px solid #ccc;'>{$title}</h2>";
}

// Check if the timetable table exists and if it has foreign keys to assignments
section("Checking Timetable Table");

$check_timetable = $conn->query("SHOW TABLES LIKE 'timetable'");
if ($check_timetable->num_rows > 0) {
    echo "<p>✅ Timetable table exists.</p>";
    
    // Check for assignment_id column
    $check_column = $conn->query("SHOW COLUMNS FROM timetable LIKE 'assignment_id'");
    if ($check_column->num_rows > 0) {
        echo "<p>✅ assignment_id column exists in timetable table.</p>";
        
        // Check if there's a foreign key from timetable to assignments
        $fk_query = "
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'timetable'
            AND COLUMN_NAME = 'assignment_id'
            AND REFERENCED_TABLE_NAME = 'assignments'
        ";
        $fk_result = $conn->query($fk_query);
        
        if ($fk_result && $fk_result->num_rows > 0) {
            $constraint = $fk_result->fetch_assoc()['CONSTRAINT_NAME'];
            echo "<p>⚠️ Found foreign key constraint: {$constraint}</p>";
            
            // Check the DELETE behavior
            $delete_rule_query = "
                SELECT DELETE_RULE
                FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND CONSTRAINT_NAME = '{$constraint}'
            ";
            $delete_rule_result = $conn->query($delete_rule_query);
            
            if ($delete_rule_result && $delete_rule_result->num_rows > 0) {
                $delete_rule = $delete_rule_result->fetch_assoc()['DELETE_RULE'];
                echo "<p>Current DELETE rule is: {$delete_rule}</p>";
                
                if ($delete_rule != 'SET NULL' && $delete_rule != 'CASCADE') {
                    echo "<p>⚠️ This constraint might be blocking assignment deletion.</p>";
                    
                    // Option to fix the constraint
                    echo "<form method='post'>";
                    echo "<input type='hidden' name='fix_timetable_fk' value='{$constraint}'>";
                    echo "<button type='submit' style='padding: 8px 15px; background-color: #dc3545; color: white; border: none; border-radius: 4px;'>Fix Timetable Foreign Key</button>";
                    echo "</form>";
                } else {
                    echo "<p>✅ The DELETE rule ({$delete_rule}) should allow assignment deletion.</p>";
                }
            }
        } else {
            echo "<p>✅ No foreign key constraint found from timetable to assignments.</p>";
        }
    } else {
        echo "<p>ℹ️ assignment_id column does not exist in timetable table.</p>";
    }
} else {
    echo "<p>ℹ️ Timetable table does not exist.</p>";
}

// Check for enrollments table and its relationship with assignments
section("Checking Enrollments Table");

$check_enrollments = $conn->query("SHOW TABLES LIKE 'enrollments'");
if ($check_enrollments->num_rows > 0) {
    echo "<p>✅ Enrollments table exists.</p>";
    
    // Check for foreign keys to assignments table
    $fk_query = "
        SELECT CONSTRAINT_NAME, COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'enrollments'
        AND REFERENCED_TABLE_NAME = 'assignments'
    ";
    $fk_result = $conn->query($fk_query);
    
    if ($fk_result && $fk_result->num_rows > 0) {
        while ($fk = $fk_result->fetch_assoc()) {
            echo "<p>⚠️ Found foreign key constraint: {$fk['CONSTRAINT_NAME']} on column {$fk['COLUMN_NAME']}</p>";
            
            // Option to fix the constraint
            echo "<form method='post'>";
            echo "<input type='hidden' name='fix_enrollments_fk' value='{$fk['CONSTRAINT_NAME']}'>";
            echo "<button type='submit' style='padding: 8px 15px; background-color: #dc3545; color: white; border: none; border-radius: 4px;'>Fix Enrollments Foreign Key</button>";
            echo "</form>";
        }
    } else {
        echo "<p>✅ No direct foreign key constraints found from enrollments to assignments.</p>";
    }
} else {
    echo "<p>ℹ️ Enrollments table does not exist.</p>";
}

// Check for attendance table and its relationship with assignments
section("Checking Attendance Table");

$check_attendance = $conn->query("SHOW TABLES LIKE 'attendance'");
if ($check_attendance->num_rows > 0) {
    echo "<p>✅ Attendance table exists.</p>";
    
    // Check if the attendance table has an assignment_id column
    $check_column = $conn->query("SHOW COLUMNS FROM attendance LIKE 'assignment_id'");
    if ($check_column->num_rows > 0) {
        echo "<p>✅ assignment_id column exists in attendance table.</p>";
        
        // Check for foreign keys to assignments table
        $fk_query = "
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'attendance'
            AND COLUMN_NAME = 'assignment_id'
            AND REFERENCED_TABLE_NAME = 'assignments'
        ";
        $fk_result = $conn->query($fk_query);
        
        if ($fk_result && $fk_result->num_rows > 0) {
            $constraint = $fk_result->fetch_assoc()['CONSTRAINT_NAME'];
            echo "<p>⚠️ Found foreign key constraint: {$constraint}</p>";
            
            // Option to fix the constraint
            echo "<form method='post'>";
            echo "<input type='hidden' name='fix_attendance_fk' value='{$constraint}'>";
            echo "<button type='submit' style='padding: 8px 15px; background-color: #dc3545; color: white; border: none; border-radius: 4px;'>Fix Attendance Foreign Key</button>";
            echo "</form>";
        } else {
            echo "<p>✅ No foreign key constraint found from attendance to assignments.</p>";
        }
    } else {
        echo "<p>ℹ️ assignment_id column does not exist in attendance table.</p>";
    }
} else {
    echo "<p>ℹ️ Attendance table does not exist.</p>";
}

// Process form submissions to fix constraints
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start a transaction
        $conn->begin_transaction();
        
        // Fix timetable foreign key
        if (isset($_POST['fix_timetable_fk'])) {
            $constraint = $_POST['fix_timetable_fk'];
            
            // Drop the existing constraint
            $drop_query = "ALTER TABLE timetable DROP FOREIGN KEY {$constraint}";
            if ($conn->query($drop_query)) {
                echo "<p style='color: green;'>Successfully dropped constraint {$constraint}</p>";
                
                // Add a new constraint with ON DELETE SET NULL
                $add_query = "
                    ALTER TABLE timetable 
                    ADD CONSTRAINT {$constraint} 
                    FOREIGN KEY (assignment_id) 
                    REFERENCES assignments(id) 
                    ON DELETE SET NULL
                ";
                
                if ($conn->query($add_query)) {
                    echo "<p style='color: green;'>Successfully added new constraint with ON DELETE SET NULL</p>";
                } else {
                    throw new Exception("Error adding new constraint: " . $conn->error);
                }
            } else {
                throw new Exception("Error dropping constraint: " . $conn->error);
            }
        }
        
        // Fix enrollments foreign key
        if (isset($_POST['fix_enrollments_fk'])) {
            $constraint = $_POST['fix_enrollments_fk'];
            
            // Drop the existing constraint
            $drop_query = "ALTER TABLE enrollments DROP FOREIGN KEY {$constraint}";
            if ($conn->query($drop_query)) {
                echo "<p style='color: green;'>Successfully dropped constraint {$constraint}</p>";
                
                // Add a new constraint with ON DELETE SET NULL
                $add_query = "
                    ALTER TABLE enrollments 
                    ADD CONSTRAINT {$constraint} 
                    FOREIGN KEY (assignment_id) 
                    REFERENCES assignments(id) 
                    ON DELETE SET NULL
                ";
                
                if ($conn->query($add_query)) {
                    echo "<p style='color: green;'>Successfully added new constraint with ON DELETE SET NULL</p>";
                } else {
                    throw new Exception("Error adding new constraint: " . $conn->error);
                }
            } else {
                throw new Exception("Error dropping constraint: " . $conn->error);
            }
        }
        
        // Fix attendance foreign key
        if (isset($_POST['fix_attendance_fk'])) {
            $constraint = $_POST['fix_attendance_fk'];
            
            // Drop the existing constraint
            $drop_query = "ALTER TABLE attendance DROP FOREIGN KEY {$constraint}";
            if ($conn->query($drop_query)) {
                echo "<p style='color: green;'>Successfully dropped constraint {$constraint}</p>";
                
                // Add a new constraint with ON DELETE SET NULL
                $add_query = "
                    ALTER TABLE attendance 
                    ADD CONSTRAINT {$constraint} 
                    FOREIGN KEY (assignment_id) 
                    REFERENCES assignments(id) 
                    ON DELETE SET NULL
                ";
                
                if ($conn->query($add_query)) {
                    echo "<p style='color: green;'>Successfully added new constraint with ON DELETE SET NULL</p>";
                } else {
                    throw new Exception("Error adding new constraint: " . $conn->error);
                }
            } else {
                throw new Exception("Error dropping constraint: " . $conn->error);
            }
        }
        
        // Commit transaction
        $conn->commit();
        echo "<p style='color: green; font-weight: bold;'>All changes committed successfully</p>";
        echo "<p><a href='fix_assignment_delete.php' style='color: blue;'>Refresh page</a> to see updated constraints</p>";
    } catch (Exception $e) {
        // Roll back transaction on error
        $conn->rollback();
        echo "<p style='color: red; font-weight: bold;'>Error: " . $e->getMessage() . "</p>";
    }
}

// Add a test assignment deletion form
section("Test Assignment Deletion");

$assignments_query = "SELECT a.id, s.subject_code, s.subject_name, CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                    FROM assignments a
                    JOIN subjects s ON a.subject_id = s.id
                    JOIN teachers t ON a.teacher_id = t.id
                    LIMIT 10";
$assignments_result = $conn->query($assignments_query);

if ($assignments_result && $assignments_result->num_rows > 0) {
    echo "<p>Select an assignment to test deletion:</p>";
    echo "<form method='post' action='admin/assignments/delete_assignment.php'>";
    echo "<select name='id' style='padding: 5px; width: 300px;'>";
    
    while ($row = $assignments_result->fetch_assoc()) {
        echo "<option value='{$row['id']}'>{$row['subject_code']} - {$row['subject_name']} (Teacher: {$row['teacher_name']})</option>";
    }
    
    echo "</select>";
    echo "<button type='submit' style='margin-left: 10px; padding: 5px 15px; background-color: #dc3545; color: white; border: none; border-radius: 4px;'>Test Delete</button>";
    echo "</form>";
} else {
    echo "<p>No assignments found to test deletion.</p>";
}

// Return link
echo "<p style='margin-top: 30px;'><a href='admin/assignments/index.php' style='padding: 8px 15px; background-color: #4285f4; color: white; text-decoration: none; border-radius: 4px;'>Return to Assignments Page</a></p>";
?> 