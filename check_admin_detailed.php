<?php
// Display errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/database.php';

echo "<h1>Advanced Admin Login Debugging</h1>";

// Check if admins table exists
$tables = $conn->query("SHOW TABLES LIKE 'admins'");
if ($tables->num_rows == 0) {
    echo "<p style='color:red'>The 'admins' table does not exist in the database.</p>";
    exit;
}

// Check if users table exists (in case authentication is through users table)
$tables = $conn->query("SHOW TABLES LIKE 'users'");
$users_table_exists = ($tables->num_rows > 0);

if ($users_table_exists) {
    echo "<p style='color:blue'>Note: A 'users' table was found. Some systems use a separate users table for authentication.</p>";
    
    // Get users table structure
    echo "<h2>Users Table Structure</h2>";
    $structure = $conn->query("DESCRIBE users");
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $structure->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $key => $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if there's a role column in users table
    $check_role = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    $has_role_column = ($check_role->num_rows > 0);
    
    if ($has_role_column) {
        // Show admin users from users table
        echo "<h2>Admin Users in Users Table</h2>";
        $admin_users = $conn->query("SELECT id, username, SUBSTRING(password, 1, 10) as password_preview, 
                                LENGTH(password) as password_length, last_login 
                                FROM users WHERE role = 'admin' LIMIT 5");
        
        if ($admin_users && $admin_users->num_rows > 0) {
            echo "<table border='1'><tr><th>ID</th><th>Username</th><th>Password Preview</th><th>Password Length</th><th>Last Login</th></tr>";
            while ($row = $admin_users->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                echo "<td>" . htmlspecialchars($row['password_preview']) . "...</td>";
                echo "<td>" . htmlspecialchars($row['password_length']) . "</td>";
                echo "<td>" . htmlspecialchars($row['last_login'] ?? 'Never') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No admin users found in the users table.</p>";
        }
    }
}

// Get admins table structure
echo "<h2>Admins Table Structure</h2>";
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

// Check if there's a user_id column in admins table (indicating a relation to users table)
$check_user_id = $conn->query("SHOW COLUMNS FROM admins LIKE 'user_id'");
$has_user_id = ($check_user_id->num_rows > 0);

if ($has_user_id && $users_table_exists) {
    echo "<p style='color:blue'>Note: The admins table has a user_id column, which likely means authentication is handled through the users table.</p>";
}

// Get admin accounts (hide full passwords)
echo "<h2>Admin Accounts</h2>";
$admins = $conn->query("SELECT * FROM admins LIMIT 5");

if ($admins->num_rows == 0) {
    echo "<p style='color:red'>No admin accounts found in the database.</p>";
} else {
    echo "<table border='1'><tr>";
    // Get column names
    $fields = $admins->fetch_fields();
    foreach ($fields as $field) {
        echo "<th>" . htmlspecialchars($field->name) . "</th>";
    }
    echo "</tr>";
    
    // Reset result pointer
    $admins->data_seek(0);
    
    // Display data
    while ($row = $admins->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $key => $value) {
            if ($key == 'password') {
                echo "<td>" . htmlspecialchars(substr($value, 0, 10)) . "... (length: " . strlen($value) . ")</td>";
            } else {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
        }
        echo "</tr>";
    }
    echo "</table>";
}

// Test login function
echo "<h2>Login Test</h2>";
echo "<form method='post'>";
echo "<label>Username: <input type='text' name='username' required></label><br><br>";
echo "<label>Password: <input type='password' name='password' required></label><br><br>";
echo "<button type='submit'>Test Login</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    echo "<h3>Testing login for username: " . htmlspecialchars($username) . "</h3>";
    
    // METHOD 1: Check direct in admins table
    echo "<h4>Method 1: Direct check in admins table</h4>";
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    if ($admin) {
        echo "<p>User found in admins table with ID: " . htmlspecialchars($admin['id']) . "</p>";
        echo "<p>Stored password: " . htmlspecialchars($admin['password']) . " (length: " . strlen($admin['password']) . ")</p>";
        
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
        }
        
        // Check MD5
        $md5_hash = md5($password);
        echo "<p>MD5 hash of entered password: " . $md5_hash . "</p>";
        if ($md5_hash === $admin['password']) {
            echo "<p style='color:green'>✓ MD5 hash match successful!</p>";
        } else {
            echo "<p style='color:red'>✗ MD5 hash match failed.</p>";
        }
        
        // Check SHA-1
        $sha1_hash = sha1($password);
        echo "<p>SHA-1 hash of entered password: " . $sha1_hash . "</p>";
        if ($sha1_hash === $admin['password']) {
            echo "<p style='color:green'>✓ SHA-1 hash match successful!</p>";
        } else {
            echo "<p style='color:red'>✗ SHA-1 hash match failed.</p>";
        }
    } else {
        echo "<p style='color:red'>User not found in admins table.</p>";
    }
    
    // METHOD 2: Check in users table if it exists
    if ($users_table_exists) {
        echo "<h4>Method 2: Check in users table</h4>";
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            echo "<p>User found in users table with ID: " . htmlspecialchars($user['id']) . "</p>";
            
            if (isset($user['role'])) {
                echo "<p>User role: " . htmlspecialchars($user['role']) . "</p>";
            }
            
            echo "<p>Stored password: " . htmlspecialchars($user['password']) . " (length: " . strlen($user['password']) . ")</p>";
            
            // Check direct password match
            if ($password === $user['password']) {
                echo "<p style='color:green'>✓ Direct password match successful!</p>";
            } else {
                echo "<p style='color:red'>✗ Direct password match failed.</p>";
            }
            
            // Check password_verify
            if (password_verify($password, $user['password'])) {
                echo "<p style='color:green'>✓ password_verify() successful!</p>";
            } else {
                echo "<p style='color:red'>✗ password_verify() failed.</p>";
            }
            
            // Check MD5
            $md5_hash = md5($password);
            echo "<p>MD5 hash of entered password: " . $md5_hash . "</p>";
            if ($md5_hash === $user['password']) {
                echo "<p style='color:green'>✓ MD5 hash match successful!</p>";
            } else {
                echo "<p style='color:red'>✗ MD5 hash match failed.</p>";
            }
            
            // Check SHA-1
            $sha1_hash = sha1($password);
            echo "<p>SHA-1 hash of entered password: " . $sha1_hash . "</p>";
            if ($sha1_hash === $user['password']) {
                echo "<p style='color:green'>✓ SHA-1 hash match successful!</p>";
            } else {
                echo "<p style='color:red'>✗ SHA-1 hash match failed.</p>";
            }
            
            // Check if this user is linked to an admin
            if ($has_user_id) {
                $stmt = $conn->prepare("SELECT * FROM admins WHERE user_id = ?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $linked_admin = $result->fetch_assoc();
                
                if ($linked_admin) {
                    echo "<p style='color:green'>✓ This user is linked to an admin record with ID: " . htmlspecialchars($linked_admin['id']) . "</p>";
                } else {
                    echo "<p style='color:red'>✗ This user is not linked to any admin record.</p>";
                }
            }
        } else {
            echo "<p style='color:red'>User not found in users table.</p>";
        }
    }
    
    // METHOD 3: Test the actual login code from admin/index.php
    echo "<h4>Method 3: Using the actual login code from admin/index.php</h4>";
    
    // Check if the admin exists
    $sql = "SELECT * FROM admins WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if ($admin) {
        $login_successful = false;
        
        // Method 1: Direct comparison (plain text password)
        if ($password === $admin['password']) {
            echo "<p style='color:green'>✓ Direct password match successful!</p>";
            $login_successful = true;
        } else {
            echo "<p style='color:red'>✗ Direct password match failed.</p>";
        }
        
        // Method 2: bcrypt hash (password_verify)
        if (password_verify($password, $admin['password'])) {
            echo "<p style='color:green'>✓ password_verify() successful!</p>";
            $login_successful = true;
        } else {
            echo "<p style='color:red'>✗ password_verify() failed.</p>";
        }
        
        // Method 3: MD5 hash
        if (strlen($admin['password']) == 32 && ctype_xdigit($admin['password']) && 
                md5($password) === $admin['password']) {
            echo "<p style='color:green'>✓ MD5 hash match successful!</p>";
            $login_successful = true;
        } else {
            echo "<p style='color:red'>✗ MD5 hash match failed.</p>";
        }
        
        // Method 4: SHA-1 hash
        if (strlen($admin['password']) == 40 && ctype_xdigit($admin['password']) && 
                sha1($password) === $admin['password']) {
            echo "<p style='color:green'>✓ SHA-1 hash match successful!</p>";
            $login_successful = true;
        } else {
            echo "<p style='color:red'>✗ SHA-1 hash match failed.</p>";
        }
        
        if ($login_successful) {
            echo "<p style='color:green; font-weight: bold; font-size: 16px;'>✓ Login would be successful with the current code!</p>";
        } else {
            echo "<p style='color:red; font-weight: bold; font-size: 16px;'>✗ Login would fail with the current code.</p>";
        }
    } else {
        echo "<p style='color:red'>User not found in admins table.</p>";
    }
    
    // Additional check for special cases
    echo "<h4>Additional Checks</h4>";
    
    // Check for MD5 with salt
    echo "<p>Checking for MD5 with salt (common pattern: salt:md5hash)...</p>";
    if ($admin && strpos($admin['password'], ':') !== false) {
        list($salt, $hash) = explode(':', $admin['password'], 2);
        echo "<p>Found salt: " . htmlspecialchars($salt) . "</p>";
        echo "<p>Found hash: " . htmlspecialchars($hash) . "</p>";
        
        $salted_md5 = md5($salt . $password);
        echo "<p>MD5 of salt + password: " . $salted_md5 . "</p>";
        
        if ($salted_md5 === $hash) {
            echo "<p style='color:green'>✓ MD5 with salt match successful!</p>";
        } else {
            echo "<p style='color:red'>✗ MD5 with salt match failed.</p>";
        }
    }
}

