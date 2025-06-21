<?php
session_start();
require_once '../config/database.php';
require_once '../includes/login_protection.php'; // Include login protection

// Check if already logged in
redirect_if_logged_in();

// For debugging
$debug_mode = true; // Set to true to see detailed error messages
$debug_message = '';

// Fix empty password issue if needed
$check_admin_sql = "SELECT * FROM admins WHERE username = 'admin' LIMIT 1";
$check_result = $conn->query($check_admin_sql);
if ($check_result && $check_result->num_rows > 0) {
    $admin_data = $check_result->fetch_assoc();
    if (empty($admin_data['password'])) {
        // Admin exists but has empty password, let's fix it
        $new_password = md5('admin123'); // Using MD5 for compatibility
        $update_sql = "UPDATE admins SET password = '$new_password' WHERE id = " . $admin_data['id'];
        if ($conn->query($update_sql)) {
            $debug_message .= "Fixed empty admin password. Please try logging in with username 'admin' and password 'admin123'.<br>";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Check if the admin exists
    $sql = "SELECT * FROM admins WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if ($admin) {
        $login_successful = false;
        
        // For debugging - show stored password details
        if ($debug_mode) {
            $debug_message .= "Stored password: " . $admin['password'] . " (Length: " . strlen($admin['password']) . ")<br>";
            $debug_message .= "Input password: " . $password . " (Length: " . strlen($password) . ")<br>";
            $debug_message .= "MD5 of input: " . md5($password) . "<br>";
            $debug_message .= "SHA1 of input: " . sha1($password) . "<br>";
        }
        
        // Method 1: Direct comparison (plain text password)
        if ($password === $admin['password']) {
            $login_successful = true;
            $debug_message .= "Login successful via direct comparison";
        }
        // Method 2: bcrypt hash (password_verify)
        else if (password_verify($password, $admin['password'])) {
            $login_successful = true;
            $debug_message .= "Login successful via password_verify()";
        }
        // Method 3: MD5 hash
        else if (md5($password) === $admin['password']) {
            $login_successful = true;
            $debug_message .= "Login successful via MD5 hash";
        }
        // Method 4: SHA-1 hash
        else if (sha1($password) === $admin['password']) {
            $login_successful = true;
            $debug_message .= "Login successful via SHA-1 hash";
        }
        // Method 5: Check for MD5 with salt (common pattern: salt:md5hash)
        else if (strpos($admin['password'], ':') !== false) {
            list($salt, $hash) = explode(':', $admin['password'], 2);
            if (md5($salt . $password) === $hash) {
                $login_successful = true;
                $debug_message .= "Login successful via salted MD5 hash";
            }
        }
        // Method 6: Try trimming spaces from password (common issue)
        else if (trim($password) === $admin['password'] || $password === trim($admin['password'])) {
            $login_successful = true;
            $debug_message .= "Login successful after trimming spaces";
        }
        // Method 7: Try case-insensitive comparison
        else if (strcasecmp($password, $admin['password']) === 0) {
            $login_successful = true;
            $debug_message .= "Login successful via case-insensitive comparison";
        }
        
        if ($login_successful) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['role'] = 'admin';
            
            // Update last login
            $update_sql = "UPDATE admins SET last_login = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("i", $admin['id']);
            $stmt->execute();
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit();
        } else {
            $error = "Invalid password";
            if ($debug_mode) {
                $error .= "<br><small style='color:#ffcccc;'>Debug info: " . $debug_message . "</small>";
            }
        }
    } else {
        $error = "Admin not found";
    }
}

