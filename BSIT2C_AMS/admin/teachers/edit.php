<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $query = "SELECT * FROM teachers WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    $teacher = mysqli_fetch_assoc($result);
    echo json_encode(['success' => true, 'data' => $teacher]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);

    $query = "UPDATE teachers SET 
              first_name = '$first_name',
              last_name = '$last_name',
              email = '$email',
              phone = '$phone',
              department = '$department'
              WHERE id = '$id'";

    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    }
}
?> 