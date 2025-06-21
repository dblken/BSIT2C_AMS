<?php
session_start();
require_once '../config/database.php';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: login.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'];

// Get assigned subjects for the teacher
$subjects_query = "SELECT 
    a.id as assignment_id,
    s.id as subject_id,
    s.subject_code,
    s.subject_name,
    a.preferred_day,
    TIME_FORMAT(a.time_start, '%h:%i %p') as start_time,
    TIME_FORMAT(a.time_end, '%h:%i %p') as end_time,
    a.location,
    (SELECT COUNT(*) FROM enrollments e 
     JOIN timetable tt ON e.schedule_id = tt.id 
     WHERE tt.subject_id = s.id) as student_count
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.teacher_id = ?
    ORDER BY a.preferred_day, a.time_start";
    
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$subjects = $stmt->get_result();

// Get today's attendance counts
$today = date('Y-m-d');
$today_stats = [
    'total' => 0,
    'present' => 0,
    'late' => 0,
    'absent' => 0,
    'subjects_with_attendance' => 0
];

$today_query = "SELECT 
    COUNT(ar.id) as total_records,
    SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
    SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
    COUNT(DISTINCT a.subject_id) as subjects_count
    FROM attendance a
    JOIN attendance_records ar ON a.id = ar.attendance_id
    WHERE a.teacher_id = ? AND a.attendance_date = ?";
    
$stmt = $conn->prepare($today_query);
$stmt->bind_param("is", $teacher_id, $today);
$stmt->execute();
$today_result = $stmt->get_result()->fetch_assoc();

if ($today_result['total_records'] > 0) {
    $today_stats['total'] = $today_result['total_records'];
    $today_stats['present'] = $today_result['present_count'];
    $today_stats['late'] = $today_result['late_count'];
    $today_stats['absent'] = $today_result['absent_count'];
    $today_stats['subjects_with_attendance'] = $today_result['subjects_count'];
}

// Get recent attendance records
$recent_query = "SELECT 
    a.id as attendance_id,
    a.attendance_date,
    s.subject_code,
    s.subject_name,
    COUNT(ar.id) as total_records,
    SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
    SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count
    FROM attendance a
    JOIN attendance_records ar ON a.id = ar.attendance_id
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.teacher_id = ?
    GROUP BY a.id
    ORDER BY a.attendance_date DESC
    LIMIT 5";
    
