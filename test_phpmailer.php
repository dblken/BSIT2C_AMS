<?php
// Test script to check if PHPMailer is installed correctly

// Display errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHPMailer Installation Test</h1>";

// Check if vendor directory exists
if (!is_dir(__DIR__ . '/vendor')) {
    echo "<p style='color:red'>❌ Vendor directory not found. Please run 'composer install'.</p>";
} else {
    echo "<p style='color:green'>✓ Vendor directory found.</p>";
}

// Check if autoload.php exists
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "<p style='color:red'>❌ Autoload file not found. Please run 'composer install'.</p>";
} else {
    echo "<p style='color:green'>✓ Autoload file found.</p>";
    
    // Try to include autoload
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        echo "<p style='color:green'>✓ Autoload file included successfully.</p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Error including autoload file: " . $e->getMessage() . "</p>";
    }
}

// Check if PHPMailer classes exist
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<p style='color:green'>✓ PHPMailer class found.</p>";
    
    // Create PHPMailer instance
    try {
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        echo "<p style='color:green'>✓ PHPMailer instance created successfully.</p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Error creating PHPMailer instance: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>❌ PHPMailer class not found. Please check your installation.</p>";
}

// Check if Mailer class exists
if (file_exists(__DIR__ . '/includes/Mailer.php')) {
    echo "<p style='color:green'>✓ Mailer wrapper class found.</p>";
    
    // Try to include Mailer class
    try {
        require_once __DIR__ . '/includes/Mailer.php';
        echo "<p style='color:green'>✓ Mailer class included successfully.</p>";
        
        // Check if mail configuration exists
        if (file_exists(__DIR__ . '/config/mail.php')) {
            echo "<p style='color:green'>✓ Mail configuration file found.</p>";
            
            // Try to create Mailer instance
            try {
                $mailer = new Mailer();
                echo "<p style='color:green'>✓ Mailer wrapper instance created successfully.</p>";
                
                // Check if mail configuration is set
                require_once __DIR__ . '/config/mail.php';
                if (isset($mail_config) && is_array($mail_config)) {
                    echo "<p style='color:green'>✓ Mail configuration loaded successfully.</p>";
                    
                    // Check required configuration values
                    $required_keys = ['host', 'username', 'password', 'port', 'from_email'];
                    $missing_keys = [];
                    
                    foreach ($required_keys as $key) {
                        if (!isset($mail_config[$key]) || empty($mail_config[$key]) || $mail_config[$key] === 'your-email@gmail.com') {
                            $missing_keys[] = $key;
                        }
                    }
                    
                    if (empty($missing_keys)) {
                        echo "<p style='color:green'>✓ All required configuration values are set.</p>";
                    } else {
                        echo "<p style='color:orange'>⚠️ The following configuration values need to be updated: " . implode(', ', $missing_keys) . "</p>";
                    }
                } else {
                    echo "<p style='color:red'>❌ Mail configuration not found in config file.</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color:red'>❌ Error creating Mailer wrapper instance: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color:red'>❌ Mail configuration file not found.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Error including Mailer class: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>❌ Mailer wrapper class not found.</p>";
}

// Summary
echo "<h2>Summary</h2>";
echo "<p>To make the forgot password functionality work:</p>";
echo "<ol>";
echo "<li>Make sure PHPMailer is installed correctly</li>";
echo "<li>Update the mail configuration in config/mail.php with your email provider's information</li>";
echo "<li>Test the forgot password feature on the login pages</li>";
echo "</ol>";

echo "<p>For more detailed instructions, please refer to the <a href='README_EMAIL_SETUP.md'>README_EMAIL_SETUP.md</a> file.</p>";
?> 