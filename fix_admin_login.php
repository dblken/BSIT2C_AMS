<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "FIXING ADMIN LOGIN ISSUES\n";
echo "=======================\n\n";

// Include database connection
require_once 'config/database.php';

// Begin transaction for safety
mysqli_begin_transaction($conn);

try {
    // Check if 'newadmin' exists in the users table
    $check_user = "SELECT id, username, role FROM users WHERE username = 'newadmin'";
    $user_result = mysqli_query($conn, $check_user);
    
    if (mysqli_num_rows($user_result) == 1) {
        $user = mysqli_fetch_assoc($user_result);
        echo "✅ Found 'newadmin' in users table (ID: {$user['id']})\n";
        
        // Check if this user is already in the admins table
        $check_admin = "SELECT id FROM admins WHERE user_id = {$user['id']}";
        $admin_result = mysqli_query($conn, $check_admin);
        
        if (mysqli_num_rows($admin_result) == 0) {
            // User not in admins table, add them
            $insert_admin = "INSERT INTO admins (user_id, username, password, email, full_name, first_name, last_name, created_at) 
                            VALUES ({$user['id']}, 'newadmin', '', 'newadmin@example.com', 'New Admin', 'New', 'Admin', NOW())";
            
            if (mysqli_query($conn, $insert_admin)) {
                echo "✅ Added 'newadmin' to admins table\n";
            } else {
                throw new Exception("Failed to add user to admins table: " . mysqli_error($conn));
            }
        } else {
            echo "✓ User already exists in admins table\n";
        }
    } else {
        echo "❌ 'newadmin' not found in users table\n";
        echo "   Creating new admin user...\n";
        
        // Create user in users table
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_user = "INSERT INTO users (username, password, role) VALUES ('newadmin', '$password_hash', 'admin')";
        
        if (mysqli_query($conn, $insert_user)) {
            $user_id = mysqli_insert_id($conn);
            echo "✅ Added 'newadmin' to users table (ID: $user_id)\n";
            
            // Add to admins table
            $insert_admin = "INSERT INTO admins (user_id, username, password, email, full_name, first_name, last_name, created_at) 
                            VALUES ($user_id, 'newadmin', '', 'newadmin@example.com', 'New Admin', 'New', 'Admin', NOW())";
            
            if (mysqli_query($conn, $insert_admin)) {
                echo "✅ Added 'newadmin' to admins table\n";
            } else {
                throw new Exception("Failed to add user to admins table: " . mysqli_error($conn));
            }
        } else {
            throw new Exception("Failed to create user: " . mysqli_error($conn));
        }
    }
    
    // Check if 'admin' exists in the users table
    $check_user = "SELECT id, username, role FROM users WHERE username = 'admin'";
    $user_result = mysqli_query($conn, $check_user);
    
    if (mysqli_num_rows($user_result) == 1) {
        $user = mysqli_fetch_assoc($user_result);
        echo "\n✅ Found 'admin' in users table (ID: {$user['id']})\n";
        
        // Check if this user is already in the admins table
        $check_admin = "SELECT id FROM admins WHERE user_id = {$user['id']}";
        $admin_result = mysqli_query($conn, $check_admin);
        
        if (mysqli_num_rows($admin_result) == 0) {
            // User not in admins table, add them
            $insert_admin = "INSERT INTO admins (user_id, username, password, email, full_name, first_name, last_name, created_at) 
                            VALUES ({$user['id']}, 'admin', '', 'admin@example.com', 'Admin User', 'Admin', 'User', NOW())";
            
            if (mysqli_query($conn, $insert_admin)) {
                echo "✅ Added 'admin' to admins table\n";
            } else {
                throw new Exception("Failed to add admin user to admins table: " . mysqli_error($conn));
            }
        } else {
            echo "✓ Admin user already exists in admins table\n";
        }
    }
    
    // All fixes have been applied successfully
    mysqli_commit($conn);
    echo "\n✅ ALL FIXES APPLIED SUCCESSFULLY!\n";
    echo "==============================\n\n";
    echo "You can now log in with:\n";
    echo "Username: newadmin\n";
    echo "Password: admin123\n";
    echo "\nOr try the default admin account if it exists.\n";

} catch (Exception $e) {
    // Roll back changes if there's an error
    mysqli_rollback($conn);
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Changes have been rolled back.\n";
}
?> 