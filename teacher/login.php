<?php
session_start();
require_once '../config/database.php';
require_once '../includes/login_protection.php'; // Include login protection

// Check if already logged in
redirect_if_logged_in();

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Validate credentials - Modified to check status as well
    $stmt = $conn->prepare("SELECT t.id as teacher_id, t.first_name, t.last_name, t.status, u.id as user_id, u.password, u.role
                           FROM teachers t 
                           JOIN users u ON t.user_id = u.id 
                           WHERE u.username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $teacher = $result->fetch_assoc();
        
        // First check if teacher is active
        if ($teacher['status'] !== 'Active') {
            if ($teacher['status'] === 'Inactive') {
                $_SESSION['error'] = 'Your account is currently inactive. Please contact the administrator.';
            } else if ($teacher['status'] === 'On Leave') {
                $_SESSION['error'] = 'Your account is currently on leave. Please contact the administrator.';
            } else {
                $_SESSION['error'] = 'Your account is not active. Please contact the administrator.';
            }
            header('Location: login.php');
            exit;
        }
        
        // Then check if the password matches (either plain text or hashed)
        if ($password === $teacher['password'] || password_verify($password, $teacher['password']) || verify_password_md5_with_salt($password, $teacher['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $teacher['user_id'];
            $_SESSION['teacher_id'] = $teacher['teacher_id'];
            $_SESSION['role'] = 'teacher'; // Always set role to teacher for this login page
            $_SESSION['name'] = $teacher['first_name'] . ' ' . $teacher['last_name'];
            header('Location: dashboard.php');
            exit;
        }
    }

    // Invalid credentials
    $_SESSION['error'] = 'Invalid username or password';
    header('Location: login.php');
    exit;
}

// Function to verify passwords hashed with MD5 and salt (format: salt:md5hash)
function verify_password_md5_with_salt($password, $stored_hash) {
    // Check if the stored hash is in the format of "salt:hash"
    if (strpos($stored_hash, ':') !== false) {
        list($salt, $hash) = explode(':', $stored_hash, 2);
        // Verify by recreating the hash with the given salt
        return md5($salt . $password) === $hash;
    }
    return false;
}

$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teacher Login - BSIT 2C AMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { 
            font-family: 'Poppins', sans-serif;
            margin: 0; 
            padding: 0; 
            background: linear-gradient(135deg, #021F3F 0%, #021F3F 60%, #d4b794 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            display: flex;
            width: 100%;
            max-width: 400px;
            margin: 0;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.10), 0 0 8px 1px rgba(255,255,255,0.5);
            overflow: hidden;
            flex-direction: column;
        }
        .form-header {
            background: #021F3F;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            padding: 24px 0 12px 0;
            text-align: center;
        }
        .form-header img {
            max-width: 150px;
            height: auto;
        }
        .login-section {
            flex: 1;
            padding: 24px 40px 40px 40px;
            transform: translateY(0);
            transition: transform 0.3s ease;
        }
        h2 {
            color: #021F3F;
            text-align: center;
            margin-bottom: 35px;
            font-size: 24px;
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
            color: #021F3F;
            font-weight: 500;
            font-size: 14px;
            letter-spacing: 0.3px;
        }
        input[type="text"], input[type="password"], input[type="email"] { 
            width: 100%; 
            padding: 14px; 
            border: 2px solid #e8e8e8; 
            border-radius: 8px; 
            box-sizing: border-box; 
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: #ffffff;
            color: #021F3F;
        }
        input[type="text"]::placeholder, input[type="password"]::placeholder, input[type="email"]::placeholder {
            color: #666;
        }
        input[type="text"]:focus, input[type="password"]:focus, input[type="email"]:focus {
            border-color: #C8A77E;
            outline: none;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(200, 167, 126, 0.1);
            color: #021F3F;
        }
        button { 
            width: 100%; 
            padding: 16px; 
            background: #021F3F; 
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
            background: #d4b794; 
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        button:active {
            transform: translateY(1px);
        }
        .error { 
            color: #dc3545; 
            margin-bottom: 20px; 
            text-align: center;
            padding: 12px;
            background: #fff5f5;
            border-radius: 8px;
            font-size: 14px;
            border: 1px solid #ffebeb;
        }
        .links { 
            text-align: center; 
            margin-top: 30px; 
        }
        .links a { 
            color: #021F3F; 
            text-decoration: none; 
            margin: 0 15px; 
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            position: relative;
        }
        .links a:hover { 
            color: #C8A77E; 
        }
        .links a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -4px;
            left: 0;
            background-color: #C8A77E;
            transition: width 0.3s ease;
        }
        .links a:hover::after {
            width: 100%;
        }
        /* Password visibility toggle styles */
        .password-container {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #aaa;
            transition: color 0.3s ease;
        }
        .toggle-password:hover {
            color: #C8A77E;
        }
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            .welcome-section {
                padding: 30px;
            }
            .stats-preview {
                margin-top: 15px;
            }
        }
        /* Modal styles for Forgot Password */
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
        }
        /* Dropdown Error Alert Styles */
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
    <div class="container">
        <div class="form-header">
            <img src="../assets/images/logos/logo_nobg.png" alt="Logo">
        </div>
        <div class="login-section">
            <h2>Teacher Login</h2>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form action="login.php" method="post">
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
            <div id="forgot-password-section" style="text-align:center; margin-top:10px;">
                <a href="#" id="forgotPasswordLink" style="color:#C8A77E; font-size:14px; text-decoration:underline; cursor:pointer;">Forgot Password?</a>
            </div>
            <div class="links">
                <a href="../student/login.php">Student Login</a>
            </div>
        </div>
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
                body: 'email=' + encodeURIComponent(document.getElementById('forgotEmail').value) + '&role=teacher'
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