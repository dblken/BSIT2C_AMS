<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session_protection.php'; // Include the session protection file

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

// Check if student is active
if ($student['status'] !== 'Active') {
    // Destroy the session
    session_unset();
    session_destroy();
    
    // Redirect with error message
    session_start();
    $_SESSION['error'] = "Your account is inactive. Please contact the administrator.";
    header("Location: ../index.php");
    exit();
}

// Get student's subjects with attendance statistics
$sql = "SELECT s.id, s.subject_code, s.subject_name, 
        a.preferred_day, a.time_start, a.time_end, a.location,
        CONCAT(t.first_name, ' ', t.last_name) as teacher_name
        FROM enrollments e
        JOIN timetable tt ON e.schedule_id = tt.id
        JOIN subjects s ON tt.subject_id = s.id
        JOIN assignments a ON s.id = a.subject_id
        JOIN teachers t ON a.teacher_id = t.id
        WHERE e.student_id = ?
        ORDER BY a.preferred_day, a.time_start";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$subjects = $stmt->get_result();

// Get overall attendance statistics
$attendance_stats = [
    'total' => 0,
    'present' => 0,
    'late' => 0,
    'absent' => 0
];

$subject_attendance = [];

// Get all enrolled subject IDs for the student
$subjects_data = [];
while ($subject = $subjects->fetch_assoc()) {
    $subjects_data[] = $subject;
    
    // Get attendance stats for this subject
    $attendance_query = "
        SELECT 
            COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_count,
            COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
            COUNT(ar.id) as total_records
        FROM 
            attendance a
        JOIN 
            attendance_records ar ON a.id = ar.attendance_id
        WHERE 
            a.subject_id = ?
            AND ar.student_id = ?";
    
    $stmt = $conn->prepare($attendance_query);
    $stmt->bind_param("ii", $subject['id'], $_SESSION['student_id']);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Add to overall stats
    $attendance_stats['total'] += $stats['total_records'];
    $attendance_stats['present'] += $stats['present_count'];
    $attendance_stats['late'] += $stats['late_count'];
    $attendance_stats['absent'] += $stats['absent_count'];
    
    // Store subject-specific stats
    $subject_attendance[$subject['id']] = [
        'subject_code' => $subject['subject_code'],
        'subject_name' => $subject['subject_name'],
        'present' => $stats['present_count'],
        'late' => $stats['late_count'],
        'absent' => $stats['absent_count'],
        'total' => $stats['total_records']
    ];
}

// Calculate attendance percentages
$total_classes = $attendance_stats['total'] > 0 ? $attendance_stats['total'] : 1; // Avoid division by zero
$present_percent = round(($attendance_stats['present'] / $total_classes) * 100);
$late_percent = round(($attendance_stats['late'] / $total_classes) * 100);
$absent_percent = round(($attendance_stats['absent'] / $total_classes) * 100);

// Get recent attendance records
$recent_attendance_query = "
    SELECT 
        a.attendance_date,
        s.subject_code,
        s.subject_name,
        ar.status,
        ar.remarks
    FROM 
        attendance a
    JOIN 
        attendance_records ar ON a.id = ar.attendance_id
    JOIN 
        subjects s ON a.subject_id = s.id
    WHERE 
        ar.student_id = ?
    ORDER BY 
        a.attendance_date DESC,
        a.id DESC
    LIMIT 5";

$stmt = $conn->prepare($recent_attendance_query);
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$recent_attendance = $stmt->get_result();

// Status styles for attendance
$status_styles = [
    'present' => ['color' => 'success', 'icon' => 'check-circle-fill', 'text' => 'Present'],
    'late' => ['color' => 'warning', 'icon' => 'clock-fill', 'text' => 'Late'],
    'absent' => ['color' => 'danger', 'icon' => 'x-circle-fill', 'text' => 'Absent']
];

// Get current day
// 1 = Monday, 2 = Tuesday, etc. for compatibility with our array indexes
$today_day = date('N');
$day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

$today_name = $day_names[$today_day - 1];

