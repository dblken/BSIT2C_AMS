<?php
/**
 * PHPMailer Installation Script
 * 
 * This script installs PHPMailer using Composer.
 * Run this script once to set up email functionality.
 */

// Check if Composer is installed
$composerInstalled = false;
exec('composer --version', $output, $returnVar);
if ($returnVar === 0) {
    $composerInstalled = true;
    echo "✓ Composer is installed.\n";
} else {
    echo "✗ Composer is not installed. Please install Composer first.\n";
    echo "Visit https://getcomposer.org/download/ for installation instructions.\n";
    exit(1);
}

// Create composer.json if it doesn't exist
if (!file_exists('composer.json')) {
    $composerJson = [
        'require' => [
            'phpmailer/phpmailer' => '^6.8'
        ]
    ];
    
    file_put_contents('composer.json', json_encode($composerJson, JSON_PRETTY_PRINT));
    echo "✓ Created composer.json file.\n";
} else {
    echo "✓ composer.json already exists.\n";
    
    // Update composer.json to include PHPMailer if it's not already included
    $composerJson = json_decode(file_get_contents('composer.json'), true);
    if (!isset($composerJson['require']['phpmailer/phpmailer'])) {
        $composerJson['require']['phpmailer/phpmailer'] = '^6.8';
        file_put_contents('composer.json', json_encode($composerJson, JSON_PRETTY_PRINT));
        echo "✓ Updated composer.json to include PHPMailer.\n";
    }
}

// Run composer install
echo "Installing PHPMailer...\n";
exec('composer install', $output, $returnVar);

if ($returnVar === 0) {
    echo "✓ PHPMailer installed successfully!\n";
    
    // Check if mail.php exists and prompt user to update it
    if (file_exists('config/mail.php')) {
        echo "\n";
        echo "IMPORTANT: Update your SMTP settings in config/mail.php\n";
        echo "You need to set your email provider's SMTP server, username, and password.\n";
    }
    
    echo "\nEmail functionality is now ready to use!\n";
} else {
    echo "✗ Failed to install PHPMailer. Please run 'composer install' manually.\n";
    exit(1);
}
?> 