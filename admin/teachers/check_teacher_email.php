<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

// Check if email is provided
if (isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    // Basic email format validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['exists' => false, 'error' => 'Invalid email format']);
        exit;
    }
    
    // Additional validation for domain part
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        echo json_encode(['exists' => false, 'error' => 'Invalid email format']);
        exit;
    }
    
    $domain = $parts[1];
    
    // Check if domain has at least one dot and the TLD is at least 2 characters
    $domainParts = explode('.', $domain);
    $tld = end($domainParts);
    
    // Validate domain structure (must have at least a domain name and TLD)
    if (count($domainParts) < 2 || empty($domainParts[0])) {
        echo json_encode(['exists' => false, 'error' => 'Invalid domain name format']);
        exit;
    }
    
    // Check if domain name follows valid format (alphanumeric with hyphens, not starting or ending with hyphen)
    foreach ($domainParts as $part) {
        if (empty($part) || !preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/i', $part)) {
            echo json_encode(['exists' => false, 'error' => 'Invalid domain name format. Domain parts must contain only letters, numbers, and hyphens, and cannot start or end with a hyphen.']);
            exit;
        }
    }
    
    // List of common generic TLDs
    $validTlds = ['com', 'net', 'org', 'edu', 'gov', 'mil', 'int', 'info', 'biz', 'name', 'pro', 'museum', 'coop', 'aero', 'xxx', 'idv', 'ac', 'edu'];
    
    // Check for multiple domain extensions (e.g., .com.com.com)
    $domainDotsCount = substr_count($domain, '.');
    if ($domainDotsCount > 2 || count($domainParts) > 3) {
        echo json_encode(['exists' => false, 'error' => 'Invalid email domain. Multiple extensions are not allowed.']);
        exit;
    }
    
    if (count($domainParts) < 2 || strlen($tld) < 2 || !in_array($tld, $validTlds)) {
        echo json_encode(['exists' => false, 'error' => 'Invalid email domain. Only common domains like .com, .net, .org, etc. are accepted']);
        exit;
    }
    
    // Check if email exists in the database
    $stmt = $conn->prepare("SELECT id FROM teachers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Return response
    echo json_encode(['exists' => ($result->num_rows > 0)]);
    
} else {
    echo json_encode(['exists' => false, 'error' => 'Email not provided']);
}

$conn->close();
?> 