// Initialize day codes with more possible variations, especially for Thursday
$day_codes = [
    1 => ['M', 'MON', 'MONDAY', 'LUNES'],
    2 => ['T', 'TU', 'TUE', 'TUES', 'TUESDAY', 'MARTES'],
    3 => ['W', 'WED', 'WEDS', 'WEDNESDAY', 'MIYERKULES'],
    4 => ['TH', 'THU', 'THUR', 'THURS', 'THURSDAY', 'HUWEBES', 'R'],
    5 => ['F', 'FRI', 'FRIDAY', 'BIYERNES'],
    6 => ['S', 'SAT', 'SATURDAY', 'SABADO'],
    7 => ['SU', 'SUN', 'SUNDAY', 'LINGGO']
];

// Classes by day organization
$classes_by_day = [
    1 => [], // Monday
    2 => [], // Tuesday
    3 => [], // Wednesday
    4 => [], // Thursday
    5 => [], // Friday
    6 => [], // Saturday
    7 => []  // Sunday
];

// Process subjects and organize by day
foreach ($subjects_data as $subject) {
    $preferred_day = trim(strtoupper($subject['preferred_day']));
    $assigned = false;
    
    // Direct numeric day match (1=Monday, 2=Tuesday, etc.)
    if (preg_match('/^[1-7]$/', $preferred_day)) {
        $day_number = (int)$preferred_day;
        $classes_by_day[$day_number][] = $subject;
        $assigned = true;
    }
    
    // If not assigned by direct numeric match
    if (!$assigned) {
        // Check for day matches using regular expressions
        foreach ($day_codes as $day_number => $codes) {
            foreach ($codes as $code) {
                // Match either exact code or code with numbers (like TH34)
                if ($preferred_day === $code || preg_match('/^' . preg_quote($code) . '\d+/', $preferred_day)) {
                    $classes_by_day[$day_number][] = $subject;
                    $assigned = true;
                    break 2; // Break out of both loops
                }
            }
        }
    }
    
    // If still not assigned, try to parse each character
    if (!$assigned && strlen($preferred_day) > 0) {
        // Examples: MWF, MTH, TTHS, etc.
        for ($i = 0; $i < strlen($preferred_day); $i++) {
            $char = $preferred_day[$i];
            // Special case for TH (2 characters)
            if ($char === 'T' && $i + 1 < strlen($preferred_day) && $preferred_day[$i + 1] === 'H') {
                $classes_by_day[4][] = $subject; // Thursday
                $i++; // Skip the next character
            } else {
                // Single character matches
                switch ($char) {
                    case 'M': $classes_by_day[1][] = $subject; break; // Monday
                    case 'T': $classes_by_day[2][] = $subject; break; // Tuesday
                    case 'W': $classes_by_day[3][] = $subject; break; // Wednesday
                    case 'R': $classes_by_day[4][] = $subject; break; // Thursday (common academic notation)
                    case 'F': $classes_by_day[5][] = $subject; break; // Friday
                    case 'S': $classes_by_day[6][] = $subject; break; // Saturday
                    case 'U': $classes_by_day[7][] = $subject; break; // Sunday
                    // Include numeric representations
                    case '1': $classes_by_day[1][] = $subject; break; // Monday
                    case '2': $classes_by_day[2][] = $subject; break; // Tuesday
                    case '3': $classes_by_day[3][] = $subject; break; // Wednesday
                    case '4': $classes_by_day[4][] = $subject; break; // Thursday
                    case '5': $classes_by_day[5][] = $subject; break; // Friday
                    case '6': $classes_by_day[6][] = $subject; break; // Saturday
                    case '7': $classes_by_day[7][] = $subject; break; // Sunday
                }
            }
        }
    }
}

// Debug: Show how classes were grouped
// Remove this from here and move to the bottom

// Today's code both as number and day code
$today_code = $day_codes[$today_day];

// Day code to day name mapping
$day_name_map = [
    'M' => 'Monday',
    'T' => 'Tuesday',
    'W' => 'Wednesday',
    'TH' => 'Thursday',
    'F' => 'Friday',
    'SAT' => 'Saturday',
    'SUN' => 'Sunday'
];

