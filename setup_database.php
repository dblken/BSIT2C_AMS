<?php
/**
 * Database Setup Script
 * Creates the attendance_system_1 database
 */

$host = 'localhost';
$username = 'root';
$password = '1234';

// Connect to MySQL without specifying a database
$conn = mysqli_connect($host, $username, $password);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create the database
$sql = "CREATE DATABASE IF NOT EXISTS attendance_system_1;";

if (mysqli_query($conn, $sql)) {
    echo "✓ Database 'attendance_system_1' created/verified successfully!<br>";
    
    // Now connect to the database
    $conn = mysqli_connect($host, $username, $password, 'attendance_system_1');
    
    if ($conn) {
        echo "✓ Successfully connected to 'attendance_system_1'<br>";
        echo "✓ Setup complete! Your application should now work.<br>";
        echo "<a href='student_login.php'>Go to Login</a>";
    } else {
        echo "✗ Error connecting to database: " . mysqli_connect_error();
    }
} else {
    echo "✗ Error creating database: " . mysqli_error($conn);
}

mysqli_close($conn);
?>
