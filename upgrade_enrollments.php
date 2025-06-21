<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "UPGRADING ENROLLMENTS TABLE STRUCTURE\n";
echo "====================================\n\n";

// Include database connection
require_once 'config/database.php';

// Check enrollments table current structure
echo "Current enrollments table structure:\n";
$current_structure = mysqli_query($conn, "DESCRIBE enrollments");
$columns = [];
if ($current_structure) {
    while ($row = mysqli_fetch_assoc($current_structure)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
        $columns[] = $row['Field'];
    }
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
    exit;
}

echo "\nChecking for required changes...\n";

// We need to:
// 1. Add subject_id column if it doesn't exist
// 2. Add assignment_id column if it doesn't exist
// 3. Keep schedule_id for compatibility, or add a migration script

// Begin a transaction for safety
mysqli_begin_transaction($conn);

try {
    $changes_made = false;
    
    // Check for subject_id column
    if (!in_array('subject_id', $columns)) {
        echo "Adding subject_id column...\n";
        
        // First, get the subject_id from timetable using schedule_id
        $migration_query = "
        ALTER TABLE enrollments 
        ADD COLUMN subject_id INT NULL AFTER student_id;
        ";
        
        if (mysqli_query($conn, $migration_query)) {
            echo "✅ Added subject_id column\n";
            
            // Now update the subject_id values from timetable relationship
            $update_query = "
            UPDATE enrollments e 
            JOIN timetable t ON e.schedule_id = t.id 
            SET e.subject_id = t.subject_id
            ";
            
            if (mysqli_query($conn, $update_query)) {
                echo "✅ Updated subject_id values from timetable\n";
                
                // Make subject_id NOT NULL after populating it
                mysqli_query($conn, "ALTER TABLE enrollments MODIFY COLUMN subject_id INT NOT NULL");
                echo "✅ Made subject_id NOT NULL\n";
                $changes_made = true;
            } else {
                throw new Exception("Failed to update subject_id: " . mysqli_error($conn));
            }
        } else {
            throw new Exception("Failed to add subject_id column: " . mysqli_error($conn));
        }
    } else {
        echo "✓ subject_id column already exists\n";
    }
    
    // Check for assignment_id column
    if (!in_array('assignment_id', $columns)) {
        echo "Adding assignment_id column...\n";
        
        // First, add the column
        $add_query = "
        ALTER TABLE enrollments 
        ADD COLUMN assignment_id INT NULL AFTER subject_id;
        ";
        
        if (mysqli_query($conn, $add_query)) {
            echo "✅ Added assignment_id column\n";
            
            // Check if there's a way to get assignment_id
            $check_assignment_relation = mysqli_query($conn, "
                SELECT COUNT(*) as count 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = 'assignments'
            ");
            
            $has_assignments = mysqli_fetch_assoc($check_assignment_relation)['count'] > 0;
            
            if ($has_assignments) {
                // Try to populate from existing data if possible
                // This is a simplified example - you might need more complex logic
                $update_query = "
                UPDATE enrollments e 
                LEFT JOIN assignments a ON e.subject_id = a.subject_id 
                SET e.assignment_id = a.id
                WHERE e.assignment_id IS NULL
                AND a.id IS NOT NULL
                ";
                
                if (mysqli_query($conn, $update_query)) {
                    echo "✅ Updated assignment_id where possible\n";
                } else {
                    echo "⚠️ Could not update assignment_id: " . mysqli_error($conn) . "\n";
                }
            } else {
                echo "⚠️ No assignments table found - assignment_id will need manual updates\n";
            }
            
            $changes_made = true;
        } else {
            throw new Exception("Failed to add assignment_id column: " . mysqli_error($conn));
        }
    } else {
        echo "✓ assignment_id column already exists\n";
    }
    
    // If we made changes, we need to update the PHP code
    if ($changes_made) {
        mysqli_commit($conn);
        echo "\n✅ All changes committed successfully\n";
    } else {
        echo "\n✓ No changes needed\n";
        mysqli_rollback($conn);
    }
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "All changes have been rolled back.\n";
}

// Display final structure
echo "\nFinal enrollments table structure:\n";
$final_structure = mysqli_query($conn, "DESCRIBE enrollments");
if ($final_structure) {
    while ($row = mysqli_fetch_assoc($final_structure)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

echo "\nDone.\n";
?> 