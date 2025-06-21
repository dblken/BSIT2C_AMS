<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session_protection.php';

// Use the verify_session function to check student session
verify_session('student');

// Get student details
$sql = "SELECT s.*, u.username, u.last_login 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Get student's subjects with schedule information
$sql = "SELECT s.subject_code, s.subject_name, 
        a.preferred_day, a.time_start, a.time_end, a.location,
        CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
        t.id as teacher_id, s.id as subject_id
        FROM enrollments e
        JOIN assignments a ON e.assignment_id = a.id
        JOIN subjects s ON a.subject_id = s.id
        JOIN teachers t ON a.teacher_id = t.id
        WHERE e.student_id = ?
        ORDER BY FIELD(a.preferred_day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), a.time_start";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$schedules = $stmt->get_result();

// Organize classes by day
$timetable = [
    'Monday' => [],
    'Tuesday' => [],
    'Wednesday' => [],
    'Thursday' => [],
    'Friday' => [],
    'Saturday' => []
];

// Set day colors
$day_colors = [
    'Monday' => 'monday',
    'Tuesday' => 'tuesday',
    'Wednesday' => 'wednesday',
    'Thursday' => 'thursday',
    'Friday' => 'friday',
    'Saturday' => 'saturday'
];

// Process subjects and organize by day
while ($schedule = $schedules->fetch_assoc()) {
    // Handle different formats of preferred_day
    $day = $schedule['preferred_day'];
    
    // Try to decode as JSON first
    $json_days = json_decode($day, true);
    if (is_array($json_days)) {
        foreach ($json_days as $single_day) {
            // Add to each day in the JSON array
            if (isset($timetable[$single_day])) {
                $timetable[$single_day][] = $schedule;
            }
        }
    } 
    // If not JSON or not parsed successfully, handle as regular string
    else {
        // Check for standard day names
        if (isset($timetable[$day])) {
            $timetable[$day][] = $schedule;
        } 
        // Try to match numeric or abbreviations
        else {
            // Map of possible day representations to standard names
            $day_mapping = [
                '1' => 'Monday', 'M' => 'Monday', 'MON' => 'Monday', 'MONDAY' => 'Monday',
                '2' => 'Tuesday', 'T' => 'Tuesday', 'TU' => 'Tuesday', 'TUE' => 'Tuesday', 'TUESDAY' => 'Tuesday',
                '3' => 'Wednesday', 'W' => 'Wednesday', 'WED' => 'Wednesday', 'WEDNESDAY' => 'Wednesday',
                '4' => 'Thursday', 'TH' => 'Thursday', 'THU' => 'Thursday', 'THURSDAY' => 'Thursday',
                '5' => 'Friday', 'F' => 'Friday', 'FRI' => 'Friday', 'FRIDAY' => 'Friday',
                '6' => 'Saturday', 'S' => 'Saturday', 'SAT' => 'Saturday', 'SATURDAY' => 'Saturday'
            ];
            
            $day_key = strtoupper($day);
            if (isset($day_mapping[$day_key])) {
                $timetable[$day_mapping[$day_key]][] = $schedule;
            }
        }
    }
}

// Get current day
$today = date('l');

// Function to format time
function formatTime($time) {
    return date('h:i A', strtotime($time));
}

// Function to calculate class duration in minutes
function calculateDuration($start, $end) {
    $start_time = strtotime($start);
    $end_time = strtotime($end);
    return round(($end_time - $start_time) / 60);
}

