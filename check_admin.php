<?php
// Display errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/database.php';

echo "<h1>Admin Credentials Check</h1>";

// Check if admins table exists
$tables = $conn->query("SHOW TABLES LIKE 'admins'");
if ($tables->num_rows == 0) {
    echo "<p style='color:red'>The 'admins' table does not exist in the database.</p>";
    exit;
}

// Get table structure
echo "<h2>Table Structure</h2>";
$structure = $conn->query("DESCRIBE admins");
echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $structure->fetch_assoc()) {
    echo "<tr>";
    foreach ($row as $key => $value) {
        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

// Get admin accounts (hide full passwords)
echo "<h2>Admin Accounts</h2>";
$admins = $conn->query("SELECT id, username, email, SUBSTRING(password, 1, 10) as password_preview, 
                        LENGTH(password) as password_length, last_login FROM admins");

if ($admins->num_rows == 0) {
    echo "<p style='color:red'>No admin accounts found in the database.</p>";
} else {
    echo "<table border='1'><tr><th>ID</th><th>Username</th><th>Email</th><th>Password Preview</th><th>Password Length</th><th>Last Login</th></tr>";
    while ($row = $admins->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['password_preview']) . "...</td>";
        echo "<td>" . htmlspecialchars($row['password_length']) . "</td>";
        echo "<td>" . htmlspecialchars($row['last_login'] ?? 'Never') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test login function
echo "<h2>Login Test</h2>";
echo "<form method='post'>";
echo "<label>Username: <input type='text' name='username' required></label><br>";
echo "<label>Password: <input type='password' name='password' required></label><br>";
echo "<button type='submit'>Test Login</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    echo "<h3>Testing login for username: " . htmlspecialchars($username) . "</h3>";
    
    // Check if the admin exists
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    if ($admin) {
        echo "<p>User found in database with ID: " . htmlspecialchars($admin['id']) . "</p>";
        echo "<p>Stored password (first 10 chars): " . htmlspecialchars(substr($admin['password'], 0, 10)) . "...</p>";
        echo "<p>Password length: " . strlen($admin['password']) . "</p>";
        
        // Check direct password match
        if ($password === $admin['password']) {
            echo "<p style='color:green'>✓ Direct password match successful!</p>";
        } else {
            echo "<p style='color:red'>✗ Direct password match failed.</p>";
        }
        
        // Check password_verify
        if (password_verify($password, $admin['password'])) {
            echo "<p style='color:green'>✓ password_verify() successful!</p>";
        } else {
            echo "<p style='color:red'>✗ password_verify() failed.</p>";
            
            // Check if it's an MD5 hash
            if (strlen($admin['password']) == 32 && ctype_xdigit($admin['password'])) {
                if (md5($password) === $admin['password']) {
                    echo "<p style='color:green'>✓ MD5 hash match successful!</p>";
                } else {
                    echo "<p style='color:red'>✗ MD5 hash match failed.</p>";
                }
            }
            
            // Check if it's a SHA-1 hash
            if (strlen($admin['password']) == 40 && ctype_xdigit($admin['password'])) {
                if (sha1($password) === $admin['password']) {
                    echo "<p style='color:green'>✓ SHA-1 hash match successful!</p>";
                } else {
                    echo "<p style='color:red'>✗ SHA-1 hash match failed.</p>";
                }
            }
        }
    } else {
        echo "<p style='color:red'>User not found in database.</p>";
    }
}
?> 