<?php
session_start();
require_once '../config/database.php';

// Send JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Check if admin_id is provided
if (!isset($_POST['admin_id']) || $_POST['admin_id'] != $_SESSION['admin_id']) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Get current profile image
$sql = "SELECT profile_image FROM admins WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// Delete the image file if exists
if (!empty($admin['profile_image'])) {
    $image_path = '../' . $admin['profile_image'];
    // If we're already in the admin directory, adjust the path
    if(strpos($_SERVER['PHP_SELF'], '/admin/') !== false && strpos($_SERVER['PHP_SELF'], '/admin/remove_profile_image.php') !== false) {
        $image_path = $admin['profile_image'];
    }
    
    if(file_exists($image_path)) {
        if (!unlink($image_path)) {
            echo json_encode(['success' => false, 'message' => 'Could not delete the image file']);
            exit();
        }
    }
}

// Set profile_image to NULL in database
$update_sql = "UPDATE admins SET profile_image = NULL WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("i", $admin_id);

if ($update_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Profile image removed successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
} 