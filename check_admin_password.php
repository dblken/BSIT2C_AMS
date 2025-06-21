<?php
// Admin password diagnostic and fix tool
session_start();
require_once 'config/database.php';

// Security check - only allow on localhost
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost'])) {
    die("This tool can only be accessed from localhost for security reasons.");
}

$message = '';
$admin_data = [];
$password_test = '';

// Get all admins
$sql = "SELECT * FROM admins";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $admin_data[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'password' => $row['password'],
            'password_length' => strlen($row['password']),
            'is_md5' => (strlen($row['password']) == 32 && ctype_xdigit($row['password'])),
            'is_sha1' => (strlen($row['password']) == 40 && ctype_xdigit($row['password'])),
            'is_bcrypt' => (strpos($row['password'], '$2y$') === 0 || strpos($row['password'], '$2a$') === 0),
            'has_salt_separator' => (strpos($row['password'], ':') !== false)
        ];
    }
}

// Process password test
if (isset($_POST['test_login'])) {
    $test_username = $_POST['test_username'];
    $test_password = $_POST['test_password'];
    
    // Find the admin
    $found = false;
    foreach ($admin_data as $admin) {
        if ($admin['username'] === $test_username) {
            $found = true;
            $password_test = [
                'username' => $test_username,
                'input_password' => $test_password,
                'stored_password' => $admin['password'],
                'plain_match' => ($test_password === $admin['password']),
                'bcrypt_match' => password_verify($test_password, $admin['password']),
                'md5_match' => (md5($test_password) === $admin['password']),
                'sha1_match' => (sha1($test_password) === $admin['password']),
                'trimmed_match' => (trim($test_password) === $admin['password'] || $test_password === trim($admin['password'])),
                'case_insensitive_match' => (strcasecmp($test_password, $admin['password']) === 0)
            ];
            
            // Check salted MD5
            if (strpos($admin['password'], ':') !== false) {
                list($salt, $hash) = explode(':', $admin['password'], 2);
                $password_test['salted_md5_match'] = (md5($salt . $test_password) === $hash);
            } else {
                $password_test['salted_md5_match'] = false;
            }
            
            break;
        }
    }
    
    if (!$found) {
        $message = "Username not found.";
    }
}

// Process password fix
if (isset($_POST['fix_password'])) {
    $admin_id = $_POST['admin_id'];
    $new_password = $_POST['new_password'];
    $hash_method = $_POST['hash_method'];
    
    $hashed_password = '';
    switch ($hash_method) {
        case 'plain':
            $hashed_password = $new_password;
            break;
        case 'md5':
            $hashed_password = md5($new_password);
            break;
        case 'sha1':
            $hashed_password = sha1($new_password);
            break;
        case 'bcrypt':
        default:
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            break;
    }
    
    $sql = "UPDATE admins SET password = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $hashed_password, $admin_id);
    
    if ($stmt->execute()) {
        $message = "Password updated successfully for admin ID: $admin_id";
        
        // Refresh admin data
        $sql = "SELECT * FROM admins";
        $result = $conn->query($sql);
        $admin_data = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $admin_data[] = [
                    'id' => $row['id'],
                    'username' => $row['username'],
                    'password' => $row['password'],
                    'password_length' => strlen($row['password']),
                    'is_md5' => (strlen($row['password']) == 32 && ctype_xdigit($row['password'])),
                    'is_sha1' => (strlen($row['password']) == 40 && ctype_xdigit($row['password'])),
                    'is_bcrypt' => (strpos($row['password'], '$2y$') === 0 || strpos($row['password'], '$2a$') === 0),
                    'has_salt_separator' => (strpos($row['password'], ':') !== false)
                ];
            }
        }
    } else {
        $message = "Error updating password: " . $stmt->error;
    }
}

