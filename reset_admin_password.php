<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "ADMIN PASSWORD RESET TOOL\n";
echo "========================\n\n";

// Include database connection
require_once 'config/database.php';

// New password to set
$new_password = 'admin123'; // Default password
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

echo "Checking database tables...\n";

// First check which table contains admin users
$check_admins = mysqli_query($conn, "SHOW TABLES LIKE 'admins'");
$check_users = mysqli_query($conn, "SHOW TABLES LIKE 'users'");

if (mysqli_num_rows($check_admins) > 0) {
    echo "Found 'admins' table...\n";
    
    // Check if the admins table has the right columns
    $columns = mysqli_query($conn, "DESCRIBE admins");
    $structure = [];
    while ($col = mysqli_fetch_assoc($columns)) {
        $structure[] = $col['Field'];
    }
    
    if (in_array('password', $structure)) {
        // Reset the password for all admin users
        $update = mysqli_query($conn, "UPDATE admins SET password = '$password_hash'");
        
        if ($update) {
            $affected = mysqli_affected_rows($conn);
            echo "✅ Successfully reset password for $affected admin accounts to: $new_password\n";
        } else {
            echo "❌ Error updating admin passwords: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "❌ Couldn't find 'password' column in admins table\n";
    }
}

if (mysqli_num_rows($check_users) > 0) {
    echo "\nFound 'users' table...\n";
    
    // Check if users table has admin role indicator
    $columns = mysqli_query($conn, "DESCRIBE users");
    $structure = [];
    while ($col = mysqli_fetch_assoc($columns)) {
        $structure[] = $col['Field'];
    }
    
    if (in_array('role', $structure) && in_array('password', $structure)) {
        // Update passwords for admin users
        $update = mysqli_query($conn, "UPDATE users SET password = '$password_hash' WHERE role = 'admin'");
        
        if ($update) {
            $affected = mysqli_affected_rows($conn);
            echo "✅ Successfully reset password for $affected admin accounts in users table to: $new_password\n";
        } else {
            echo "❌ Error updating admin passwords in users table: " . mysqli_error($conn) . "\n";
        }
    } else if (in_array('user_type', $structure) && in_array('password', $structure)) {
        // Alternative column name for role
        $update = mysqli_query($conn, "UPDATE users SET password = '$password_hash' WHERE user_type = 'admin'");
        
        if ($update) {
            $affected = mysqli_affected_rows($conn);
            echo "✅ Successfully reset password for $affected admin accounts in users table to: $new_password\n";
        } else {
            echo "❌ Error updating admin passwords in users table: " . mysqli_error($conn) . "\n";
        }
    } else if (in_array('password', $structure)) {
        // If no role column found, ask if we should reset all user passwords
        echo "⚠️ No role/user_type column found in users table.\n";
        echo "⚠️ Would reset ALL user passwords if uncommented. Edit the script to enable this.\n";
        
        // Uncomment the following lines to reset ALL user passwords (use with caution)
        /*
        $update = mysqli_query($conn, "UPDATE users SET password = '$password_hash'");
        
        if ($update) {
            $affected = mysqli_affected_rows($conn);
            echo "✅ Reset password for ALL $affected user accounts to: $new_password\n";
        } else {
            echo "❌ Error updating user passwords: " . mysqli_error($conn) . "\n";
        }
        */
    }
}

// Check for a combined users table with user_id+admin_id
$check_admin_users = mysqli_query($conn, "SHOW TABLES LIKE 'admins'");
$check_user_table = mysqli_query($conn, "SHOW TABLES LIKE 'users'");

if (mysqli_num_rows($check_admin_users) > 0 && mysqli_num_rows($check_user_table) > 0) {
    echo "\nChecking for linked admins and users tables...\n";
    
    // Check if admins table has user_id column
    $admin_columns = mysqli_query($conn, "DESCRIBE admins");
    $admin_has_user_id = false;
    while ($col = mysqli_fetch_assoc($admin_columns)) {
        if ($col['Field'] == 'user_id') {
            $admin_has_user_id = true;
            break;
        }
    }
    
    if ($admin_has_user_id) {
        echo "Found relationship between admins and users tables.\n";
        
        // Find admin user IDs
        $admin_users = mysqli_query($conn, "SELECT user_id FROM admins");
        $user_ids = [];
        
        while ($row = mysqli_fetch_assoc($admin_users)) {
            $user_ids[] = $row['user_id'];
        }
        
        if (count($user_ids) > 0) {
            $ids = implode(',', $user_ids);
            $update = mysqli_query($conn, "UPDATE users SET password = '$password_hash' WHERE id IN ($ids)");
            
            if ($update) {
                $affected = mysqli_affected_rows($conn);
                echo "✅ Reset password for $affected admin accounts (via user_id relation) to: $new_password\n";
            } else {
                echo "❌ Error updating admin passwords via user relation: " . mysqli_error($conn) . "\n";
            }
        } else {
            echo "⚠️ No admin user IDs found.\n";
        }
    }
}

// Create a default admin if none exists
$has_admin = false;

if (mysqli_num_rows($check_admins) > 0) {
    $admin_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM admins");
    $count = mysqli_fetch_assoc($admin_count)['count'];
    $has_admin = ($count > 0);
}

if (!$has_admin) {
    echo "\nNo admin accounts found. Creating a default admin account...\n";
    
    if (mysqli_num_rows($check_admins) > 0) {
        // Get the structure to determine required fields
        $columns = mysqli_query($conn, "DESCRIBE admins");
        $structure = [];
        while ($col = mysqli_fetch_assoc($columns)) {
            $structure[$col['Field']] = $col;
        }
        
        // Build a query with required fields
        $fields = [];
        $values = [];
        
        if (isset($structure['username'])) {
            $fields[] = 'username';
            $values[] = "'admin'";
        }
        
        if (isset($structure['password'])) {
            $fields[] = 'password';
            $values[] = "'$password_hash'";
        }
        
        if (isset($structure['email'])) {
            $fields[] = 'email';
            $values[] = "'admin@example.com'";
        }
        
        if (isset($structure['first_name'])) {
            $fields[] = 'first_name';
            $values[] = "'System'";
        }
        
        if (isset($structure['last_name'])) {
            $fields[] = 'last_name';
            $values[] = "'Administrator'";
        }
        
        if (isset($structure['full_name'])) {
            $fields[] = 'full_name';
            $values[] = "'System Administrator'";
        }
        
        if (isset($structure['created_at'])) {
            $fields[] = 'created_at';
            $values[] = "NOW()";
        }
        
        if (count($fields) > 0) {
            $query = "INSERT INTO admins (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
            
            if (mysqli_query($conn, $query)) {
                echo "✅ Created default admin account with username 'admin' and password '$new_password'\n";
            } else {
                echo "❌ Error creating default admin: " . mysqli_error($conn) . "\n";
            }
        }
    }
}

echo "\nDone. You can now log in with:\n";
echo "Username: admin\n";
echo "Password: $new_password\n";
?> 