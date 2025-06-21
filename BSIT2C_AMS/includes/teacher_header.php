<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['type'] !== 'teacher') {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teacher Dashboard - BSIT 2C</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
</head>
<body>
    <div class="sidebar">
        <div class="logo">Teacher Panel</div>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="my_subjects.php">My Subjects</a>
            <a href="mark_attendance.php">Mark Attendance</a>
            <a href="view_attendance.php">View Attendance</a>
            <a href="my_timetable.php">My Timetable</a>
            <a href="../logout.php">Logout</a>
        </nav>
    </div>
</body>
</html> 