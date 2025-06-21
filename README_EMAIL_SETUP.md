# Email Setup for Password Recovery

This document provides instructions on how to set up the email functionality for the password recovery feature in the Attendance Management System.

## Prerequisites

1. PHPMailer library (already installed in the vendor directory)
2. SMTP server credentials (e.g., Gmail, Outlook, etc.)

## Configuration Steps

### 1. Update SMTP Settings

Edit the file `config/mail.php` and update the following settings with your email provider's information:

```php
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
    'debug' => 0  // Set to 1 or 2 for troubleshooting
];
```

### 2. Gmail-Specific Setup

If you're using Gmail:

1. Enable 2-Step Verification on your Google account
   - Go to your Google Account > Security > 2-Step Verification
   - Follow the steps to turn it on

2. Generate an App Password
   - Go to your Google Account > Security > App passwords
   - Select "Mail" as the app and "Other" as the device (name it "Attendance System")
   - Click "Generate"
   - Use the 16-character password that appears in the `password` field of the mail configuration

### 3. Testing the Email Functionality

After configuring your email settings:

1. Try the "Forgot Password" feature on any login page
2. Enter a valid email address that exists in the system
3. Check your email inbox (and spam folder) for the password reset link
4. Click the link to reset your password

### 4. Troubleshooting

If emails are not being sent:

1. Set the debug level to 2 in `config/mail.php` to see detailed error messages
2. Check your server's error logs
3. Verify that your SMTP credentials are correct
4. Make sure your hosting provider allows outgoing SMTP connections
5. Try a different port (587, 465, or 25) if one doesn't work
6. Check if your email provider has additional security settings that need to be configured

### 5. Security Considerations

- Keep your email credentials secure and never commit them to version control
- Consider using environment variables for sensitive information
- Regularly update your app passwords
- Monitor for any unauthorized use of your email account

## Additional Resources

- [PHPMailer Documentation](https://github.com/PHPMailer/PHPMailer)
- [Gmail App Passwords](https://support.google.com/accounts/answer/185833)
- [Outlook SMTP Settings](https://support.microsoft.com/en-us/office/pop-imap-and-smtp-settings-8361e398-8af4-4e97-b147-6c6c4ac95353) 