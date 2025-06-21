<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);

    // Check if email exists for other teachers
    $check_query = "SELECT teacher_id FROM teachers WHERE email = '$email' AND teacher_id != '$teacher_id'";
    $check_result = mysqli_query($conn, $check_query);
    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }

    $query = "UPDATE teachers SET 
              first_name = '$first_name',
              last_name = '$last_name',
              email = '$email',
              phone_number = '$phone_number',
              department = '$department'
              WHERE teacher_id = '$teacher_id'";

    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true, 'message' => 'Teacher updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating teacher: ' . mysqli_error($conn)]);
    }
}
?> 