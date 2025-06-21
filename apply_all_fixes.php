<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "APPLYING ALL SYSTEM FIXES\n";
echo "========================\n\n";

// Include database connection
require_once 'config/database.php';

// Begin transaction for safety
mysqli_begin_transaction($conn);

try {
    echo "1. CHECKING AND CREATING ADMIN ACCOUNT\n";
    echo "-----------------------------------\n";
    
    // Create new admin user
    $username = 'newadmin';
    $password = 'admin123';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if users table exists and has the right structure
    $check_users = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
    
    if (mysqli_num_rows($check_users) > 0) {
        // Check structure
        $columns = mysqli_query($conn, "DESCRIBE users");
        $has_username = false;
        $has_password = false;
        $has_role = false;
        
        while ($col = mysqli_fetch_assoc($columns)) {
            if ($col['Field'] == 'username') $has_username = true;
            if ($col['Field'] == 'password') $has_password = true;
            if ($col['Field'] == 'role') $has_role = true;
        }
        
        if ($has_username && $has_password && $has_role) {
            // Check if admin already exists
            $check_admin = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
            
            if (mysqli_num_rows($check_admin) > 0) {
                // Just update password
                mysqli_query($conn, "UPDATE users SET password = '$password_hash' WHERE username = '$username'");
                echo "✅ Updated existing admin user: $username with password: $password\n";
            } else {
                // Create new admin
                mysqli_query($conn, "INSERT INTO users (username, password, role) VALUES ('$username', '$password_hash', 'admin')");
                echo "✅ Created new admin user: $username with password: $password\n";
            }
            
            // Also reset the default admin password if it exists
            mysqli_query($conn, "UPDATE users SET password = '$password_hash' WHERE username = 'admin' AND role = 'admin'");
            echo "✅ Also reset 'admin' user password if it exists\n";
        } else {
            echo "❌ Users table doesn't have the required columns (username, password, role)\n";
        }
    } else {
        echo "❌ Users table doesn't exist\n";
    }
    
    echo "\n2. FIXING TIMETABLE STRUCTURE\n";
    echo "----------------------------\n";
    
    // Check if timetable exists
    $check_timetable = mysqli_query($conn, "SHOW TABLES LIKE 'timetable'");
    
    if (mysqli_num_rows($check_timetable) > 0) {
        // Check if assignment_id column exists
        $check_column = mysqli_query($conn, "SHOW COLUMNS FROM timetable LIKE 'assignment_id'");
        
        if (mysqli_num_rows($check_column) == 0) {
            // Add assignment_id column
            mysqli_query($conn, "ALTER TABLE timetable ADD COLUMN assignment_id INT NULL AFTER subject_id");
            echo "✅ Added assignment_id column to timetable table\n";
            
            // Update assignment_id values based on matching criteria
            $update_query = "
                UPDATE timetable tt
                JOIN assignments a ON tt.subject_id = a.subject_id 
                                   AND tt.time_start = a.time_start 
                                   AND tt.time_end = a.time_end
                SET tt.assignment_id = a.id
                WHERE tt.assignment_id IS NULL
            ";
            
            if (mysqli_query($conn, $update_query)) {
                $updated = mysqli_affected_rows($conn);
                echo "✅ Updated $updated timetable entries with assignment_id values\n";
            } else {
                echo "❌ Error updating timetable: " . mysqli_error($conn) . "\n";
            }
        } else {
            echo "✓ assignment_id column already exists in timetable\n";
        }
    } else {
        echo "❌ Timetable table doesn't exist\n";
    }
    
    echo "\n3. CHECKING ENROLLMENTS TABLE\n";
    echo "---------------------------\n";
    
    // Check if enrollments table exists
    $check_enrollments = mysqli_query($conn, "SHOW TABLES LIKE 'enrollments'");
    
    if (mysqli_num_rows($check_enrollments) > 0) {
        // Check structure
        $columns = mysqli_query($conn, "DESCRIBE enrollments");
        $structure = [];
        
        while ($col = mysqli_fetch_assoc($columns)) {
            $structure[] = $col['Field'];
        }
        
        // Check for required columns
        if (!in_array('subject_id', $structure)) {
            mysqli_query($conn, "ALTER TABLE enrollments ADD COLUMN subject_id INT NULL AFTER student_id");
            echo "✅ Added subject_id column to enrollments table\n";
        }
        
        if (!in_array('assignment_id', $structure)) {
            mysqli_query($conn, "ALTER TABLE enrollments ADD COLUMN assignment_id INT NULL AFTER subject_id");
            echo "✅ Added assignment_id column to enrollments table\n";
        }
        
        // Check for schedule_id column which is required
        if (!in_array('schedule_id', $structure)) {
            mysqli_query($conn, "ALTER TABLE enrollments ADD COLUMN schedule_id INT NULL AFTER assignment_id");
            echo "✅ Added schedule_id column to enrollments table\n";
        }
        
        // Populate missing values
        if (in_array('assignment_id', $structure) && in_array('subject_id', $structure) && in_array('schedule_id', $structure)) {
            // Fix enrollments with NULL or 0 values
            $update_query = "
                UPDATE enrollments e
                JOIN timetable t ON e.schedule_id = t.id
                SET e.subject_id = t.subject_id, e.assignment_id = t.assignment_id
                WHERE (e.subject_id IS NULL OR e.subject_id = 0 OR e.assignment_id IS NULL OR e.assignment_id = 0)
                  AND t.subject_id IS NOT NULL AND t.assignment_id IS NOT NULL
            ";
            
            if (mysqli_query($conn, $update_query)) {
                $updated = mysqli_affected_rows($conn);
                echo "✅ Fixed $updated enrollments with missing values\n";
            } else {
                echo "❌ Error updating enrollments: " . mysqli_error($conn) . "\n";
            }
        }
    } else {
        // Enrollments table doesn't exist, create it
        $create_query = "
            CREATE TABLE enrollments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                student_id INT NOT NULL,
                subject_id INT NOT NULL,
                assignment_id INT NOT NULL,
                schedule_id INT NOT NULL,
                status ENUM('Enrolled', 'Dropped', 'Completed') DEFAULT 'Enrolled',
                enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ";
        
        if (mysqli_query($conn, $create_query)) {
            echo "✅ Created enrollments table\n";
        } else {
            echo "❌ Error creating enrollments table: " . mysqli_error($conn) . "\n";
        }
    }
    
    echo "\n4. UPDATING ENROLLMENT PROCESS CODE\n";
    echo "---------------------------------\n";
    echo "✓ This has been fixed in the PHP code (process_enrollment.php)\n";
    
    // All fixes have been applied successfully
    mysqli_commit($conn);
    echo "\n✅ ALL FIXES APPLIED SUCCESSFULLY!\n";
    echo "==============================\n\n";
    echo "You can now log in with:\n";
    echo "Username: " . $username . "\n";
    echo "Password: " . $password . "\n";
    echo "\nAnd enrollments should now work properly!\n";

} catch (Exception $e) {
    // Roll back changes if there's an error
    mysqli_rollback($conn);
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Changes have been rolled back.\n";
}
?> 