<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "CHECKING ADMIN ACCOUNTS\n";
echo "======================\n\n";

// Include database connection
require_once 'config/database.php';

// Function to check if a user exists in the users table
function checkUser($conn, $username) {
    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Function to check if a user exists in the admins table
function checkAdmin($conn, $user_id) {
    $sql = "SELECT id FROM admins WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows == 1;
}

// Function to verify password hash
function verifyPassword($password, $hash) {
    // Method 1: Direct comparison
    $direct = ($password === $hash);
    
    // Method 2: password_verify() for bcrypt hashes
    $bcrypt = password_verify($password, $hash);
    
    return [
        'direct_comparison' => $direct,
        'password_verify' => $bcrypt,
        'result' => $direct || $bcrypt
    ];
}

// Check 'admin' account
echo "1. Checking 'admin' account:\n";
$admin_user = checkUser($conn, 'admin');

if ($admin_user) {
    echo "✅ User found in users table:\n";
    echo "   ID: {$admin_user['id']}\n";
    echo "   Username: {$admin_user['username']}\n";
    echo "   Role: {$admin_user['role']}\n";
    
    // Check if user is in admins table
    if (checkAdmin($conn, $admin_user['id'])) {
        echo "✅ User exists in admins table\n";
    } else {
        echo "❌ User does NOT exist in admins table\n";
    }
    
    // Verify password
    $test_passwords = ['admin123', 'admin'];
    foreach ($test_passwords as $test_password) {
        $verify_results = verifyPassword($test_password, $admin_user['password']);
        echo "\n   Testing password '{$test_password}':\n";
        echo "   - Direct comparison: " . ($verify_results['direct_comparison'] ? 'TRUE' : 'FALSE') . "\n";
        echo "   - password_verify(): " . ($verify_results['password_verify'] ? 'TRUE' : 'FALSE') . "\n";
        echo "   - Final result: " . ($verify_results['result'] ? 'Password matches!' : 'Password does NOT match') . "\n";
    }
    
    // Show stored hash
    echo "\n   Stored password hash: {$admin_user['password']}\n";
} else {
    echo "❌ 'admin' user NOT found in users table\n";
}

// Check 'newadmin' account
echo "\n2. Checking 'newadmin' account:\n";
$newadmin_user = checkUser($conn, 'newadmin');

if ($newadmin_user) {
    echo "✅ User found in users table:\n";
    echo "   ID: {$newadmin_user['id']}\n";
    echo "   Username: {$newadmin_user['username']}\n";
    echo "   Role: {$newadmin_user['role']}\n";
    
    // Check if user is in admins table
    if (checkAdmin($conn, $newadmin_user['id'])) {
        echo "✅ User exists in admins table\n";
    } else {
        echo "❌ User does NOT exist in admins table\n";
    }
    
    // Verify password
    $test_passwords = ['admin123', 'newadmin'];
    foreach ($test_passwords as $test_password) {
        $verify_results = verifyPassword($test_password, $newadmin_user['password']);
        echo "\n   Testing password '{$test_password}':\n";
        echo "   - Direct comparison: " . ($verify_results['direct_comparison'] ? 'TRUE' : 'FALSE') . "\n";
        echo "   - password_verify(): " . ($verify_results['password_verify'] ? 'TRUE' : 'FALSE') . "\n";
        echo "   - Final result: " . ($verify_results['result'] ? 'Password matches!' : 'Password does NOT match') . "\n";
    }
    
    // Show stored hash
    echo "\n   Stored password hash: {$newadmin_user['password']}\n";
} else {
    echo "❌ 'newadmin' user NOT found in users table\n";
}

// Check structure of admins table
echo "\n3. Checking admins table structure:\n";
$check_admins = mysqli_query($conn, "SHOW TABLES LIKE 'admins'");

if (mysqli_num_rows($check_admins) > 0) {
    echo "✅ admins table exists\n";
    
    $columns = mysqli_query($conn, "DESCRIBE admins");
    echo "\n   Columns in admins table:\n";
    
    while ($col = mysqli_fetch_assoc($columns)) {
        echo "   - {$col['Field']} ({$col['Type']})" . ($col['Key'] == 'PRI' ? ' [PRIMARY KEY]' : '') . "\n";
    }
} else {
    echo "❌ admins table does NOT exist\n";
}

echo "\n======================\n";
echo "TROUBLESHOOTING TIPS:\n";
echo "1. Make sure the username exists in the users table\n";
echo "2. Make sure the user has role 'admin' in the users table\n";
echo "3. Make sure there's a corresponding entry in the admins table\n";
echo "4. Try using the password 'admin123' which should work with both direct comparison and bcrypt verification\n";
?> 