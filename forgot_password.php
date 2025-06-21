<?php
// Ensure no errors are displayed in the output
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON content type header
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';

// Start output buffering to catch any unwanted output
ob_start();

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$role = isset($_POST['role']) ? trim($_POST['role']) : '';
if (!$email || !$role) {
    // Clean any buffered output
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Please enter your email.']);
    exit;
}

// Function to generate a secure random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Function to send password reset email
function sendResetEmail($email, $token, $role) {
    // Get the base URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    
    // Make sure script_dir ends with a slash if it's not the root
    if ($script_dir !== '/' && !empty($script_dir)) {
        $script_dir = rtrim($script_dir, '/') . '/';
    }
    
    // Build the reset link
    $reset_link = $protocol . $host . $script_dir . "reset_password.php?token=" . $token . "&email=" . urlencode($email) . "&role=" . $role;
    
    // HTML version of the message
    $html_message = "
    <html>
    <head>
        <title>Password Reset</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #007bff; color: white; padding: 15px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; border: 1px solid #ddd; }
            .button { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; 
                     text-decoration: none; border-radius: 4px; margin: 15px 0; }
            .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Password Reset Request</h2>
            </div>
            <div class='content'>
                <p>Hello,</p>
                <p>You have requested to reset your password. Please click the button below to reset your password:</p>
                <p style='text-align: center;'>
                    <a href=\"$reset_link\" class='button'>Reset Password</a>
                </p>
                <p>Or copy and paste this URL into your browser:</p>
                <p style='background-color: #eee; padding: 10px; word-break: break-all;'>$reset_link</p>
                <p>This link will expire in 24 hours.</p>
                <p>If you did not request this password reset, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>Regards,<br>Attendance Management System</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Plain text version
    $text_message = "Hello,\n\n" .
                   "You have requested to reset your password. Please click the link below to reset your password:\n\n" .
                   "$reset_link\n\n" .
                   "This link will expire in 24 hours.\n\n" .
                   "If you did not request this password reset, please ignore this email.\n\n" .
                   "Regards,\nAttendance Management System";
    
    // Try to send email using PHPMailer
    try {
        // Check if PHPMailer is installed
        if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
            // Fallback to session storage if PHPMailer is not installed
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['test_reset_link'] = $reset_link;
            error_log("PHPMailer not installed. Test reset link stored in session: $reset_link");
            return true;
        }
        
        // Include the Mailer class
        require_once __DIR__ . '/includes/Mailer.php';
        
        // Create mailer instance
        $mailer = new Mailer();
        
        // Send the email
        $sent = $mailer->send(
            $email,
            '',
            'Password Reset Request',
            $html_message,
            $text_message
        );
        
        if (!$sent) {
            error_log("Failed to send email: " . $mailer->getError());
            
            // Fallback to session storage
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['test_reset_link'] = $reset_link;
            error_log("Email sending failed. Test reset link stored in session: $reset_link");
        } else {
            error_log("Password reset email sent successfully to $email");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error sending email: " . $e->getMessage());
        
        // Fallback to session storage
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['test_reset_link'] = $reset_link;
        error_log("Email sending failed with exception. Test reset link stored in session: $reset_link");
        
        return true;
    }
}

