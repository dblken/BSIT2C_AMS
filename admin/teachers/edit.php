<?php
require_once dirname(__FILE__) . '/../../config/database.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['id'])) {
        error_log("No ID provided in GET request");
        echo json_encode(['success' => false, 'message' => 'No ID provided']);
        exit;
    }
    
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Log the query for debugging
    error_log("Fetching teacher with ID: $id");
    
    $query = "SELECT * FROM teachers WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        error_log("MySQL Error: " . mysqli_error($conn));
        echo json_encode(['success' => false, 'message' => 'Database query error: ' . mysqli_error($conn)]);
        exit;
    }
    
    if ($result && mysqli_num_rows($result) > 0) {
        $teacher = mysqli_fetch_assoc($result);
        // Format the birthday date for the form
        if (isset($teacher['birthday']) && $teacher['birthday']) {
            error_log("Original birthday value: " . $teacher['birthday']);
            $teacher['birthday'] = date('Y-m-d', strtotime($teacher['birthday']));
            error_log("Formatted birthday value: " . $teacher['birthday']);
        } else {
            error_log("Birthday is not set or is null");
        }
        // Log the retrieved data
        error_log("Teacher data retrieved: " . json_encode($teacher));
        
        echo json_encode(['success' => true, 'teacher' => $teacher]);
    } else {
        error_log("No teacher found with ID: $id");
        echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize all POST data
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $teacher_id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $middle_name = isset($_POST['middle_name']) ? mysqli_real_escape_string($conn, $_POST['middle_name']) : null;
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $birthday = mysqli_real_escape_string($conn, $_POST['birthday']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = isset($_POST['phone']) ? mysqli_real_escape_string($conn, $_POST['phone']) : null;
    $department = mysqli_real_escape_string($conn, $_POST['department']);

    // Construct the update query
    $query = "UPDATE teachers SET 
              teacher_id = '$teacher_id',
              first_name = '$first_name',
              middle_name = " . ($middle_name ? "'$middle_name'" : "NULL") . ",
              last_name = '$last_name',
              gender = '$gender',
              birthday = " . ($birthday ? "'$birthday'" : "NULL") . ",
              email = '$email',
              phone = " . ($phone ? "'$phone'" : "NULL") . ",
              department = '$department'
              WHERE id = '$id'";

    // Log the query for debugging
    error_log("Update query: " . $query);

    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true, 'message' => 'Teacher updated successfully']);
    } else {
        error_log("Error updating teacher: " . mysqli_error($conn));
        echo json_encode(['success' => false, 'message' => 'Error updating teacher: ' . mysqli_error($conn)]);
    }
    exit;
}

// If the request method is neither GET nor POST
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
exit;
?> 