// Function to determine if a class is scheduled today
function isClassToday($preferred_day, $day_codes, $today_day) {
    // If preferred_day is in JSON format, decode it
    $days = @json_decode($preferred_day, true);
    if (is_array($days)) {
        foreach ($days as $day) {
            $day_name = getDayName($day);
            if (strtolower($day_name) === strtolower($today_day)) {
                return true;
            }
        }
        return false;
    }
    
    // Check if preferred_day directly matches one of today's codes
    foreach ($day_codes[$today_day] as $code) {
        if (strcasecmp($preferred_day, $code) === 0) {
            return true;
        }
    }
    
    // Check numeric values (in case the database stores day as a number)
    if ($preferred_day == $today_day) {
        return true;
    }
    
    // Check for day name abbreviations that might not exactly match our codes
    $day_abbrevs = [
        'MON' => 'M',
        'TUE' => 'T',
        'WED' => 'W',
        'THU' => 'TH',
        'THUR' => 'TH', // Alternative abbreviation
        'FRI' => 'F',
        'SAT' => 'SAT',
        'SUN' => 'SUN',
        // Add full day names for more matching possibilities
        'MONDAY' => 'M',
        'TUESDAY' => 'T',
        'WEDNESDAY' => 'W',
        'THURSDAY' => 'TH',
        'FRIDAY' => 'F',
        'SATURDAY' => 'SAT',
        'SUNDAY' => 'SUN',
        // Include numeric values as strings
        '1' => 'M',
        '2' => 'T',
        '3' => 'W',
        '4' => 'TH',
        '5' => 'F',
        '6' => 'SAT',
        '7' => 'SUN'
    ];
    
    // If preferred_day is a known abbreviation, check if it maps to today
    if (isset($day_abbrevs[strtoupper($preferred_day)])) {
        return in_array($day_abbrevs[strtoupper($preferred_day)], $day_codes[$today_day]);
    }
    
    // Check direct day number as integer
    if (is_numeric($preferred_day) && (int)$preferred_day === $today_day) {
        return true;
    }
    
    // Check if preferred_day is the full day name
    $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    if (strcasecmp($preferred_day, $day_names[$today_day - 1]) === 0) {
        return true;
    }
    
    return false;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard - BSIT 2C AMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        /* Import Poppins font */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
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
        
        /* Apply font to everything */
        body, .navbar, .dropdown-menu, .card, .btn {
            font-family: 'Poppins', sans-serif;
        }
        
        body { margin: 0; padding: 0; background: #f5f7fa; color: var(--text-dark); }
        .dashboard-container { padding: 20px; }
        .card { border-radius: 10px; box-shadow: var(--card-shadow); margin-bottom: 20px; border: none; }
        .card-header { border-bottom: 1px solid #eee; font-weight: 600; }
        .welcome-card { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; }
        .stat-card { text-align: center; padding: 10px; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 2.5rem; margin-bottom: 10px; color: var(--primary-color); }
        .stat-value { font-size: 1.5rem; font-weight: bold; color: var(--primary-color); }
        .stat-label { color: #6c757d; font-size: 0.9rem; }
        .progress { height: 8px; margin-top: 5px; }
        .today-class { 
            border-radius: 8px; 
            transition: all 0.2s; 
            border: 1px solid #e5e7eb;
            padding: 15px;
            margin-bottom: 15px;
        }
        .today-class:hover { 
            transform: translateY(-2px); 
            box-shadow: var(--card-shadow);
        }
        .bg-light-info {
            background-color: rgba(13, 202, 240, 0.1);
        }
        .bg-light-success {
            background-color: rgba(25, 135, 84, 0.1);
        }
        .bg-light-secondary {
            background-color: rgba(108, 117, 125, 0.1);
        }
        .class-time { font-size: 0.85rem; color: #6c757d; }
        .attendance-record { 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 15px; 
            transition: all 0.2s;
        }
        
        .attendance-record:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }
        .present { background-color: #d1fae5; }
        .late { background-color: #fef3c7; }
        .absent { background-color: #fee2e2; }
        .status-badge {
            border-radius: 50px;
            padding: 5px 10px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        /* Navbar styles from header */
        .navbar { 
            background-color: var(--primary-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2); 
            padding: 0.75rem 0;
        }
        
        .navbar-brand { 
            font-weight: 600; 
            color: #fff; 
            font-size: 1.25rem;
        }
        
        /* Clock styles from header */
        .digital-clock {
            background: rgba(0,0,0,0.2);
            color: #ecf0f1;
            padding: 10px 15px;
            border-radius: 5px;
            font-weight: 500;
            font-size: 1.2rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .digital-clock .date {
            font-size: 0.85rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>

    <!-- Main Content -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <!-- Alerts Container -->
                <div id="alerts-container"></div>
                
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div class="dashboard-container">
                        <div class="container">
                            <!-- Attendance Statistics -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header bg-white">
                                            <h5 class="mb-0">Attendance Overview</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="stat-card">
                                                        <div class="stat-icon text-primary">
                                                            <i class="bi bi-calendar-check"></i>
                                                        </div>
                                                        <div class="stat-value"><?php echo $attendance_stats['total']; ?></div>
                                                        <div class="stat-label">Total Classes</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="stat-card">
                                                        <div class="stat-icon text-success">
                                                            <i class="bi bi-check-circle"></i>
                                                        </div>
                                                        <div class="stat-value"><?php echo $attendance_stats['present']; ?></div>
                                                        <div class="stat-label">Present</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="stat-card">
                                                        <div class="stat-icon text-warning">
                                                            <i class="bi bi-clock"></i>
                                                        </div>
                                                        <div class="stat-value"><?php echo $attendance_stats['late']; ?></div>
                                                        <div class="stat-label">Late</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="stat-card">
                                                        <div class="stat-icon text-danger">
                                                            <i class="bi bi-x-circle"></i>
                                                        </div>
                                                        <div class="stat-value"><?php echo $attendance_stats['absent']; ?></div>
                                                        <div class="stat-label">Absent</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Progress Bar -->
                                            <div class="mt-4">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>Attendance Rate</span>
                                                    <span><?php echo $present_percent + $late_percent; ?>%</span>
                                                </div>
                                                <div class="progress">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $present_percent; ?>%" aria-valuenow="<?php echo $present_percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $late_percent; ?>%" aria-valuenow="<?php echo $late_percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $absent_percent; ?>%" aria-valuenow="<?php echo $absent_percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <div class="d-flex justify-content-between mt-2">
                                                    <small class="text-success"><?php echo $present_percent; ?>% Present</small>
                                                    <small class="text-warning"><?php echo $late_percent; ?>% Late</small>
                                                    <small class="text-danger"><?php echo $absent_percent; ?>% Absent</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Today's Classes -->
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-white">
                                            <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Today's Classes (<?php echo $today_name; ?>)</h5>
                                        </div>
                                        <div class="card-body">
                                            <?php
                                            // Check if there are classes today directly from our grouped array
                                            $has_classes_today = !empty($classes_by_day[$today_day]);
                                            $current_time = strtotime('now');
                                            $today_classes = $classes_by_day[$today_day];
                                            
                                            // Sort classes by start time
                                            usort($today_classes, function($a, $b) {
                                                return strtotime($a['time_start']) - strtotime($b['time_start']);
                                            });
                                            
                                            if ($has_classes_today) {
                                                foreach ($today_classes as $subject) {
                                                    // Calculate class status (upcoming, ongoing, completed)
                                                    $start_time = strtotime($subject['time_start']);
                                                    $end_time = strtotime($subject['time_end']);
                                                    
                                                    if ($current_time < $start_time) {
                                                        $status = 'upcoming';
                                                        $time_diff = $start_time - $current_time;
                                                        
                                                        // Format time remaining
                                                        if ($time_diff < 3600) { // Less than 1 hour
                                                            $time_remaining = floor($time_diff / 60) . ' minutes';
                                                        } else {
                                                            $hours = floor($time_diff / 3600);
                                                            $minutes = floor(($time_diff % 3600) / 60);
                                                            $time_remaining = $hours . ' hour' . ($hours > 1 ? 's' : '') . 
                                                                            ($minutes > 0 ? ' ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '') : '');
                                                        }
                                                    } elseif ($current_time >= $start_time && $current_time <= $end_time) {
                                                        $status = 'ongoing';
                                                        $time_diff = $end_time - $current_time;
                                                        
                                                        // Format time remaining
                                                        if ($time_diff < 3600) { // Less than 1 hour
                                                            $time_remaining = floor($time_diff / 60) . ' minutes left';
                                                        } else {
                                                            $hours = floor($time_diff / 3600);
                                                            $minutes = floor(($time_diff % 3600) / 60);
                                                            $time_remaining = $hours . ' hour' . ($hours > 1 ? 's' : '') . 
                                                                            ($minutes > 0 ? ' ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '') : '') . ' left';
                                                        }
                                                    } else {
                                                        $status = 'completed';
                                                        $time_remaining = 'Completed';
                                                    }
                                                    
                                                    $status_class = '';
                                                    $badge_color = 'primary';
                                                    $icon = 'clock';
                                                    
                                                    if ($status === 'upcoming') {
                                                        $status_class = 'border-info bg-light-info';
                                                        $badge_color = 'info';
                                                        $icon = 'hourglass-split';
                                                    } elseif ($status === 'ongoing') {
                                                        $status_class = 'border-success bg-light-success';
                                                        $badge_color = 'success';
                                                        $icon = 'play-circle';
                                                    } else {
                                                        $status_class = 'border-secondary bg-light-secondary opacity-75';
                                                        $badge_color = 'secondary';
                                                        $icon = 'check-circle';
                                                    }
                                                    ?>
                                                    <div class="today-class p-3 mb-3 border <?php echo $status_class; ?>">
                                                        <div class="d-flex justify-content-between">
                                                            <div>
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($subject['subject_code']); ?> - <?php echo htmlspecialchars($subject['subject_name']); ?></h6>
                                                                <div class="class-time">
                                                                    <i class="bi bi-clock"></i> <?php echo date('h:i A', strtotime($subject['time_start'])); ?> - <?php echo date('h:i A', strtotime($subject['time_end'])); ?>
                                                                    <i class="bi bi-geo-alt ms-2"></i> <?php echo htmlspecialchars($subject['location']); ?>
                                                                </div>
                                                                <small><i class="bi bi-person-badge me-1"></i>Teacher: <?php echo htmlspecialchars($subject['teacher_name']); ?></small>
                                                            </div>
                                                            <div class="text-end">
                                                                <span class="badge bg-<?php echo $badge_color; ?>">
                                                                    <i class="bi bi-<?php echo $icon; ?> me-1"></i>
                                                                    <?php echo ucfirst($status); ?>
                                                                </span>
                                                                <div class="small mt-1 text-<?php echo $badge_color; ?>">
                                                                    <?php echo $time_remaining; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                            <?php
                                                    }
                                            } else {
                                                echo '<div class="text-center py-5 text-muted">
                                                      <i class="bi bi-calendar-x display-4"></i>
                                                      <p class="mt-3">No classes scheduled for today.</p>
                                                      </div>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Recent Attendance -->
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">Recent Attendance</h5>
                                            <a href="attendance.php" class="btn btn-sm btn-outline-primary">View All</a>
                                        </div>
                                        <div class="card-body">
                                            <?php if ($recent_attendance->num_rows === 0): ?>
                                                <div class="text-center py-5 text-muted">
                                                    <i class="bi bi-clipboard display-4"></i>
                                                    <p class="mt-3">No recent attendance records found.</p>
                                                </div>
                                            <?php else: ?>
                                                <?php while ($record = $recent_attendance->fetch_assoc()): ?>
                                                    <div class="attendance-record <?php echo $record['status']; ?>">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($record['subject_code']); ?> - <?php echo htmlspecialchars($record['subject_name']); ?></h6>
                                                                <small><?php echo date('F d, Y (l)', strtotime($record['attendance_date'])); ?></small>
                                                            </div>
                                                            <span class="status-badge bg-<?php echo $status_styles[$record['status']]['color']; ?> bg-opacity-10 text-<?php echo $status_styles[$record['status']]['color']; ?>">
                                                                <i class="bi bi-<?php echo $status_styles[$record['status']]['icon']; ?>"></i>
                                                                <?php echo $status_styles[$record['status']]['text']; ?>
                                                            </span>
                                                        </div>
                                                        <?php if ($record['remarks']): ?>
                                                            <div class="mt-2 small">
                                                                <i class="bi bi-chat-left-text"></i> <?php echo htmlspecialchars($record['remarks']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- My Subjects -->
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header bg-white">
                                            <h5 class="mb-0">My Subjects</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Subject Code</th>
                                                            <th>Subject Name</th>
                                                            <th>Schedule</th>
                                                            <th>Teacher</th>
                                                            <th>Attendance</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($subjects_data as $subject): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                                <td>
                                                                    <div class="badge bg-primary-subtle text-primary">
                                                                        <i class="bi bi-calendar-day me-1"></i> 
                                                                        <?php echo formatDays($subject['preferred_day']); ?>, 
                                                                        <?php echo date('h:i A', strtotime($subject['time_start'])); ?> - 
                                                                        <?php echo date('h:i A', strtotime($subject['time_end'])); ?>
                                                                        <?php if (!empty($subject['location'])): ?>
                                                                            (<?php echo htmlspecialchars($subject['location']); ?>)
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($subject['teacher_name']); ?></td>
                                                                <td>
                                                                    <?php 
                                                                    if (isset($subject_attendance[$subject['id']])) {
                                                                        $stats = $subject_attendance[$subject['id']];
                                                                        $total = $stats['total'] > 0 ? $stats['total'] : 1;
                                                                        $present_pct = round(($stats['present'] / $total) * 100);
                                                                        $late_pct = round(($stats['late'] / $total) * 100);
                                                                        $absent_pct = round(($stats['absent'] / $total) * 100);
                                                                        
                                                                        echo '<div class="progress" style="height: 5px;">
                                                                                <div class="progress-bar bg-success" style="width: ' . $present_pct . '%"></div>
                                                                                <div class="progress-bar bg-warning" style="width: ' . $late_pct . '%"></div>
                                                                                <div class="progress-bar bg-danger" style="width: ' . $absent_pct . '%"></div>
                                                                             </div>';
                                                                        echo '<small class="d-flex justify-content-between mt-1">
                                                                                <span class="text-success">' . $stats['present'] . '</span>
                                                                                <span class="text-warning">' . $stats['late'] . '</span>
                                                                                <span class="text-danger">' . $stats['absent'] . '</span>
                                                                                <span class="text-muted">Total: ' . $stats['total'] . '</span>
                                                                              </small>';
                                                                    } else {
                                                                        echo '<span class="badge bg-secondary">No Data</span>';
                                                                    }
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include('../includes/footer.php'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // JavaScript for handling enrollment 
    $(document).ready(function() {
        // Enrollment button click handler
        $('.enroll-btn').click(function() {
            var assignmentId = $(this).data('assignment-id');
            var subjectName = $(this).data('subject-name');
            
            // Show confirmation dialog
            if (confirm('Are you sure you want to enroll in ' + subjectName + '?')) {
                // Show loading spinner
                $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enrolling...');
                $(this).prop('disabled', true);
                
                var $button = $(this);
                
                // Send enrollment request
                $.ajax({
                    url: 'process_enrollment.php',
                    type: 'POST',
                    data: {
                        assignment_id: assignmentId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            showAlert('success', response.message);
                            // Reload page after short delay
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            // Show error message
                            showAlert('danger', response.message);
                            // Reset button
                            $button.html('Enroll');
                            $button.prop('disabled', false);
                            
                            // If the error is about missing timetable, show a more detailed message
                            if (response.message && response.message.includes('timetable entry')) {
                                showAlert('warning', 'This subject cannot be enrolled yet because the schedule has not been set up. Please contact the administrator.');
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        // Show error message
                        showAlert('danger', 'An error occurred while processing your request. Please try again later.');
                        console.error(xhr.responseText);
                        
                        // Reset button
                        $button.html('Enroll');
                        $button.prop('disabled', false);
                    }
                });
            }
        });
        
        // Function to show alert message
        function showAlert(type, message) {
            var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                            message +
                            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                            '</div>';
            
            $('#alerts-container').html(alertHtml);
            
            // Scroll to alert
            $('html, body').animate({
                scrollTop: $('#alerts-container').offset().top - 100
            }, 200);
        }
    });
    
    // Digital clock functionality
    function updateClock() {
        var now = new Date();
        var hours = now.getHours();
        var minutes = now.getMinutes();
        var seconds = now.getSeconds();
        
        // Format with leading zeros
        hours = hours < 10 ? '0' + hours : hours;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        
        // Update clock display
        document.getElementById('time').innerHTML = hours + ':' + minutes + ':' + seconds;
        
        // Update every second
        setTimeout(updateClock, 1000);
    }
    
    // Start the clock when page loads
    document.addEventListener('DOMContentLoaded', function() {
        updateClock();
    });
    </script>
</body>
</html>