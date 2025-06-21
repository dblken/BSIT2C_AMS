<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set cache-control headers to prevent back button access after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Function to verify different user roles (admin, teacher, student)
function verify_session($role) {
    // Check if the session for the specified role exists
    $valid_session = false;
    
    switch ($role) {
        case 'admin':
            $valid_session = isset($_SESSION['admin_id']);
            $redirect_url = '../index.php';
            break;
        case 'teacher':
            $valid_session = isset($_SESSION['teacher_id']);
            $redirect_url = '../index.php';
            break;
        case 'student':
            $valid_session = isset($_SESSION['student_id']);
            $redirect_url = '../index.php';
            break;
        default:
            $redirect_url = '../index.php';
            break;
    }
    
    // If session is not valid (user not logged in), redirect to login page
    if (!$valid_session) {
        header("Location: $redirect_url");
        exit();
    }
    
    // If session is valid but hasn't been regenerated recently, regenerate it
    // This helps prevent session fixation attacks
    if (!isset($_SESSION['last_regeneration']) || 
        (time() - $_SESSION['last_regeneration']) > 1800) { // Regenerate every 30 minutes
        // Regenerate session ID
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    return true;
}
?> 