// Function to determine if a class is ongoing
function isClassOngoing($start, $end) {
    $current_time = time();
    $start_time = strtotime($start);
    $end_time = strtotime($end);
    return ($current_time >= $start_time && $current_time <= $end_time);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Timetable - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #021F3F;
            --secondary-color: #C8A77E;
            --primary-dark: #011327;
            --secondary-light: #d8b78e;
            --text-dark: #1f2937;
            --text-light: #ffffff;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--text-dark);
        }
        
        .timetable-wrapper {
            padding: 20px;
        }
        
        .timetable-day {
            margin-bottom: 30px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }
        
        .day-header {
            padding: 15px;
            color: white;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .day-body {
            background-color: white;
            padding: 0;
        }
        
        .class-item {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .class-item:last-child {
            border-bottom: none;
        }
        
        .class-item:hover {
            background-color: #f9fafb;
        }
        
        .class-time {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .class-duration {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .class-subject {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .class-details {
            font-size: 0.9rem;
            color: #4b5563;
        }
        
        .class-room {
            background-color: #e5e7eb;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #374151;
        }
        
        .ongoing-class {
            border-left: 4px solid var(--primary-color);
            background-color: rgba(2, 31, 63, 0.05);
        }
        
        .empty-day {
            padding: 20px;
            text-align: center;
            color: #6b7280;
        }
        
        .today-label {
            background-color: var(--primary-color);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .timetable-legend {
            margin-bottom: 20px;
        }
        
        .legend-item {
            display: inline-flex;
            align-items: center;
            margin-right: 15px;
            font-size: 0.9rem;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .week-navigator {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .current-time {
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.2rem;
            color: var(--primary-color);
        }
        
        /* Custom color modifications to use primary color */
        .bg-primary {
            background-color: var(--primary-color) !important;
        }
        
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        /* Override the day color classes to use primary color theme */
        .bg-primary, .bg-success, .bg-info, .bg-warning, .bg-danger, .bg-secondary {
            background-color: var(--primary-color) !important;
        }
        
        /* Add color variations for each day */
        .bg-monday { background-color: var(--primary-color); }
        .bg-tuesday { background-color: #0c2c54; }
        .bg-wednesday { background-color: #163a69; }
        .bg-thursday { background-color: #21477e; }
        .bg-friday { background-color: #2c5493; }
        .bg-saturday { background-color: #3761a8; }
        
        .page-title {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.8rem;
        }
        
        /* Update card styling */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .timetable-day {
                margin-bottom: 20px;
            }
            
            .class-item {
                padding: 12px;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container main-content my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">My Weekly Timetable</h1>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="card mb-4">
            <div class="card-body">
                <div class="current-time">
                    <div id="current-date-time" class="fw-bold"></div>
                </div>
                
                <div class="timetable-legend">
                    <div class="d-flex flex-wrap justify-content-center">
                        <?php foreach ($day_colors as $day => $color): ?>
                        <div class="legend-item mx-2">
                            <span class="legend-color bg-<?= strtolower($day) ?>"></span>
                            <span><?= $day ?></span>
                            <?php if ($day == $today): ?>
                            <span class="badge bg-primary ms-1">Today</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="week-navigator">
                    <div></div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" id="printTimetable">
                            <i class="bi bi-printer"></i> Print Timetable
                        </button>
                    </div>
                    <div></div>
                </div>
                
                <div class="timetable-wrapper">
                    <?php foreach ($timetable as $day => $classes): ?>
                    <div class="timetable-day">
                        <div class="day-header bg-<?= strtolower($day) ?>">
                            <span><?= $day ?></span>
                            <?php if ($day == $today): ?>
                            <span class="today-label">Today</span>
                            <?php endif; ?>
                        </div>
                        <div class="day-body">
                            <?php if (empty($classes)): ?>
                            <div class="empty-day">
                                <i class="bi bi-calendar-x" style="font-size: 1.5rem;"></i>
                                <p class="mb-0 mt-2">No classes scheduled</p>
                            </div>
                            <?php else: ?>
                                <?php foreach ($classes as $class): ?>
                                <div class="class-item <?= (($day == $today) && isClassOngoing($class['time_start'], $class['time_end'])) ? 'ongoing-class' : '' ?>">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="class-time">
                                                <?= formatTime($class['time_start']) ?> - <?= formatTime($class['time_end']) ?>
                                            </div>
                                            <div class="class-duration">
                                                <?= calculateDuration($class['time_start'], $class['time_end']) ?> minutes
                                            </div>
                                        </div>
                                        <div class="col-md-7">
                                            <div class="class-subject">
                                                <?= htmlspecialchars($class['subject_code']) ?> - <?= htmlspecialchars($class['subject_name']) ?>
                                            </div>
                                            <div class="class-details">
                                                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($class['teacher_name']) ?>
                                            </div>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <span class="class-room">
                                                <i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($class['location']) ?>
                                            </span>
                                            <?php if (($day == $today) && isClassOngoing($class['time_start'], $class['time_end'])): ?>
                                            <div class="mt-2">
                                                <span class="badge bg-primary">Ongoing</span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update current date and time
        function updateTimetableClock() {
            const now = new Date();
            
            // Format date
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric'
            };
            const dateString = now.toLocaleDateString('en-US', options);
            
            // Update display - only showing date, not time
            document.getElementById('current-date-time').textContent = `${dateString}`;
        }
        
        // Initial call and then update every second
        updateTimetableClock();
        // Update once per minute instead of every second since we're only showing the date
        setInterval(updateTimetableClock, 60000);
        
        // Print timetable
        document.getElementById('printTimetable').addEventListener('click', function() {
            window.print();
        });
    </script>
</body>
</html> 