$error = isset($_SESSION['error']) ? $_SESSION['error'] : (isset($error) ? $error : '');
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - BSIT 2C AMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #021F3F;
            --secondary-color: #C8A77E;
            --primary-hover: #042b59;
            --secondary-hover: #b39268;
        }
        
        body { 
            font-family: 'Poppins', sans-serif;
            margin: 0; 
            padding: 0; 
            background: #fff url('../assets/images/backgrounds/admin-login-bg.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.92);
            z-index: 1;
        }
        .login-container { 
            width: 100%;
            max-width: 400px; 
            margin: 20px;
            padding: 40px; 
            background: var(--primary-color);
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2), 
                       0 15px 45px rgba(2, 31, 63, 0.25);
            transform: translateY(0);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            z-index: 2;
        }
        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.25),
                       0 20px 60px rgba(2, 31, 63, 0.3);
        }
        .logo-container {
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
        }
        .logo-container img {
            width: 150px;
            height: auto;
            margin-bottom: 0;
            filter: brightness(0) invert(1);
            transition: transform 0.3s ease;
        }
        .logo-container img:hover {
            transform: scale(1.05);
        }
        .login-container h2 {
            color: #fff;
            text-align: center;
            margin-bottom: 25px;
            font-size: 32px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        .form-group { 
            margin-bottom: 24px; 
            position: relative;
        }
        label { 
            display: block; 
            margin-bottom: 8px; 
            color: #fff;
            font-weight: 500;
            font-size: 14px;
            letter-spacing: 0.3px;
        }
        input[type="text"], input[type="password"] { 
            width: 100%; 
            padding: 14px; 
            border: 2px solid rgba(255,255,255,0.1); 
            border-radius: 8px; 
            box-sizing: border-box; 
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.05);
            color: #fff;
        }
        input[type="text"]::placeholder, input[type="password"]::placeholder {
            color: rgba(255,255,255,0.6);
        }
        input[type="text"]:focus, input[type="password"]:focus {
            border-color: var(--secondary-color);
            outline: none;
            background: rgba(255,255,255,0.1);
            box-shadow: 0 0 0 4px rgba(200, 167, 126, 0.1);
        }
        button { 
            width: 100%; 
            padding: 16px; 
            background: var(--secondary-color);
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 16px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        button:hover { 
            background: var(--secondary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(200, 167, 126, 0.3);
        }
        button:active {
            transform: translateY(1px);
        }
        .error { 
            color: #ffebeb; 
            margin-bottom: 20px; 
            text-align: center;
            padding: 12px;
            background: rgba(220, 53, 69, 0.2);
            border-radius: 8px;
            font-size: 14px;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        .links { 
            text-align: center; 
            margin-top: 30px; 
        }
        .links a { 
            color: var(--secondary-color);
            text-decoration: none; 
            margin: 0 15px; 
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            position: relative;
        }
        .links a:hover { 
            color: var(--secondary-hover);
        }
        .links a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -4px;
            left: 0;
            background-color: var(--secondary-color);
            transition: width 0.3s ease;
        }
        .links a:hover::after {
            width: 100%;
        }
        .password-container {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: rgba(255,255,255,0.6);
            transition: color 0.3s ease;
        }
        .toggle-password:hover {
            color: var(--secondary-color);
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.35);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
            padding: 32px 24px 24px 24px;
            max-width: 350px;
            width: 100%;
            position: relative;
            text-align: center;
        }
        .modal-close {
            position: absolute;
            top: 12px;
            right: 14px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #fff;
            border: 1px solid #eee;
            color: #021F3F;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            transition: box-shadow 0.2s;
            padding: 0;
        }
        .modal-close:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            background: #f7f7f7;
        }
        .modal-close i {
            font-size: 18px;
            line-height: 1;
        }
        .modal-box h3 {
            margin-bottom: 18px;
            color: #021F3F;
            font-size: 20px;
            font-weight: 600;
        }
        .modal-box button[type="submit"] {
            width: 100%;
            background: #021F3F;
            color: white;
            padding: 10px 0;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .modal-box button[type="submit"]:hover {
            background: #d4b794;
            color: #021F3F;
        }
        .modal-box #forgotPasswordMsg {
            margin-top: 8px;
            font-size: 13px;
        }
        #forgotPasswordForm input[type="email"] {
            margin-bottom: 16px;
            color: #333;
            background: #fff;
            border: 1px solid #ddd;
        }
        .dropdown-alert {
            display: none;
            position: fixed;
            top: 0;
            left: 50%;
            transform: translate(-50%, -100%);
            min-width: 320px;
            max-width: 90vw;
            z-index: 2000;
            background: #dc3545;
            color: #fff;
            font-size: 17px;
            font-family: 'Poppins', sans-serif;
            padding: 18px 60px 18px 24px;
            text-align: left;
            box-shadow: 0 8px 24px rgba(0,0,0,0.18);
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            transition: transform 0.4s cubic-bezier(.4,2,.6,1), opacity 0.3s;
            opacity: 0;
        }
        .dropdown-alert.active {
            display: block;
            transform: translate(-50%, 0);
            opacity: 1;
        }
        .dropdown-alert .close-alert {
            position: absolute;
            top: 12px;
            right: 24px;
            background: none;
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <img src="../assets/images/logos/logo_nobg.png" alt="Oz University Logo">
        </div>
        <h2>Admin Login</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password:</label>
                <div class="password-container">
                    <input type="password" name="password" id="password" required>
                    <i class="toggle-password bi bi-eye-slash" id="togglePassword"></i>
                </div>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal-overlay" id="forgotModal">
        <div class="modal-box">
            <button class="modal-close" id="closeForgotModal" aria-label="Close"><i class="bi bi-x"></i></button>
            <h3>Forgot Password</h3>
            <form id="forgotPasswordForm" method="post">
                <input type="email" name="email" id="forgotEmail" placeholder="Enter your registered email" required>
                <button type="submit">Send Reset Link</button>
                <div id="forgotPasswordMsg"></div>
                <div style="margin-top: 15px; font-size: 12px; color: #666;">
                    <p>A password reset link will be sent to your email address. If you don't receive it, please check your spam folder.</p>
                </div>
            </form>
        </div>
    </div>

    <!-- Dropdown Error Alert -->
    <div class="dropdown-alert" id="errorDropdown">
        <span id="errorDropdownMsg"></span>
    </div>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this;
            
            // Toggle the password field type
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            }
        });

        // Forgot Password Feature (Modal)
        const forgotLink = document.getElementById('forgotPasswordLink');
        const forgotModal = document.getElementById('forgotModal');
        const closeForgotModal = document.getElementById('closeForgotModal');
        const forgotForm = document.getElementById('forgotPasswordForm');
        const forgotMsg = document.getElementById('forgotPasswordMsg');
        forgotLink.addEventListener('click', function(e) {
            e.preventDefault();
            forgotModal.classList.add('active');
            forgotMsg.textContent = '';
            forgotForm.reset();
        });
        closeForgotModal.addEventListener('click', function() {
            forgotModal.classList.remove('active');
        });
        forgotModal.addEventListener('click', function(e) {
            if (e.target === forgotModal) forgotModal.classList.remove('active');
        });
        forgotForm.addEventListener('submit', function(e) {
            e.preventDefault();
            forgotMsg.textContent = 'Sending...';
            fetch('../forgot_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'email=' + encodeURIComponent(document.getElementById('forgotEmail').value) + '&role=admin'
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error('Network response was not ok: ' + res.status);
                }
                return res.json();
            })
            .then(data => {
                if(data.login_error) {
                    forgotModal.classList.remove('active');
                    showErrorModal(data.message);
                } else if(data.success) {
                    forgotMsg.style.color = 'green';
                    forgotMsg.textContent = data.message;
                } else {
                    forgotMsg.style.color = 'red';
                    forgotMsg.textContent = data.message;
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                forgotMsg.style.color = 'red';
                forgotMsg.textContent = 'An error occurred: ' + error.message;
            });
        });

        // Dropdown Error Alert JS
        const errorDropdown = document.getElementById('errorDropdown');
        const errorDropdownMsg = document.getElementById('errorDropdownMsg');
        function showErrorModal(msg) {
            errorDropdownMsg.textContent = msg;
            errorDropdown.classList.add('active');
            setTimeout(() => errorDropdown.classList.remove('active'), 5000);
        }
    </script>
</body>
</html>