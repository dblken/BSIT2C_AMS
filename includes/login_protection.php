<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set cache-control headers to prevent caching of login pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Check if user is already logged in, redirect to appropriate dashboard
function redirect_if_logged_in() {
    // Get the current script path
    $current_path = $_SERVER['PHP_SELF'];
    
    if (isset($_SESSION['admin_id'])) {
        // Check if we're in the admin directory
        if (strpos($current_path, '/admin/') !== false) {
            header("Location: dashboard.php");
        } else {
            header("Location: admin/dashboard.php");
        }
        exit();
    } elseif (isset($_SESSION['teacher_id'])) {
        header("Location: ../teacher/dashboard.php");
        exit();
    } elseif (isset($_SESSION['student_id'])) {
        header("Location: ../student/dashboard.php");
        exit();
    }
}
?> 