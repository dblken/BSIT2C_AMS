<?php
require_once 'config/database.php';

// Get a valid teacher_id from the database
$teacher_query = "SELECT id FROM teachers LIMIT 1";
$teacher_result = $conn->query($teacher_query);
$teacher_id = $teacher_result->fetch_assoc()['id'];

echo "Testing queries for teacher ID: $teacher_id\n\n";

// Pending count query from teacher/attendance.php
$pending_query = "
    SELECT COUNT(*) as pending_count  
    FROM attendance a
    WHERE a.teacher_id = ? AND a.is_pending = 1";
$pending_stmt = $conn->prepare($pending_query);
$pending_stmt->bind_param("i", $teacher_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$pending_count = $pending_result->fetch_assoc()['pending_count'] ?? 0;

echo "1. Pending count query result: $pending_count pending records\n";

// Pending stats query from teacher/attendance.php
$pending_stats_query = "SELECT 
    COUNT(DISTINCT a.id) as total_pending,
    COUNT(DISTINCT a.subject_id) as subject_count,
    MAX(DATEDIFF(CURDATE(), a.attendance_date)) as oldest_days
    FROM attendance a
    WHERE a.teacher_id = ? AND a.is_pending = 1";

$stmt = $conn->prepare($pending_stats_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$pending_stats = $stmt->get_result()->fetch_assoc();

echo "2. Pending stats query results:\n";
echo "   - Total pending: " . $pending_stats['total_pending'] . "\n";
echo "   - Subject count: " . $pending_stats['subject_count'] . "\n";
echo "   - Oldest days: " . $pending_stats['oldest_days'] . "\n\n";

// Month stats query from teacher/attendance.php
$month_stats_query = "SELECT 
    COUNT(DISTINCT a.id) as total_records,
    SUM(a.is_pending) as pending_count,
    COUNT(DISTINCT a.subject_id) as subjects_with_attendance,
    SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
    SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
    COUNT(ar.id) as total_students
    FROM attendance a
    LEFT JOIN attendance_records ar ON a.id = ar.attendance_id
    WHERE a.teacher_id = ? AND MONTH(a.attendance_date) = MONTH(CURDATE()) AND YEAR(a.attendance_date) = YEAR(CURDATE())";

$stmt = $conn->prepare($month_stats_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$month_stats = $stmt->get_result()->fetch_assoc();

echo "3. Month stats query results:\n";
echo "   - Total records: " . $month_stats['total_records'] . "\n";
echo "   - Pending count: " . $month_stats['pending_count'] . "\n";
echo "   - Subjects with attendance: " . $month_stats['subjects_with_attendance'] . "\n";
echo "   - Present count: " . $month_stats['present_count'] . "\n";
echo "   - Late count: " . $month_stats['late_count'] . "\n";
echo "   - Absent count: " . $month_stats['absent_count'] . "\n";
echo "   - Total students: " . $month_stats['total_students'] . "\n\n";

// Metrics query from teacher/attendance.php
$metrics_query = "
    SELECT
    COUNT(DISTINCT a.id) as total_attendance,
    SUM(CASE WHEN DATE(a.attendance_date) = CURDATE() THEN 1 ELSE 0 END) as today_count,
    SUM(CASE WHEN DATE(a.attendance_date) BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE() THEN 1 ELSE 0 END) as week_count,
    SUM(CASE WHEN DATE(a.attendance_date) BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE() THEN 1 ELSE 0 END) as month_count,
    SUM(a.is_pending) as pending_count
    FROM attendance a
    WHERE a.teacher_id = ?";
$metrics_stmt = $conn->prepare($metrics_query);
$metrics_stmt->bind_param("i", $teacher_id);
$metrics_stmt->execute();
$metrics = $metrics_stmt->get_result()->fetch_assoc();

echo "4. Metrics query results:\n";
echo "   - Total attendance: " . $metrics['total_attendance'] . "\n";
echo "   - Today count: " . $metrics['today_count'] . "\n";
echo "   - Week count: " . $metrics['week_count'] . "\n";
echo "   - Month count: " . $metrics['month_count'] . "\n";
echo "   - Pending count: " . $metrics['pending_count'] . "\n";

?> 