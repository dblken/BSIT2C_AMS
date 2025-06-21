<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "USER ACCOUNT LIST\n";
echo "================\n\n";

// Include database connection
require_once 'config/database.php';

// Check users table
$check_users = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if (mysqli_num_rows($check_users) > 0) {
    echo "Users table exists, showing all accounts:\n\n";
    
    // Get all columns from users table
    $result = mysqli_query($conn, "SHOW COLUMNS FROM users");
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }
    
    // Build a query to fetch user information
    $select_columns = ['id'];
    
    // Add relevant columns if they exist
    if (in_array('username', $columns)) $select_columns[] = 'username';
    if (in_array('email', $columns)) $select_columns[] = 'email';
    if (in_array('role', $columns)) $select_columns[] = 'role';
    if (in_array('user_type', $columns)) $select_columns[] = 'user_type';
    
    $query = "SELECT " . implode(', ', $select_columns) . " FROM users LIMIT 10";
    $users = mysqli_query($conn, $query);
    
    if ($users && mysqli_num_rows($users) > 0) {
        $count = 1;
        while ($user = mysqli_fetch_assoc($users)) {
            echo "User #{$count}:\n";
            foreach ($user as $field => $value) {
                echo "- {$field}: {$value}\n";
            }
            echo "\n";
            $count++;
        }
    } else {
        echo "No users found in the users table.\n";
    }
} else {
    echo "Users table not found.\n";
}

// Check admins table
$check_admins = mysqli_query($conn, "SHOW TABLES LIKE 'admins'");
if (mysqli_num_rows($check_admins) > 0) {
    echo "Admins table exists, showing all accounts:\n\n";
    
    // Get all columns from admins table
    $result = mysqli_query($conn, "SHOW COLUMNS FROM admins");
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }
    
    // Build a query to fetch admin information
    $select_columns = ['id'];
    
    // Add relevant columns if they exist
    if (in_array('username', $columns)) $select_columns[] = 'username';
    if (in_array('email', $columns)) $select_columns[] = 'email';
    if (in_array('user_id', $columns)) $select_columns[] = 'user_id';
    
    $query = "SELECT " . implode(', ', $select_columns) . " FROM admins LIMIT 10";
    $admins = mysqli_query($conn, $query);
    
    if ($admins && mysqli_num_rows($admins) > 0) {
        $count = 1;
        while ($admin = mysqli_fetch_assoc($admins)) {
            echo "Admin #{$count}:\n";
            foreach ($admin as $field => $value) {
                echo "- {$field}: {$value}\n";
            }
            echo "\n";
            $count++;
        }
    } else {
        echo "No admins found in the admins table.\n";
    }
} else {
    echo "Admins table not found.\n";
}

echo "Password has been reset to: admin123\n";
echo "Use one of the displayed usernames to log in to the admin area.\n";
?> 