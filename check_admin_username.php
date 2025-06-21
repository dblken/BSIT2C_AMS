<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "ADMIN USERNAME CHECKER\n";
echo "=====================\n\n";

// Include database connection
require_once 'config/database.php';

echo "Checking for admin accounts...\n\n";

// Check users table
$check_users = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if (mysqli_num_rows($check_users) > 0) {
    // Check structure to find username and role columns
    $columns = mysqli_query($conn, "DESCRIBE users");
    $has_username = false;
    $has_role = false;
    $has_user_type = false;
    
    while ($col = mysqli_fetch_assoc($columns)) {
        if ($col['Field'] == 'username') {
            $has_username = true;
        } else if ($col['Field'] == 'role') {
            $has_role = true;
        } else if ($col['Field'] == 'user_type') {
            $has_user_type = true;
        }
    }
    
    if ($has_username) {
        if ($has_role) {
            $query = "SELECT id, username, email FROM users WHERE role = 'admin' LIMIT 5";
        } else if ($has_user_type) {
            $query = "SELECT id, username, email FROM users WHERE user_type = 'admin' LIMIT 5";
        } else {
            // Try to find admin accounts without role
            $query = "SELECT id, username, email FROM users LIMIT 5";
        }
        
        $result = mysqli_query($conn, $query);
        if (mysqli_num_rows($result) > 0) {
            echo "Found admin accounts in users table:\n";
            while ($row = mysqli_fetch_assoc($result)) {
                echo "- Username: {$row['username']}\n";
                if (isset($row['email'])) {
                    echo "  Email: {$row['email']}\n";
                }
                echo "\n";
            }
        } else {
            echo "No admin accounts found in users table.\n";
        }
    } else {
        echo "Users table doesn't have a username column.\n";
    }
} else {
    echo "No users table found.\n";
}

// Check admins table
$check_admins = mysqli_query($conn, "SHOW TABLES LIKE 'admins'");
if (mysqli_num_rows($check_admins) > 0) {
    $has_username = false;
    $columns = mysqli_query($conn, "DESCRIBE admins");
    
    while ($col = mysqli_fetch_assoc($columns)) {
        if ($col['Field'] == 'username') {
            $has_username = true;
            break;
        }
    }
    
    if ($has_username) {
        $query = "SELECT id, username, email FROM admins LIMIT 5";
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) > 0) {
            echo "Found accounts in admins table:\n";
            while ($row = mysqli_fetch_assoc($result)) {
                echo "- Username: {$row['username']}\n";
                if (isset($row['email'])) {
                    echo "  Email: {$row['email']}\n";
                }
                echo "\n";
            }
        } else {
            echo "No accounts found in admins table.\n";
        }
    } else {
        echo "Admins table doesn't have a username column.\n";
    }
} else {
    echo "No admins table found.\n";
}

echo "Done. You can now log in with the displayed username and password: admin123\n";
?> 