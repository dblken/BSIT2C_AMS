<?php
require_once 'config/database.php';

echo "Teacher Login Credential Checker\n\n";

// Get a sample teacher account
$query = "SELECT u.username, u.password, t.first_name, t.last_name 
          FROM users u 
          JOIN teachers t ON u.id = t.user_id 
          WHERE u.role = 'teacher' 
          LIMIT 1";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $teacher = $result->fetch_assoc();
    echo "Sample Teacher Account Found:\n";
    echo "Username: " . $teacher['username'] . "\n";
    echo "Password: " . $teacher['password'] . "\n";
    echo "Name: " . $teacher['first_name'] . " " . $teacher['last_name'] . "\n";
    
    echo "\nIs password hashed? " . (password_get_info($teacher['password'])['algo'] !== 0 ? "Yes" : "No") . "\n";
} else {
    echo "No teacher accounts found in the database.\n";
    
    // Check if teachers table exists
    $tables_result = $conn->query("SHOW TABLES LIKE 'teachers'");
    if ($tables_result->num_rows == 0) {
        echo "The 'teachers' table does not exist.\n";
    }
    
    // Check if users table exists
    $tables_result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($tables_result->num_rows == 0) {
        echo "The 'users' table does not exist.\n";
    }
}

// Count all teachers
$count_result = $conn->query("SELECT COUNT(*) as count FROM teachers");
if ($count_result) {
    $count = $count_result->fetch_assoc()['count'];
    echo "\nTotal teachers in database: " . $count . "\n";
}
?> 