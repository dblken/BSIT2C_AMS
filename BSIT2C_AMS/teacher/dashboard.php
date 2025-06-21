<?php
session_start();
require_once '../config/database.php';

// Verify teacher role
if (!isset($_SESSION['teacher_id'])) {
    $_SESSION['error'] = "Access denied. Teacher privileges required.";
    header("Location: index.php");
    exit();
}

// Get teacher details
$sql = "SELECT t.*, u.username, u.last_login 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['teacher_id']);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();

// Get teacher's subjects
$sql = "SELECT * FROM subjects WHERE teacher_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['teacher_id']);
$stmt->execute();
$subjects = $stmt->get_result();

// Query for recent attendance records
$recent_attendance_query = "
    SELECT 
        a.id,
        a.subject_id,
        a.attendance_date,
        a.is_pending,
        s.subject_code,
        s.subject_name,
        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.attendance_id = a.id AND ar.status = 'present') as present_count,
        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.attendance_id = a.id AND ar.status = 'late') as late_count,
        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.attendance_id = a.id AND ar.status = 'absent') as absent_count,
        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.attendance_id = a.id) as total_students
    FROM 
        attendance a
    JOIN 
        subjects s ON a.subject_id = s.id
    WHERE 
        a.teacher_id = ?
    ORDER BY 
        a.attendance_date DESC, a.id DESC
    LIMIT 5";

$stmt = $conn->prepare($recent_attendance_query);
$stmt->bind_param("i", $_SESSION['teacher_id']);
$stmt->execute();
$recent_attendance = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        /* Custom styles for teacher dashboard */
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
        
        /* Header styling */
        header.dashboard-header {
            background: linear-gradient(135deg, var(--primary-color), #2563eb);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
        }
        
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .navbar-dark .navbar-nav .nav-link:hover,
        .navbar-dark .navbar-nav .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        /* Main content area */
        .container.main-content {
            padding-top: 2rem;
            padding-bottom: 2rem;
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
        
        /* Dashboard cards */
        .stat-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            height: 100%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .stat-card .icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--secondary-color);
            border-radius: 50%;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-card .icon.subjects {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--accent-color);
        }
        
        .stat-card .icon.students {
            background-color: rgba(5, 150, 105, 0.1);
            color: var(--success-color);
        }
        
        .stat-card .icon.schedule {
            background-color: rgba(217, 119, 6, 0.1);
            color: var(--warning-color);
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        .stat-card .label {
            font-size: 0.875rem;
            color: var(--text-light);
            font-weight: 500;
        }
        
        /* Recent activity section */
        .recent-activity {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            margin-top: 2rem;
        }
        
        .recent-activity .card-header {
            background-color: var(--primary-color);
            color: white;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }
        
        .activity-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--secondary-color);
            display: flex;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .activity-details {
            flex-grow: 1;
        }
        
        .activity-details .title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .activity-details .time {
            font-size: 0.75rem;
            color: var(--text-light);
        }
        
        /* Quick access buttons */
        .quick-access {
            margin-top: 2rem;
        }
        
        .quick-action-btn {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            transition: all 0.3s ease;
            color: var(--text-dark);
            text-decoration: none;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            color: var(--primary-color);
        }
        
        .quick-action-btn i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--accent-color);
        }
        
        .quick-action-btn span {
            font-weight: 600;
        }
        
        /* Footer */
        footer {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem 0;
            margin-top: 3rem;
        }
        
        footer p {
            margin-bottom: 0;
            opacity: 0.9;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 1rem;
            }
            
            .quick-action-btn {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include_once 'includes/header.php'; ?>

    <div class="container main-content">
        <h1 class="page-title mb-4">Teacher Dashboard</h1>
        
        <!-- Dashboard Stats -->
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="stat-card">
                    <div class="icon subjects">
                        <i class="bi bi-book"></i>
                    </div>
                    <div class="number">5</div>
                    <div class="label">Assigned Subjects</div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="stat-card">
                    <div class="icon students">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="number">120</div>
                    <div class="label">Total Students</div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="stat-card">
                    <div class="icon schedule">
                        <i class="bi bi-calendar-week"></i>
                    </div>
                    <div class="number">18</div>
                    <div class="label">Weekly Hours</div>
                </div>
            </div>
        </div>
        
        <!-- Quick Access Buttons -->
        <h4 class="mb-3">Quick Access</h4>
        <div class="row quick-access">
            <div class="col-6 col-md-3 mb-4">
                <a href="subjects/index.php" class="quick-action-btn">
                    <i class="bi bi-book"></i>
                    <span>My Subjects</span>
                </a>
            </div>
            
            <div class="col-6 col-md-3 mb-4">
                <a href="schedule.php" class="quick-action-btn">
                    <i class="bi bi-calendar-check"></i>
                    <span>Schedule</span>
                </a>
            </div>
            
            <div class="col-6 col-md-3 mb-4">
                <a href="attendance.php" class="quick-action-btn">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Attendance</span>
                </a>
            </div>
            
            <div class="col-6 col-md-3 mb-4">
                <a href="profile.php" class="quick-action-btn">
                    <i class="bi bi-person-circle"></i>
                    <span>Profile</span>
                </a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="recent-activity">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Activity</h5>
                <a href="#" class="text-white text-decoration-none">
                    <small>View All</small>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if ($recent_attendance->num_rows > 0): ?>
                    <?php while ($record = $recent_attendance->fetch_assoc()): ?>
                    <div class="activity-item">
                        <div class="activity-icon bg-primary text-white">
                            <i class="bi bi-book"></i>
                        </div>
                        <div class="activity-details">
                            <div class="activity-title"><?= htmlspecialchars($record['subject_code']) ?> - <?= htmlspecialchars($record['subject_name']) ?></div>
                            <div class="activity-stats">
                                <div class="stat">
                                    <div class="stat-dot present"></div>
                                    <span><?= $record['present_count'] ?> Present</span>
                                </div>
                                <div class="stat">
                                    <div class="stat-dot late"></div>
                                    <span><?= $record['late_count'] ?> Late</span>
                                </div>
                                <div class="stat">
                                    <div class="stat-dot absent"></div>
                                    <span><?= $record['absent_count'] ?> Absent</span>
                                </div>
                                <div class="ms-auto d-none d-md-block">
                                    <span class="text-muted"><?= $record['total_students'] ?> students</span>
                                </div>
                            </div>
                        </div>
                        <a href="attendance/history.php?subject_id=<?= $record['subject_id'] ?? '' ?>&attendance_id=<?= $record['id'] ?>" class="btn btn-sm btn-outline-secondary ms-auto">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                    <?php endwhile; ?>
                <?php endif; ?>
                <div class="text-center p-3">
                    <a href="attendance/index.php" class="btn btn-primary">
                        <i class="bi bi-clipboard-check me-2"></i>Manage Attendance
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <footer>
        <div class="container">
            <p class="text-center">© 2023 School Management System. All rights reserved.</p>
        </div>
    </footer>

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
        
        // Auto refresh the page every 5 minutes to keep class status updated
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>