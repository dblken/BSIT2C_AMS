<?php
// Ensure no errors are displayed in the output
error_reporting(0);
ini_set('display_errors', 0);

session_start();

// Check if there's a test reset link in the session
if (isset($_SESSION['test_reset_link'])) {
    $reset_link = $_SESSION['test_reset_link'];
    
    // Keep the link in session for a while in case user needs to access it again
    // It will be cleared after 5 minutes
    $_SESSION['reset_link_generated_time'] = time();
} else {
    $reset_link = '';
    
    // Check if we have a stored link that's less than 5 minutes old
    if (isset($_SESSION['stored_reset_link']) && isset($_SESSION['reset_link_generated_time'])) {
        if (time() - $_SESSION['reset_link_generated_time'] < 300) { // 5 minutes
            $reset_link = $_SESSION['stored_reset_link'];
        } else {
            // Clear old links
            unset($_SESSION['stored_reset_link']);
            unset($_SESSION['reset_link_generated_time']);
        }
    }
}

// Store the link for future use
if (!empty($reset_link)) {
    $_SESSION['stored_reset_link'] = $reset_link;
}

// Auto-refresh the page if no link is found
$auto_refresh = empty($reset_link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Link</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <?php if ($auto_refresh): ?>
    <meta http-equiv="refresh" content="3">
    <?php endif; ?>
    <style>
        body {
            background-color: #f8f9fa;
            padding: 50px 0;
            font-family: Arial, sans-serif;
        }
        .container {
            max-width: 800px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #007bff;
            color: white;
            text-align: center;
            border-radius: 10px 10px 0 0 !important;
            padding: 20px;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            font-size: 18px;
            padding: 10px 20px;
        }
        .btn-primary:hover {
            background-color: #0069d9;
            border-color: #0062cc;
        }
        .reset-link {
            word-break: break-all;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
            margin: 15px 0;
        }
        .big-button {
            display: block;
            width: 100%;
            padding: 20px;
            font-size: 20px;
            margin: 20px 0;
        }
        .refresh-notice {
            text-align: center;
            font-style: italic;
            color: #666;
        }
        .countdown {
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>Password Reset Link</h2>
            </div>
            <div class="card-body">
                <?php if ($reset_link): ?>
                    <div class="alert alert-success" role="alert">
                        <h4 class="alert-heading">Reset Link Available!</h4>
                        <p>Click the button below to reset your password:</p>
                    </div>
                    
                    <a href="<?php echo htmlspecialchars($reset_link); ?>" class="btn btn-primary big-button">RESET MY PASSWORD</a>
                    
                    <p class="mt-4">Or copy this URL if the button doesn't work:</p>
                    <div class="reset-link"><?php echo htmlspecialchars($reset_link); ?></div>
                <?php else: ?>
                    <div class="alert alert-info" role="alert">
                        <h4 class="alert-heading">Waiting for Reset Link...</h4>
                        <p>No password reset link has been generated yet.</p>
                    </div>
                    <p>This page will automatically refresh every 3 seconds until a reset link is found.</p>
                    <div class="refresh-notice">
                        <p>To get a password reset link:</p>
                        <ol>
                            <li>Go to the login page</li>
                            <li>Click on "Forgot Password?"</li>
                            <li>Enter your email address</li>
                            <li>Submit the form</li>
                            <li>This page will automatically detect your reset link</li>
                        </ol>
                    </div>
                    <div class="text-center">
                        <p>Refreshing in <span id="countdown" class="countdown">3</span> seconds...</p>
                    </div>
                    <script>
                        // Countdown script
                        let seconds = 3;
                        const countdownElement = document.getElementById('countdown');
                        
                        function updateCountdown() {
                            countdownElement.textContent = seconds;
                            if (seconds > 0) {
                                seconds--;
                                setTimeout(updateCountdown, 1000);
                            }
                        }
                        
                        updateCountdown();
                    </script>
                <?php endif; ?>
                <div class="text-center mt-4">
                    <a href="index.php" class="btn btn-secondary">Back to Home</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 