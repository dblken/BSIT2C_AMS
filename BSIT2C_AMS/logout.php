<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Get the base URL dynamically
$base_url = '/BSIT2C_AMS'; // Update this to match your project folder name

// Redirect to login page
header("Location: " . $base_url . "/index.php");
exit(); // This should be the last line of code
?> 