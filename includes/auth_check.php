<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['teacher_id'])) {
    // Not logged in, redirect to login page
    header('Location: login.php');
    exit;
}

// If session exists but we want to add additional checks later (like checking if the user still exists in the database),
// we can add those checks here.
?> 