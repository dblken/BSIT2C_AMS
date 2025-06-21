<?php
/**
 * Mailer Class
 * A wrapper for PHPMailer to send emails using SMTP
 */

class Mailer {
    private $mailer;
    private $config;
    private $error = '';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Include mail configuration
        require_once __DIR__ . '/../config/mail.php';
        $this->config = $mail_config;
        
        // Check if PHPMailer exists
        if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
            $this->error = 'PHPMailer not found. Please install it first.';
            error_log($this->error);
            return;
        }
        
        // Load PHPMailer
        require_once __DIR__ . '/../vendor/autoload.php';
        
        // Create PHPMailer instance
        $this->mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $this->mailer->SMTPDebug = $this->config['debug'];
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['username'];
            $this->mailer->Password = $this->config['password'];
            
            if (!empty($this->config['encryption'])) {
                $this->mailer->SMTPSecure = $this->config['encryption'];
            }
            
            $this->mailer->Port = $this->config['port'];
            
            // Default sender
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            
            // Character set
            $this->mailer->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            $this->error = 'Mailer initialization failed: ' . $e->getMessage();
            error_log($this->error);
        }
    }
    
    /**
     * Send an email
     * 
     * @param string $to_email Recipient email
     * @param string $to_name Recipient name
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string $alt_body Plain text alternative body
     * @return bool True if email was sent, false otherwise
     */
    public function send($to_email, $to_name = '', $subject = '', $body = '', $alt_body = '') {
        // If mailer initialization failed, return false
        if (!empty($this->error)) {
            return false;
        }
        
        try {
            // Add recipient
            $this->mailer->addAddress($to_email, $to_name);
            
            // Set email content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            
            // Set alternative body if provided
            if (!empty($alt_body)) {
                $this->mailer->AltBody = $alt_body;
            } else {
                $this->mailer->AltBody = strip_tags($body);
            }
            
            // Send the email
            $this->mailer->send();
            
            // Clear recipients for next send
            $this->mailer->clearAddresses();
            
            return true;
        } catch (Exception $e) {
            $this->error = 'Message could not be sent. Mailer Error: ' . $this->mailer->ErrorInfo;
            error_log($this->error);
            return false;
        }
    }
    
    /**
     * Get the last error message
     * 
     * @return string Error message
     */
    public function getError() {
        return $this->error;
    }
}
?> 