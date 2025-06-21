<?php
// SMTP Mail Configuration

// SMTP Server Settings
$mail_config = [
    'host' => 'smtp.gmail.com',    // Change to your SMTP server
    'username' => 'your-email@gmail.com', // Change to your email
    'password' => 'your-app-password',    // Change to your app password or email password
    'port' => 587,                  // Common ports: 25, 465, 587
    'encryption' => 'tls',          // Options: 'ssl', 'tls', or ''
    
    // Sender Information
    'from_email' => 'your-email@gmail.com', // Change to your email
    'from_name' => 'Attendance Management System',
    
    // Debug Level (0 = off, 1 = client messages, 2 = client and server messages)
    'debug' => 0  // Changed to 0 for production
];

// Instructions for Gmail:
// 1. Enable 2-Step Verification on your Google account
// 2. Generate an App Password: Google Account > Security > App passwords
// 3. Use that App Password instead of your regular Gmail password
// 4. Update the settings above with your Gmail address and App Password

// Instructions for other email providers:
// 1. Contact your email provider for SMTP server details
// 2. Update the host, port, and encryption settings accordingly
// 3. Use your email address and password for authentication
?>