$stmt = $conn->prepare($recent_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$recent_attendance = $stmt->get_result();

// Helper function to convert day codes to day names
function getDayName($day_code) {
    $days = [
        'M' => 'Monday',
        'T' => 'Tuesday',
        'W' => 'Wednesday',
        'TH' => 'Thursday',
        'F' => 'Friday',
        'SAT' => 'Saturday',
        'SUN' => 'Sunday',
        '1' => 'Monday',
        '2' => 'Tuesday',
        '3' => 'Wednesday',
        '4' => 'Thursday',
        '5' => 'Friday',
        '6' => 'Saturday',
        '7' => 'Sunday'
    ];
    
    return $days[$day_code] ?? $day_code;
}

// Calculate percentage
function calculatePercentage($value, $total) {
    if ($total === 0) return 0;
    return round(($value / $total) * 100);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard - Teacher Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        /* Custom styles for the attendance dashboard */
        :root {
            --primary-color: #1e40af;
            --secondary-color: #f3f4f6;
            --accent-color: #3b82f6;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: var(--text-dark);
        }
        
        .page-title {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.75rem;
        }
        
        .page-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 4px;
            background-color: var(--accent-color);
            border-radius: 2px;
        }
        
        .stats-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            height: 100%;
        }
        
        .stats-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem;
        }
        
        .stat-box {
            padding: 1.5rem;
            text-align: center;
            border-right: 1px solid var(--secondary-color);
        }
        
        .stat-box:last-child {
            border-right: none;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-light);
            font-weight: 500;
        }
        
        .progress {
            height: 8px;
            border-radius: 4px;
        }
        
        .subject-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            overflow: hidden;
        }
        
        .subject-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .subject-card .card-header {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 1rem 1.5rem;
        }
        
        .subject-card .badge {
            font-weight: 500;
            padding: 0.5rem 0.75rem;
            border-radius: 30px;
        }
        
        .btn-attendance {
            border-radius: 5px;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-attendance:hover {
            transform: translateY(-2px);
        }
        
        .btn-history {
            background-color: #f3f4f6;
            color: var(--text-dark);
        }
        
        .btn-history:hover {
            background-color: #e5e7eb;
        }
        
        .recent-attendance {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            margin-top: 2rem;
        }
        
        .recent-attendance .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }
        
        .attendance-item {
            display: flex;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--secondary-color);
            align-items: center;
        }
        
        .attendance-item:last-child {
            border-bottom: none;
        }
        
        .attendance-date {
            width: 140px;
            font-weight: 600;
        }
        
        .attendance-subject {
            flex-grow: 1;
            margin-right: 1rem;
        }
        
        .attendance-stats {
            display: flex;
            align-items: center;
        }
        
        .stat-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .stat-indicator.present {
            background-color: var(--success-color);
        }
        
        .stat-indicator.late {
            background-color: var(--warning-color);
        }
        
        .stat-indicator.absent {
            background-color: var(--danger-color);
        }
        
        .no-classes-msg {
            text-align: center;
            padding: 3rem;
            color: var(--text-light);
        }
        
        .no-classes-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }
        
        @media (max-width: 768px) {
            .stat-box {
                border-right: none;
                border-bottom: 1px solid var(--secondary-color);
            }
            
            .stat-box:last-child {
                border-bottom: none;
            }
            
            .subject-card {
                margin-bottom: 1.5rem;
            }
            
            .attendance-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .attendance-date, .attendance-subject {
                margin-bottom: 0.5rem;
                width: 100%;
            }
            
            .attendance-stats {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <?php include_once 'includes/header.php'; ?>

    <div class="container py-4">
        <h1 class="page-title">Attendance Dashboard</h1>
        
        <!-- Add this clock widget to the top of the page, after the page title -->
        <div class="stats-card mb-4">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">Today: <?= date('l, F d, Y') ?></h5>
                        <p class="mb-0 text-muted">Attendance records are time-sensitive and can only be updated during class hours</p>
                    </div>
                    <div class="text-center">
                        <div id="currentTime" class="fs-2 fw-bold text-primary"></div>
                        <small class="text-muted">Current Time</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Add this after Today's Stats and before Today's Schedule -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-hourglass-split me-2"></i> Pending Attendance Records</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get pending attendance statistics
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
                        
                        if ($pending_stats['total_pending'] > 0):
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="mb-0"><?= $pending_stats['total_pending'] ?></h3>
                            <span class="badge bg-warning text-dark px-3 py-2">Attention Required</span>
                        </div>
                        <p>You have <?= $pending_stats['total_pending'] ?> pending attendance records across <?= $pending_stats['subject_count'] ?> subjects.</p>
                        <p class="mb-0">The oldest record is from <?= $pending_stats['oldest_days'] ?> days ago.</p>
                        <div class="mt-3">
                            <a href="attendance/pending.php" class="btn btn-warning">
                                <i class="bi bi-clipboard-check me-2"></i>Manage Pending Records
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle text-success mb-3" style="font-size: 3rem;"></i>
                            <h5>No Pending Records</h5>
                            <p class="text-muted mb-0">All your attendance records are up-to-date.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i> Attendance Overview</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get attendance statistics for the current month
                        $month_stats_query = "SELECT 
                            COUNT(DISTINCT a.id) as total_records,
                            SUM(a.is_pending) as pending_count,
                            COUNT(DISTINCT a.subject_id) as subjects_with_attendance,
                            SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
                            SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
                            SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                            COUNT(ar.id) as total_students
                            FROM attendance a
                            JOIN attendance_records ar ON a.id = ar.attendance_id
                            WHERE a.teacher_id = ? AND MONTH(a.attendance_date) = MONTH(CURDATE()) AND YEAR(a.attendance_date) = YEAR(CURDATE())";
                        
                        $stmt = $conn->prepare($month_stats_query);
                        $stmt->bind_param("i", $teacher_id);
                        $stmt->execute();
                        $month_stats = $stmt->get_result()->fetch_assoc();
                        
                        if ($month_stats['total_records'] > 0):
                        $attendance_rate = calculatePercentage($month_stats['present_count'] + $month_stats['late_count'], $month_stats['total_students']);
                        ?>
                        <h6 class="mb-3">This Month's Statistics</h6>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span>Attendance Rate</span>
                                <span class="fw-bold"><?= $attendance_rate ?>%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: <?= calculatePercentage($month_stats['present_count'], $month_stats['total_students']) ?>%"></div>
                                <div class="progress-bar bg-warning" style="width: <?= calculatePercentage($month_stats['late_count'], $month_stats['total_students']) ?>%"></div>
                                <div class="progress-bar bg-danger" style="width: <?= calculatePercentage($month_stats['absent_count'], $month_stats['total_students']) ?>%"></div>
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="fw-bold text-success"><?= $month_stats['present_count'] ?></div>
                                <small class="text-muted">Present</small>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold text-warning"><?= $month_stats['late_count'] ?></div>
                                <small class="text-muted">Late</small>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold text-danger"><?= $month_stats['absent_count'] ?></div>
                                <small class="text-muted">Absent</small>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="fw-bold"><?= $month_stats['total_records'] ?></div>
                                <small class="text-muted">Attendance Records</small>
                            </div>
                            <div>
                                <div class="fw-bold"><?= $month_stats['subjects_with_attendance'] ?></div>
                                <small class="text-muted">Subjects</small>
                            </div>
                            <div>
                                <div class="fw-bold"><?= $month_stats['pending_count'] ?></div>
                                <small class="text-muted">Pending</small>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-clipboard mb-3" style="font-size: 3rem; color: #d1d5db;"></i>
                            <h5>No Data Available</h5>
                            <p class="text-muted mb-0">Start taking attendance to see statistics here.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Today's Stats -->
        <div class="stats-card mb-4">
            <div class="stats-header">
                <h5 class="mb-0">Today's Attendance Overview (<?= date('F d, Y') ?>)</h5>
            </div>
            <div class="card-body p-0">
                <?php if ($today_stats['total'] > 0): ?>
                <div class="row m-0">
                    <div class="col-md-3 stat-box">
                        <div class="stat-value"><?= $today_stats['total'] ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="col-md-3 stat-box">
                        <div class="stat-value text-success"><?= $today_stats['present'] ?></div>
                        <div class="stat-label">Present</div>
                    </div>
                    <div class="col-md-3 stat-box">
                        <div class="stat-value text-warning"><?= $today_stats['late'] ?></div>
                        <div class="stat-label">Late</div>
                    </div>
                    <div class="col-md-3 stat-box">
                        <div class="stat-value text-danger"><?= $today_stats['absent'] ?></div>
                        <div class="stat-label">Absent</div>
                    </div>
                </div>
                
                <div class="p-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Attendance Rate</span>
                        <span><?= calculatePercentage($today_stats['present'] + $today_stats['late'], $today_stats['total']) ?>%</span>
                    </div>
                    <div class="progress mb-3">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?= calculatePercentage($today_stats['present'], $today_stats['total']) ?>%" 
                             aria-valuenow="<?= $today_stats['present'] ?>" aria-valuemin="0" aria-valuemax="<?= $today_stats['total'] ?>"></div>
                        <div class="progress-bar bg-warning" role="progressbar" 
                             style="width: <?= calculatePercentage($today_stats['late'], $today_stats['total']) ?>%" 
                             aria-valuenow="<?= $today_stats['late'] ?>" aria-valuemin="0" aria-valuemax="<?= $today_stats['total'] ?>"></div>
                        <div class="progress-bar bg-danger" role="progressbar" 
                             style="width: <?= calculatePercentage($today_stats['absent'], $today_stats['total']) ?>%" 
                             aria-valuenow="<?= $today_stats['absent'] ?>" aria-valuemin="0" aria-valuemax="<?= $today_stats['total'] ?>"></div>
                    </div>
                    <small class="text-muted">
                        You've taken attendance for <?= $today_stats['subjects_with_attendance'] ?> 
                        subject<?= $today_stats['subjects_with_attendance'] !== 1 ? 's' : '' ?> today.
                    </small>
                </div>
                <?php else: ?>
                <div class="no-classes-msg">
                    <i class="bi bi-clipboard-x no-classes-icon"></i>
                    <h5>No Attendance Taken Today</h5>
                    <p class="mb-3">You haven't recorded any attendance for today's classes yet.</p>
                    <a href="attendance/index.php" class="btn btn-primary">Take Attendance Now</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- My Classes -->
        <h4 class="mb-3">My Classes</h4>
        
        <?php if ($subjects->num_rows === 0): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i> You don't have any assigned subjects yet.
        </div>
        <?php else: ?>
        <div class="row">
            <?php while ($subject = $subjects->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="subject-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="m-0"><?= htmlspecialchars($subject['subject_code']) ?></h5>
                        <span class="badge bg-light text-dark">
                            <?= $subject['student_count'] ?> Students
                        </span>
                    </div>
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($subject['subject_name']) ?></h6>
                        <p class="mb-2">
                            <i class="bi bi-calendar3 me-2"></i>
                            <?= getDayName($subject['preferred_day']) ?>
                        </p>
                        <p class="mb-2">
                            <i class="bi bi-clock me-2"></i>
                            <?= $subject['start_time'] ?> - <?= $subject['end_time'] ?>
                        </p>
                        <p class="mb-3">
                            <i class="bi bi-geo-alt me-2"></i>
                            <?= htmlspecialchars($subject['location'] ?: 'TBA') ?>
                        </p>
                        <div class="d-flex justify-content-between">
                            <a href="attendance/take_attendance.php?subject_id=<?= $subject['subject_id'] ?>&assignment_id=<?= $subject['assignment_id'] ?>" class="btn btn-primary btn-attendance">
                                <i class="bi bi-clipboard-check me-2"></i>Take Attendance
                            </a>
                            <a href="attendance/history.php?subject_id=<?= $subject['subject_id'] ?>&assignment_id=<?= $subject['assignment_id'] ?>" class="btn btn-attendance btn-history">
                                <i class="bi bi-clock-history me-2"></i>History
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
        
        <!-- Recent Attendance -->
        <div class="recent-attendance">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="m-0">Recent Attendance Records</h5>
                <a href="attendance/index.php" class="text-white text-decoration-none">
                    <small>View All</small>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if ($recent_attendance->num_rows === 0): ?>
                <div class="p-4 text-center text-muted">
                    <p class="mb-0">No attendance records found</p>
                </div>
                <?php else: ?>
                    <?php while ($record = $recent_attendance->fetch_assoc()): ?>
                    <div class="attendance-item">
                        <div class="attendance-date">
                            <?= date('M d, Y', strtotime($record['attendance_date'])) ?>
                        </div>
                        <div class="attendance-subject">
                            <strong><?= htmlspecialchars($record['subject_code']) ?></strong> - 
                            <?= htmlspecialchars($record['subject_name']) ?>
                        </div>
                        <div class="attendance-stats">
                            <div class="d-flex align-items-center me-3">
                                <div class="stat-indicator present"></div>
                                <small><?= $record['present_count'] ?> Present</small>
                            </div>
                            <div class="d-flex align-items-center me-3">
                                <div class="stat-indicator late"></div>
                                <small><?= $record['late_count'] ?> Late</small>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="stat-indicator absent"></div>
                                <small><?= $record['absent_count'] ?> Absent</small>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
    <script>
        // Current time display
        function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            const timeString = `${hours}:${minutes}:${seconds} ${ampm}`;
            
            document.getElementById('currentTime').textContent = timeString;
        }
        
        // Update clock every second
        updateClock();
        setInterval(updateClock, 1000);
        
        // Auto refresh the page every minute to keep class status updated
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html> 