// Create new admin
if (isset($_POST['create_admin'])) {
    $new_username = $_POST['new_username'];
    $new_password = $_POST['create_password'];
    $hash_method = $_POST['create_hash_method'];
    
    // Check if username exists
    $check_sql = "SELECT id FROM admins WHERE username = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $new_username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $message = "Error: Username already exists.";
    } else {
        $hashed_password = '';
        switch ($hash_method) {
            case 'plain':
                $hashed_password = $new_password;
                break;
            case 'md5':
                $hashed_password = md5($new_password);
                break;
            case 'sha1':
                $hashed_password = sha1($new_password);
                break;
            case 'bcrypt':
            default:
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                break;
        }
        
        $sql = "INSERT INTO admins (username, password, created_at) VALUES (?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $new_username, $hashed_password);
        
        if ($stmt->execute()) {
            $message = "New admin created successfully with username: $new_username";
            
            // Refresh admin data
            $sql = "SELECT * FROM admins";
            $result = $conn->query($sql);
            $admin_data = [];
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $admin_data[] = [
                        'id' => $row['id'],
                        'username' => $row['username'],
                        'password' => $row['password'],
                        'password_length' => strlen($row['password']),
                        'is_md5' => (strlen($row['password']) == 32 && ctype_xdigit($row['password'])),
                        'is_sha1' => (strlen($row['password']) == 40 && ctype_xdigit($row['password'])),
                        'is_bcrypt' => (strpos($row['password'], '$2y$') === 0 || strpos($row['password'], '$2a$') === 0),
                        'has_salt_separator' => (strpos($row['password'], ':') !== false)
                    ];
                }
            }
        } else {
            $message = "Error creating admin: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Password Diagnostic Tool</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1, h2, h3 {
            color: #021F3F;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #021F3F;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #C8A77E;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
        }
        .tab.active {
            background-color: white;
            border-color: #ddd;
            border-bottom: 1px solid white;
            margin-bottom: -1px;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .success {
            color: green;
            font-weight: bold;
        }
        .failure {
            color: red;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Password Diagnostic Tool</h1>
        
        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" data-tab="admin-list">Admin List</div>
            <div class="tab" data-tab="test-login">Test Login</div>
            <div class="tab" data-tab="fix-password">Fix Password</div>
            <div class="tab" data-tab="create-admin">Create Admin</div>
        </div>
        
        <div class="tab-content active" id="admin-list">
            <h2>Admin Accounts</h2>
            <?php if (empty($admin_data)): ?>
                <p>No admin accounts found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Password (Masked)</th>
                            <th>Password Length</th>
                            <th>Password Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admin_data as $admin): ?>
                            <tr>
                                <td><?php echo $admin['id']; ?></td>
                                <td><?php echo $admin['username']; ?></td>
                                <td><?php echo substr($admin['password'], 0, 3) . '...' . substr($admin['password'], -3); ?></td>
                                <td><?php echo $admin['password_length']; ?></td>
                                <td>
                                    <?php
                                    if ($admin['is_bcrypt']) echo "bcrypt hash";
                                    elseif ($admin['is_md5']) echo "MD5 hash";
                                    elseif ($admin['is_sha1']) echo "SHA-1 hash";
                                    elseif ($admin['has_salt_separator']) echo "Salted hash";
                                    else echo "Plain text or unknown";
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="tab-content" id="test-login">
            <h2>Test Login Credentials</h2>
            <div class="card">
                <form method="post">
                    <div class="form-group">
                        <label for="test_username">Username:</label>
                        <input type="text" id="test_username" name="test_username" required>
                    </div>
                    <div class="form-group">
                        <label for="test_password">Password:</label>
                        <input type="password" id="test_password" name="test_password" required>
                    </div>
                    <button type="submit" name="test_login">Test Login</button>
                </form>
            </div>
            
            <?php if ($password_test): ?>
                <h3>Test Results for <?php echo htmlspecialchars($password_test['username']); ?></h3>
                <table>
                    <tr>
                        <th>Test Type</th>
                        <th>Result</th>
                    </tr>
                    <tr>
                        <td>Plain text comparison</td>
                        <td><?php echo $password_test['plain_match'] ? '<span class="success">Match</span>' : '<span class="failure">No match</span>'; ?></td>
                    </tr>
                    <tr>
                        <td>bcrypt verification</td>
                        <td><?php echo $password_test['bcrypt_match'] ? '<span class="success">Match</span>' : '<span class="failure">No match</span>'; ?></td>
                    </tr>
                    <tr>
                        <td>MD5 hash comparison</td>
                        <td><?php echo $password_test['md5_match'] ? '<span class="success">Match</span>' : '<span class="failure">No match</span>'; ?></td>
                    </tr>
                    <tr>
                        <td>SHA-1 hash comparison</td>
                        <td><?php echo $password_test['sha1_match'] ? '<span class="success">Match</span>' : '<span class="failure">No match</span>'; ?></td>
                    </tr>
                    <tr>
                        <td>Trimmed spaces comparison</td>
                        <td><?php echo $password_test['trimmed_match'] ? '<span class="success">Match</span>' : '<span class="failure">No match</span>'; ?></td>
                    </tr>
                    <tr>
                        <td>Case-insensitive comparison</td>
                        <td><?php echo $password_test['case_insensitive_match'] ? '<span class="success">Match</span>' : '<span class="failure">No match</span>'; ?></td>
                    </tr>
                    <?php if (isset($password_test['salted_md5_match'])): ?>
                    <tr>
                        <td>Salted MD5 comparison</td>
                        <td><?php echo $password_test['salted_md5_match'] ? '<span class="success">Match</span>' : '<span class="failure">No match</span>'; ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <div style="margin-top: 20px;">
                    <h4>Debug Information</h4>
                    <p>Input password: <?php echo htmlspecialchars($password_test['input_password']); ?></p>
                    <p>Stored password: <?php echo htmlspecialchars($password_test['stored_password']); ?></p>
                    <p>MD5 of input: <?php echo md5($password_test['input_password']); ?></p>
                    <p>SHA-1 of input: <?php echo sha1($password_test['input_password']); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="tab-content" id="fix-password">
            <h2>Fix Admin Password</h2>
            <div class="card">
                <form method="post">
                    <div class="form-group">
                        <label for="admin_id">Select Admin:</label>
                        <select id="admin_id" name="admin_id" required>
                            <?php foreach ($admin_data as $admin): ?>
                                <option value="<?php echo $admin['id']; ?>"><?php echo $admin['username']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="hash_method">Password Storage Method:</label>
                        <select id="hash_method" name="hash_method">
                            <option value="bcrypt">bcrypt (Recommended)</option>
                            <option value="md5">MD5</option>
                            <option value="sha1">SHA-1</option>
                            <option value="plain">Plain Text (Not Recommended)</option>
                        </select>
                    </div>
                    <button type="submit" name="fix_password">Update Password</button>
                </form>
            </div>
        </div>
        
        <div class="tab-content" id="create-admin">
            <h2>Create New Admin</h2>
            <div class="card">
                <form method="post">
                    <div class="form-group">
                        <label for="new_username">Username:</label>
                        <input type="text" id="new_username" name="new_username" required>
                    </div>
                    <div class="form-group">
                        <label for="create_password">Password:</label>
                        <input type="password" id="create_password" name="create_password" required>
                    </div>
                    <div class="form-group">
                        <label for="create_hash_method">Password Storage Method:</label>
                        <select id="create_hash_method" name="create_hash_method">
                            <option value="bcrypt">bcrypt (Recommended)</option>
                            <option value="md5">MD5</option>
                            <option value="sha1">SHA-1</option>
                            <option value="plain">Plain Text (Not Recommended)</option>
                        </select>
                    </div>
                    <button type="submit" name="create_admin">Create Admin</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs
                    tabs.forEach(t => t.classList.remove('active'));
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Hide all tab contents
                    const tabContents = document.querySelectorAll('.tab-content');
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Show the selected tab content
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html> 