<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    
    // First check if teacher has any assigned subjects
    $check_query = "SELECT id FROM teacher_subjects WHERE teacher_id = '$id' AND status = 'Active'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete teacher with active subject assignments']);
        exit;
    }

    $query = "UPDATE teachers SET status = 'Inactive' WHERE id = '$id'";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    }
}
?> 