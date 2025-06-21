<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    exit('Unauthorized');
}

$stats = [
    'students' => $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'],
    'teachers' => $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'],
    'subjects' => $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count']
];

echo json_encode($stats);
?> 