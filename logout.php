<?php
session_start();

// Determine which login page to redirect to before destroying the session
$role = '';
if (isset($_SESSION['admin_id'])) {
    $role = 'admin';
} elseif (isset($_SESSION['teacher_id'])) {
    $role = 'teacher';
} elseif (isset($_SESSION['student_id'])) {
    $role = 'student';
}

// Set cache-control headers to prevent back button access
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

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

// Determine which login page to redirect to based on role
if ($role === 'teacher') {
    header("Location: " . $base_url . "/teacher/login.php");
} elseif ($role === 'admin') {
    header("Location: " . $base_url . "/admin/");
} elseif ($role === 'student') {
    header("Location: " . $base_url . "/student/login.php");
} else {
    // Fallback to main login page
    header("Location: " . $base_url . "/index.php");
}
exit(); // This should be the last line of code
?>