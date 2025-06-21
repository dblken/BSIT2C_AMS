<?php
require_once dirname(__FILE__) . '/../../config/database.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Get the first teacher ID from the database for testing
$query = "SELECT id FROM teachers LIMIT 1";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $teacherId = $row['id'];
    
    // Now retrieve the full teacher data
    $query = "SELECT * FROM teachers WHERE id = " . intval($teacherId);
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $teacher = mysqli_fetch_assoc($result);
        echo "<h1>Test Teacher Data Retrieval</h1>";
        echo "<pre>";
        print_r($teacher);
        echo "</pre>";
        
        echo "<h2>Field Values That Would Be Used:</h2>";
        echo "<ul>";
        echo "<li>ID: " . htmlspecialchars($teacher['id']) . "</li>";
        echo "<li>Teacher ID: " . htmlspecialchars($teacher['teacher_id']) . "</li>";
        echo "<li>First Name: " . htmlspecialchars($teacher['first_name']) . "</li>";
        echo "<li>Middle Name: " . htmlspecialchars($teacher['middle_name'] ?? 'NULL') . "</li>";
        echo "<li>Last Name: " . htmlspecialchars($teacher['last_name']) . "</li>";
        echo "<li>Gender: " . htmlspecialchars($teacher['gender'] ?? 'NULL') . "</li>";
        echo "<li>Email: " . htmlspecialchars($teacher['email']) . "</li>";
        echo "<li>Phone: " . htmlspecialchars($teacher['phone'] ?? 'NULL') . "</li>";
        echo "<li>Department: " . htmlspecialchars($teacher['department'] ?? 'NULL') . "</li>";
        echo "</ul>";
    } else {
        echo "Error retrieving teacher: " . mysqli_error($conn);
    }
} else {
    echo "No teachers found in the database: " . mysqli_error($conn);
}
?> 