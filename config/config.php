<?php
define('BASE_PATH', dirname(dirname(__FILE__)));
define('DATABASE_PATH', BASE_PATH . '/config/database.php');

// Base URL configuration
define('BASE_URL', '/BSIT2C_AMS'); // Update this to match your project folder name

// Function to get the full URL for a path
function url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}
?> 