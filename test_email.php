<?php
// Test script to send a test email using the Mailer class

// Display errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session
session_start();

// Include the Mailer class
require_once __DIR__ . '/includes/Mailer.php';

// Function to sanitize form inputs
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_email = sanitize_input($_POST['to_email']);
    $subject = sanitize_input($_POST['subject']);
    $message = $_POST['message'];
    
    // Create mailer instance
    $mailer = new Mailer();
    
    // Send the email
    $sent = $mailer->send(
        $to_email,
        '',
        $subject,
        $message,
        strip_tags($message)
    );
    
    if ($sent) {
        $_SESSION['success'] = "Email sent successfully!";
    } else {
        $_SESSION['error'] = "Failed to send email: " . $mailer->getError();
        
        // Store the link in session for testing
        if (strpos($message, 'href="') !== false) {
            preg_match('/href="([^"]+)"/', $message, $matches);
            if (isset($matches[1])) {
                $_SESSION['test_reset_link'] = $matches[1];
                $_SESSION['link_notice'] = "Email sending failed, but the reset link has been stored in session. <a href='test_reset_link.php'>Click here</a> to view it.";
            }
        }
    }
    
    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Get messages from session
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$link_notice = isset($_SESSION['link_notice']) ? $_SESSION['link_notice'] : '';

// Clear session messages
unset($_SESSION['success']);
unset($_SESSION['error']);
unset($_SESSION['link_notice']);

// Get mail configuration
require_once __DIR__ . '/config/mail.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email Sending</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            padding: 30px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        h1 {
            margin-bottom: 30px;
            color: #007bff;
        }
        .config-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        .config-info pre {
            margin-bottom: 0;
        }
        .alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Email Sending</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($link_notice): ?>
            <div class="alert alert-info"><?php echo $link_notice; ?></div>
        <?php endif; ?>
        
        <div class="config-info">
            <h4>Current Mail Configuration:</h4>
            <pre><?php
                // Display mail configuration (hide password)
                $safe_config = $mail_config;
                if (isset($safe_config['password'])) {
                    $safe_config['password'] = '********';
                }
                echo htmlspecialchars(print_r($safe_config, true));
            ?></pre>
            <p class="mt-3 mb-0">
                <a href="README_EMAIL_SETUP.md" class="btn btn-sm btn-info">View Email Setup Instructions</a>
                <a href="test_phpmailer.php" class="btn btn-sm btn-secondary">Check PHPMailer Installation</a>
            </p>
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Send Test Email</h3>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="form-group">
                        <label for="to_email">To Email:</label>
                        <input type="email" class="form-control" id="to_email" name="to_email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject:</label>
                        <input type="text" class="form-control" id="subject" name="subject" value="Test Email from Attendance Management System" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message:</label>
                        <textarea class="form-control" id="message" name="message" rows="10" required>
<html>
<head>
    <title>Test Email</title>
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
            <h2>Test Email</h2>
        </div>
        <div class='content'>
            <p>Hello,</p>
            <p>This is a test email from the Attendance Management System.</p>
            <p style='text-align: center;'>
                <a href="http://localhost/test_reset_link.php" class='button'>Test Button</a>
            </p>
            <p>If you received this email, your email configuration is working correctly.</p>
        </div>
        <div class='footer'>
            <p>Regards,<br>Attendance Management System</p>
        </div>
    </div>
</body>
</html>
                        </textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Send Test Email</button>
                </form>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="index.php" class="btn btn-secondary">Back to Home</a>
        </div>
    </div>
</body>
</html> 