// Add a section to create a new admin account
echo "<h2>Create Test Admin Account</h2>";
echo "<p>If you can't log in with any existing accounts, you can create a new admin account for testing:</p>";
echo "<form method='post' action='?action=create_admin'>";
echo "<label>Username: <input type='text' name='new_username' required></label><br><br>";
echo "<label>Password: <input type='password' name='new_password' required></label><br><br>";
echo "<label>Password Storage Method: 
      <select name='hash_method'>
        <option value='plain'>Plain Text (not recommended)</option>
        <option value='bcrypt' selected>Bcrypt (recommended)</option>
        <option value='md5'>MD5</option>
        <option value='sha1'>SHA-1</option>
      </select>
      </label><br><br>";
echo "<button type='submit'>Create Admin Account</button>";
echo "</form>";

// Handle admin creation
if (isset($_GET['action']) && $_GET['action'] == 'create_admin' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_username = $_POST['new_username'];
    $new_password = $_POST['new_password'];
    $hash_method = $_POST['hash_method'];
    
    // Hash the password according to the selected method
    $hashed_password = $new_password; // Default to plain text
    
    if ($hash_method == 'bcrypt') {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    } elseif ($hash_method == 'md5') {
        $hashed_password = md5($new_password);
    } elseif ($hash_method == 'sha1') {
        $hashed_password = sha1($new_password);
    }
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->bind_param("s", $new_username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p style='color:red'>Username already exists. Please choose a different username.</p>";
    } else {
        // Get the required fields for the admins table
        $fields = array();
        $structure = $conn->query("DESCRIBE admins");
        while ($row = $structure->fetch_assoc()) {
            $fields[] = $row['Field'];
        }
        
        // Build the insert query based on the table structure
        $query = "INSERT INTO admins (username, password";
        $values = "VALUES (?, ?";
        $types = "ss"; // string, string for username and password
        $params = array($new_username, $hashed_password);
        
        // Add email field if it exists
        if (in_array('email', $fields)) {
            $query .= ", email";
            $values .= ", ?";
            $types .= "s";
            $params[] = $new_username . "@example.com"; // Default email
        }
        
        // Close the query
        $query .= ") " . $values . ")";
        
        // Prepare and execute the statement
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            echo "<p style='color:green'>Admin account created successfully! Username: " . htmlspecialchars($new_username) . ", Password: " . htmlspecialchars($new_password) . "</p>";
            echo "<p>You can now try to log in with these credentials.</p>";
        } else {
            echo "<p style='color:red'>Error creating admin account: " . $conn->error . "</p>";
        }
    }
}
?> 