try {
    if ($role === 'student') {
        // Check in students
        $stmt = $conn->prepare('SELECT id FROM students WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_id = $user['id'];
            
            // Generate token
            $token = generateToken();
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Check if password_resets table exists, create if not
            $check_table = $conn->query("SHOW TABLES LIKE 'password_resets'");
            if ($check_table->num_rows == 0) {
                $conn->query("CREATE TABLE password_resets (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    email VARCHAR(100) NOT NULL,
                    token VARCHAR(100) NOT NULL,
                    role VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL
                )");
            }
            
            // Delete any existing tokens for this email
            $delete_stmt = $conn->prepare('DELETE FROM password_resets WHERE email = ?');
            $delete_stmt->bind_param('s', $email);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Store token in database
            $insert_stmt = $conn->prepare('INSERT INTO password_resets (email, token, role, expires_at) VALUES (?, ?, ?, ?)');
            $insert_stmt->bind_param('ssss', $email, $token, $role, $expiry);
            $insert_stmt->execute();
            $insert_stmt->close();
            
            // Send email
            if (sendResetEmail($email, $token, $role)) {
                // Clean any buffered output
                ob_end_clean();
                echo json_encode(['success' => true, 'message' => 'A password reset link has been sent to your email address. Please check your inbox (and spam folder).']);
            } else {
                // Clean any buffered output
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to send password reset email. Please try again later.']);
            }
            exit;
        }
        $stmt->close();
        
        // Check if email exists in teachers
        $stmt = $conn->prepare('SELECT id FROM teachers WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            // Clean any buffered output
            ob_end_clean();
            echo json_encode(['success' => false, 'login_error' => true, 'message' => 'Login error: This email is registered as a teacher. Please use the correct portal.']);
            exit;
        }
        $stmt->close();
    } elseif ($role === 'teacher') {
        // Check in teachers
        $stmt = $conn->prepare('SELECT id FROM teachers WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_id = $user['id'];
            
            // Generate token
            $token = generateToken();
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Check if password_resets table exists, create if not
            $check_table = $conn->query("SHOW TABLES LIKE 'password_resets'");
            if ($check_table->num_rows == 0) {
                $conn->query("CREATE TABLE password_resets (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    email VARCHAR(100) NOT NULL,
                    token VARCHAR(100) NOT NULL,
                    role VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL
                )");
            }
            
            // Delete any existing tokens for this email
            $delete_stmt = $conn->prepare('DELETE FROM password_resets WHERE email = ?');
            $delete_stmt->bind_param('s', $email);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Store token in database
            $insert_stmt = $conn->prepare('INSERT INTO password_resets (email, token, role, expires_at) VALUES (?, ?, ?, ?)');
            $insert_stmt->bind_param('ssss', $email, $token, $role, $expiry);
            $insert_stmt->execute();
            $insert_stmt->close();
            
            // Send email
            if (sendResetEmail($email, $token, $role)) {
                // Clean any buffered output
                ob_end_clean();
                echo json_encode(['success' => true, 'message' => 'A password reset link has been sent to your email address. Please check your inbox (and spam folder).']);
            } else {
                // Clean any buffered output
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to send password reset email. Please try again later.']);
            }
            exit;
        }
        $stmt->close();
        
        // Check if email exists in students
        $stmt = $conn->prepare('SELECT id FROM students WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            // Clean any buffered output
            ob_end_clean();
            echo json_encode(['success' => false, 'login_error' => true, 'message' => 'Login error: This email is registered as a student. Please use the correct portal.']);
            exit;
        }
        $stmt->close();
    } elseif ($role === 'admin') {
        // Check in admins
        $stmt = $conn->prepare('SELECT id FROM admins WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_id = $user['id'];
            
            // Generate token
            $token = generateToken();
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Check if password_resets table exists, create if not
            $check_table = $conn->query("SHOW TABLES LIKE 'password_resets'");
            if ($check_table->num_rows == 0) {
                $conn->query("CREATE TABLE password_resets (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    email VARCHAR(100) NOT NULL,
                    token VARCHAR(100) NOT NULL,
                    role VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL
                )");
            }
            
            // Delete any existing tokens for this email
            $delete_stmt = $conn->prepare('DELETE FROM password_resets WHERE email = ?');
            $delete_stmt->bind_param('s', $email);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Store token in database
            $insert_stmt = $conn->prepare('INSERT INTO password_resets (email, token, role, expires_at) VALUES (?, ?, ?, ?)');
            $insert_stmt->bind_param('ssss', $email, $token, $role, $expiry);
            $insert_stmt->execute();
            $insert_stmt->close();
            
            // Send email
            if (sendResetEmail($email, $token, $role)) {
                // Clean any buffered output
                ob_end_clean();
                echo json_encode(['success' => true, 'message' => 'A password reset link has been sent to your email address. Please check your inbox (and spam folder).']);
            } else {
                // Clean any buffered output
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to send password reset email. Please try again later.']);
            }
            exit;
        }
        $stmt->close();
    } else {
        // Clean any buffered output
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid role.']);
        exit;
    }

    // Not found
    // Clean any buffered output
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Email address not found. Please check and try again.']);
} catch (Exception $e) {
    // Clean any buffered output
    ob_end_clean();
    error_log("Error in forgot_password.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
} 