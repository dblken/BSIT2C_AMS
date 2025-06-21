<?php
$host = 'localhost';
$username = 'root';
$password = '1234';
$database = 'attendance_system';

try {
    $conn = new mysqli($host, $username, $password, $database);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}
?> 