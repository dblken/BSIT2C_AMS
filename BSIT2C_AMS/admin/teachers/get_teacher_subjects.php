<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if (isset($_GET['teacher_id'])) {
    $teacher_id = mysqli_real_escape_string($conn, $_GET['teacher_id']);
    
    $query = "SELECT s.subject_code, s.subject_name 
              FROM subjects s 
              INNER JOIN teacher_subjects ts ON s.id = ts.subject_id 
              WHERE ts.teacher_id = '$teacher_id' 
              AND ts.status = 'Active'";
    
    $result = mysqli_query($conn, $query);
    $subjects = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $subjects[] = $row;
    }
    
    echo json_encode(['success' => true, 'subjects' => $subjects]);
} else {
    echo json_encode(['success' => false, 'message' => 'Teacher ID not provided']);
}
?> 