<?php
// Start session
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: ../login.php');
    exit;
}

// Include database connection
require_once '../config/database.php';

// Include helper functions
require_once '../includes/functions.php';

// Initialize variables
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';
$error_message = '';
$subjects = [];
$attendance_summary = [];
$selected_subject = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Status colors and icons
$status_styles = [
    'present' => ['color' => 'success', 'icon' => 'check-circle-fill', 'text' => 'Present'],
    'late' => ['color' => 'warning', 'icon' => 'clock-fill', 'text' => 'Late'],
    'absent' => ['color' => 'danger', 'icon' => 'x-circle-fill', 'text' => 'Absent']
];

try {
    // Get enrolled subjects for the student
    $subjects_query = "
        SELECT 
            s.id as subject_id,
            s.subject_code,
            s.subject_name,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
            a.preferred_day,
            a.time_start,
            a.time_end,
            a.location
        FROM 
            enrollments e
        JOIN 
            timetable tt ON e.schedule_id = tt.id
        JOIN 
            subjects s ON tt.subject_id = s.id
        JOIN 
            assignments a ON s.id = a.subject_id
        JOIN 
            teachers t ON a.teacher_id = t.id
        WHERE 
            e.student_id = ?
        ORDER BY 
            a.preferred_day, a.time_start";
    
    $stmt = $conn->prepare($subjects_query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error_message = 'You are not enrolled in any subjects.';
    } else {
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
            
            // If this is the first subject and no subject is selected, select this one
            if (!$selected_subject && count($subjects) === 1) {
                $selected_subject = $row['subject_id'];
            }
        }
        
        // Get attendance summary for each subject
        foreach ($subjects as &$subject) {
            $summary_query = "
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
            
            $stmt = $conn->prepare($summary_query);
            $stmt->bind_param("ii", $subject['subject_id'], $student_id);
            $stmt->execute();
            $summary = $stmt->get_result()->fetch_assoc();
            
            // Calculate attendance percentage
            $total = $summary['total_records'] > 0 ? $summary['total_records'] : 1; // Avoid division by zero
            $present_percent = round(($summary['present_count'] / $total) * 100);
            $late_percent = round(($summary['late_count'] / $total) * 100);
            $absent_percent = round(($summary['absent_count'] / $total) * 100);
            
            $subject['attendance_summary'] = [
                'present' => ['count' => $summary['present_count'], 'percent' => $present_percent],
                'late' => ['count' => $summary['late_count'], 'percent' => $late_percent],
                'absent' => ['count' => $summary['absent_count'], 'percent' => $absent_percent],
                'total' => $summary['total_records']
            ];
        }
        
        // If a subject is selected, get attendance details
        if ($selected_subject) {
            // Get attendance records for the selected subject and month
            $attendance_query = "
                SELECT 
                    a.id as attendance_id,
                    a.attendance_date,
                    a.is_pending,
                    ar.status,
                    ar.remarks,
                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                FROM 
                    attendance a
                JOIN 
                    attendance_records ar ON a.id = ar.attendance_id
                JOIN 
                    teachers t ON a.teacher_id = t.id
                WHERE 
                    a.subject_id = ?
                    AND ar.student_id = ?
                    " . (isset($_GET['view']) && $_GET['view'] === 'overall' ? "" : "AND MONTH(a.attendance_date) = ? AND YEAR(a.attendance_date) = ?") . "
                ORDER BY 
                    a.attendance_date DESC";
            
            $stmt = $conn->prepare($attendance_query);
            if (isset($_GET['view']) && $_GET['view'] === 'overall') {
                $stmt->bind_param("ii", $selected_subject, $student_id);
            } else {
                $stmt->bind_param("iiii", $selected_subject, $student_id, $filter_month, $filter_year);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $attendance_summary[] = $row;
            }
        }
    }
} catch (Exception $e) {
    $error_message = 'Error retrieving attendance data: ' . $e->getMessage();
}

// Get dates for month selection
$current_year = date('Y');
$years = range($current_year - 1, $current_year + 1);
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Convert day numbers to names
$day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background: #f5f7fa; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px; border: none; }
        .card-header { border-bottom: 1px solid #eee; font-weight: 600; }
        .subject-card {
            cursor: pointer;
            transition: all 0.2s;
            border-left: 4px solid transparent;
        }
        .subject-card:hover {
            background-color: #f8f9fa;
        }
        .subject-card.active {
            border-left-color: #0d6efd;
            background-color: #f0f7ff;
        }
        .attendance-progress {
            height: 8px;
            margin-top: 8px;
        }
        .attendance-day {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .attendance-item {
            border-left: 3px solid #dee2e6;
            transition: all 0.2s;
        }
        .attendance-item.present {
            border-left-color: #198754;
        }
        .attendance-item.late {
            border-left-color: #ffc107;
        }
        .attendance-item.absent {
            border-left-color: #dc3545;
        }
        .attendance-date {
            width: 100px;
            text-align: center;
        }
        .status-badge {
            border-radius: 50px;
            padding: 5px 10px;
            font-size: 0.85rem;
        }
        .status-icon {
            margin-right: 5px;
        }
        .attendance-summary {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .stat-box {
            text-align: center;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            line-height: 1;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        /* Calendar view styles */
        .calendar-container {
            margin-top: 30px;
        }
        .calendar-header {
            text-align: center;
            margin-bottom: 15px;
        }
        .calendar-month {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .calendar {
            width: 100%;
            border-collapse: collapse;
        }
        .calendar th {
            background-color: #f8f9fa;
            padding: 10px;
            text-align: center;
            font-weight: normal;
            color: #6c757d;
        }
        .calendar td {
            border: 1px solid #dee2e6;
            height: 80px;
            width: 14.28%;
            vertical-align: top;
            padding: 5px;
        }
        .calendar .day-number {
            font-weight: bold;
            margin-bottom: 5px;
            display: inline-block;
            width: 24px;
            height: 24px;
            text-align: center;
            line-height: 24px;
            border-radius: 50%;
        }
        .calendar .today .day-number {
            background-color: #0d6efd;
            color: white;
        }
        .calendar .attendance-status {
            text-align: center;
            padding: 3px;
            border-radius: 4px;
            margin-top: 5px;
            font-size: 0.8rem;
        }
        .calendar .present {
            background-color: rgba(25, 135, 84, 0.2);
            color: #198754;
        }
        .calendar .late {
            background-color: rgba(255, 193, 7, 0.2);
            color: #fd7e14;
        }
        .calendar .absent {
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        .calendar-legend {
            display: flex;
            justify-content: center;
            margin-top: 15px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin: 0 10px;
            font-size: 0.85rem;
        }
        .legend-color {
            width: 15px;
            height: 15px;
            margin-right: 5px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-4">
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= $error_message ?>
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Subjects list -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">My Subjects</h5>
                        </div>
                        <div class="card-body p-0">
                            <a href="?view=overall" class="text-decoration-none text-dark">
                                <div class="subject-card p-3 border-bottom <?= (!$selected_subject && isset($_GET['view']) && $_GET['view'] === 'overall') ? 'active' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="mb-1"><i class="bi bi-calendar-range"></i> Overall Records</h5>
                                            <p class="mb-1">View your complete attendance history</p>
                                        </div>
                                    </div>
                                </div>
                            </a>
                            <?php foreach ($subjects as $subject): ?>
                                <a href="?subject_id=<?= $subject['subject_id'] ?>&month=<?= $filter_month ?>&year=<?= $filter_year ?>" 
                                   class="text-decoration-none text-dark">
                                    <div class="subject-card p-3 border-bottom <?= $selected_subject === $subject['subject_id'] ? 'active' : '' ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="mb-1"><?= htmlspecialchars($subject['subject_code']) ?></h5>
                                                <p class="mb-1"><?= htmlspecialchars($subject['subject_name']) ?></p>
                                                <small class="text-muted"><?= formatDays($subject['preferred_day']) ?>, 
                                                <?= date('h:i A', strtotime($subject['time_start'])) ?> - 
                                                <?= date('h:i A', strtotime($subject['time_end'])) ?></small>
                                            </div>
                                        </div>
                                        
                                        <div class="progress attendance-progress">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                style="width: <?= $subject['attendance_summary']['present']['percent'] ?>%" 
                                                aria-valuenow="<?= $subject['attendance_summary']['present']['percent'] ?>" 
                                                aria-valuemin="0" aria-valuemax="100"></div>
                                            <div class="progress-bar bg-warning" role="progressbar" 
                                                style="width: <?= $subject['attendance_summary']['late']['percent'] ?>%" 
                                                aria-valuenow="<?= $subject['attendance_summary']['late']['percent'] ?>" 
                                                aria-valuemin="0" aria-valuemax="100"></div>
                                            <div class="progress-bar bg-danger" role="progressbar" 
                                                style="width: <?= $subject['attendance_summary']['absent']['percent'] ?>%" 
                                                aria-valuenow="<?= $subject['attendance_summary']['absent']['percent'] ?>" 
                                                aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance details -->
                <div class="col-md-8">
                    <?php if (isset($_GET['view']) && $_GET['view'] === 'overall'): ?>
                        <!-- Overall Attendance View -->
                        <div class="card mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-calendar-range me-2"></i> Overall Attendance Records
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Overall stats summary -->
                                <div class="attendance-summary mb-4">
                                    <h6 class="mb-3">Attendance Summary</h6>
                                    <div class="row">
                                        <?php
                                        // Get overall attendance stats directly from database
                                        $overall_stats_query = "
                                            SELECT 
                                                COUNT(ar.id) as total_records,
                                                COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
                                                COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_count,
                                                COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count
                                            FROM 
                                                attendance a
                                            JOIN 
                                                attendance_records ar ON a.id = ar.attendance_id
                                            WHERE 
                                                ar.student_id = ?";
                                        
                                        $stmt = $conn->prepare($overall_stats_query);
                                        $stmt->bind_param("i", $student_id);
                                        $stmt->execute();
                                        $overall_stats = $stmt->get_result()->fetch_assoc();
                                        
                                        $overall_total = $overall_stats['total_records'];
                                        $overall_present = $overall_stats['present_count'];
                                        $overall_late = $overall_stats['late_count'];
                                        $overall_absent = $overall_stats['absent_count'];
                                        
                                        $overall_present_pct = ($overall_total > 0) ? round(($overall_present / $overall_total) * 100) : 0;
                                        $overall_late_pct = ($overall_total > 0) ? round(($overall_late / $overall_total) * 100) : 0;
                                        $overall_absent_pct = ($overall_total > 0) ? round(($overall_absent / $overall_total) * 100) : 0;
                                        ?>
                                        <div class="col-md-3">
                                            <div class="stat-box bg-light">
                                                <div class="stat-value"><?= $overall_total ?></div>
                                                <div class="stat-label">Total Classes</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-box bg-success bg-opacity-10">
                                                <div class="stat-value text-success"><?= $overall_present ?></div>
                                                <div class="stat-label">Present (<?= $overall_present_pct ?>%)</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-box bg-warning bg-opacity-10">
                                                <div class="stat-value text-warning"><?= $overall_late ?></div>
                                                <div class="stat-label">Late (<?= $overall_late_pct ?>%)</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-box bg-danger bg-opacity-10">
                                                <div class="stat-value text-danger"><?= $overall_absent ?></div>
                                                <div class="stat-label">Absent (<?= $overall_absent_pct ?>%)</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Progress Bar -->
                                    <div class="mt-3">
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar bg-success" style="width: <?= $overall_present_pct ?>%"></div>
                                            <div class="progress-bar bg-warning" style="width: <?= $overall_late_pct ?>%"></div>
                                            <div class="progress-bar bg-danger" style="width: <?= $overall_absent_pct ?>%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-2 small">
                                            <span class="text-success"><?= $overall_present_pct ?>% Present</span>
                                            <span class="text-warning"><?= $overall_late_pct ?>% Late</span>
                                            <span class="text-danger"><?= $overall_absent_pct ?>% Absent</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Overall attendance records -->
                                <h6 class="mb-3">Complete Attendance History</h6>
                                
                                <?php
                                // Get all attendance records for the student
                                $all_records_query = "
                                    SELECT 
                                        a.attendance_date,
                                        a.subject_id,
                                        s.subject_code,
                                        s.subject_name,
                                        ar.status,
                                        ar.remarks,
                                        CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                                    FROM 
                                        attendance a
                                    JOIN 
                                        attendance_records ar ON a.id = ar.attendance_id
                                    JOIN 
                                        subjects s ON a.subject_id = s.id
                                    LEFT JOIN
                                        teachers t ON a.teacher_id = t.id
                                    WHERE 
                                        ar.student_id = ?
                                    ORDER BY 
                                        a.attendance_date DESC, s.subject_code";
                                
                                $stmt = $conn->prepare($all_records_query);
                                $stmt->bind_param("i", $student_id);
                                $stmt->execute();
                                $all_records = $stmt->get_result();
                                
                                if ($all_records->num_rows === 0): 
                                ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-calendar-x display-1 text-muted"></i>
                                        <p class="mt-3">No attendance records found.</p>
                                    </div>
                                <?php else: 
                                    // Group records by month
                                    $records_by_month = [];
                                    while ($record = $all_records->fetch_assoc()) {
                                        $month_key = date('Y-m', strtotime($record['attendance_date']));
                                        $month_name = date('F Y', strtotime($record['attendance_date']));
                                        
                                        if (!isset($records_by_month[$month_key])) {
                                            $records_by_month[$month_key] = [
                                                'name' => $month_name,
                                                'records' => []
                                            ];
                                        }
                                        
                                        $records_by_month[$month_key]['records'][] = $record;
                                    }
                                    
                                    // Display records by month
                                    foreach ($records_by_month as $month_key => $month_data):
                                ?>
                                    <div class="month-section mb-4">
                                        <h5 class="bg-light p-2 rounded"><?= $month_data['name'] ?></h5>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Subject</th>
                                                        <th>Status</th>
                                                        <th>Remarks</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($month_data['records'] as $record): ?>
                                                        <tr>
                                                            <td><?= date('D, M d', strtotime($record['attendance_date'])) ?></td>
                                                            <td>
                                                                <strong><?= htmlspecialchars($record['subject_code']) ?></strong><br>
                                                                <small class="text-muted"><?= htmlspecialchars($record['subject_name']) ?></small>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?= $status_styles[$record['status']]['color'] ?>">
                                                                    <i class="bi bi-<?= $status_styles[$record['status']]['icon'] ?>"></i>
                                                                    <?= $status_styles[$record['status']]['text'] ?>
                                                                </span>
                                                            </td>
                                                            <td><?= $record['remarks'] ? htmlspecialchars($record['remarks']) : '<span class="text-muted">-</span>' ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php 
                                    endforeach;
                                endif; 
                                ?>
                            </div>
                        </div>
                    <?php elseif ($selected_subject): ?>
                        <?php 
                        // Find the selected subject details
                        $selected_subject_details = null;
                        foreach ($subjects as $subject) {
                            if ($subject['subject_id'] == $selected_subject) {
                                $selected_subject_details = $subject;
                                break;
                            }
                        }
                        ?>
                        
                        <div class="card mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <?= htmlspecialchars($selected_subject_details['subject_code']) ?> - 
                                    <?= htmlspecialchars($selected_subject_details['subject_name']) ?>
                                </h5>
                                <span class="badge bg-primary text-white">
                                    <?= htmlspecialchars($selected_subject_details['teacher_name']) ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <!-- Attendance summary -->
                                <div class="attendance-summary mb-4">
                                    <h6 class="mb-3">Attendance Overview</h6>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="stat-box bg-light">
                                                <div class="stat-value"><?= $selected_subject_details['attendance_summary']['total'] ?></div>
                                                <div class="stat-label">Total Classes</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-box bg-success bg-opacity-10">
                                                <div class="stat-value text-success"><?= $selected_subject_details['attendance_summary']['present']['count'] ?></div>
                                                <div class="stat-label">Present</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-box bg-warning bg-opacity-10">
                                                <div class="stat-value text-warning"><?= $selected_subject_details['attendance_summary']['late']['count'] ?></div>
                                                <div class="stat-label">Late</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-box bg-danger bg-opacity-10">
                                                <div class="stat-value text-danger"><?= $selected_subject_details['attendance_summary']['absent']['count'] ?></div>
                                                <div class="stat-label">Absent</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Month filter -->
                                <div class="mb-4">
                                    <form method="GET" class="row g-3 align-items-end">
                                        <input type="hidden" name="subject_id" value="<?= $selected_subject ?>">
                                        <div class="col-md-4">
                                            <label for="month" class="form-label">Month</label>
                                            <select name="month" id="month" class="form-select">
                                                <?php foreach ($months as $key => $month): ?>
                                                    <option value="<?= $key ?>" <?= $key == $filter_month ? 'selected' : '' ?>>
                                                        <?= $month ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="year" class="form-label">Year</label>
                                            <select name="year" id="year" class="form-select">
                                                <?php foreach ($years as $year): ?>
                                                    <option value="<?= $year ?>" <?= $year == $filter_year ? 'selected' : '' ?>>
                                                        <?= $year ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Attendance records -->
                                <?php if (empty($attendance_summary)): ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-calendar-x display-1 text-muted"></i>
                                        <p class="mt-3">No attendance records found for the selected month.</p>
                                    </div>
                                <?php else: ?>
                                    <h6 class="mb-3">Attendance Records for <?= $months[$filter_month] ?> <?= $filter_year ?></h6>
                                    
                                    <?php foreach ($attendance_summary as $record): ?>
                                        <div class="attendance-item p-3 mb-3 <?= $record['status'] ?> rounded shadow-sm">
                                            <div class="row align-items-center">
                                                <div class="col-md-3 col-sm-4">
                                                    <div class="attendance-date">
                                                        <div class="fw-bold"><?= date('d', strtotime($record['attendance_date'])) ?></div>
                                                        <div class="attendance-day"><?= date('D', strtotime($record['attendance_date'])) ?></div>
                                                        <small><?= date('M', strtotime($record['attendance_date'])) ?></small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 col-sm-8">
                                                    <div class="d-flex align-items-center">
                                                        <span class="status-badge bg-<?= $status_styles[$record['status']]['color'] ?>">
                                                            <i class="bi bi-<?= $status_styles[$record['status']]['icon'] ?> status-icon"></i>
                                                            <?= $status_styles[$record['status']]['text'] ?>
                                                        </span>
                                                        <?php if ($record['remarks']): ?>
                                                            <div class="ms-3 small">
                                                                <i class="bi bi-chat-left-text"></i> <?= htmlspecialchars($record['remarks']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="col-md-3 text-end">
                                                    <small class="text-muted">Recorded by: <?= htmlspecialchars($record['teacher_name']) ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Calendar View -->
                        <?php if (!empty($attendance_summary)): ?>
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Calendar View - <?= $months[$filter_month] ?> <?= $filter_year ?></h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    // Create an indexed array of attendance records by date
                                    $records_by_date = [];
                                    foreach ($attendance_summary as $record) {
                                        $date = date('Y-m-d', strtotime($record['attendance_date']));
                                        $records_by_date[$date] = $record;
                                    }
                                    
                                    // Get the number of days in the month
                                    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $filter_month, $filter_year);
                                    
                                    // Get the day of the week for the first day of the month (0 = Sunday, 6 = Saturday)
                                    $first_day = date('w', strtotime("$filter_year-$filter_month-01"));
                                    if ($first_day == 0) $first_day = 7; // Convert Sunday from 0 to 7 for our calendar
                                    
                                    // Generate the calendar
                                    echo '<table class="calendar">';
                                    echo '<thead>';
                                    echo '<tr>';
                                    echo '<th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th>';
                                    echo '</tr>';
                                    echo '</thead>';
                                    echo '<tbody>';
                                    
                                    // Start the calendar with empty cells for days before the first day of the month
                                    echo '<tr>';
                                    for ($i = 1; $i < $first_day; $i++) {
                                        echo '<td></td>';
                                    }
                                    
                                    // Fill in the days of the month
                                    $day_count = $first_day - 1;
                                    for ($day = 1; $day <= $days_in_month; $day++) {
                                        $day_count++;
                                        
                                        // Start a new row if it's the first day of the week
                                        if ($day_count > 7) {
                                            echo '</tr><tr>';
                                            $day_count = 1;
                                        }
                                        
                                        // Check if today
                                        $is_today = (date('Y-m-d') === sprintf('%04d-%02d-%02d', $filter_year, $filter_month, $day));
                                        $today_class = $is_today ? 'today' : '';
                                        
                                        // Check if there's an attendance record for this day
                                        $date_key = sprintf('%04d-%02d-%02d', $filter_year, $filter_month, $day);
                                        $has_record = isset($records_by_date[$date_key]);
                                        
                                        echo '<td class="' . $today_class . '">';
                                        echo '<div class="day-number">' . $day . '</div>';
                                        
                                        if ($has_record) {
                                            $record = $records_by_date[$date_key];
                                            $status = $record['status'];
                                            $icon = $status_styles[$status]['icon'];
                                            $text = $status_styles[$status]['text'];
                                            
                                            echo '<div class="attendance-status ' . $status . '" 
                                                     data-bs-toggle="tooltip" 
                                                     title="' . $text . ($record['remarks'] ? ': ' . htmlspecialchars($record['remarks']) : '') . '">';
                                            echo '<i class="bi bi-' . $icon . '"></i> ' . $text;
                                            echo '</div>';
                                        }
                                        
                                        echo '</td>';
                                    }
                                    
                                    // Fill in empty cells at the end of the month
                                    for ($i = $day_count; $i < 7; $i++) {
                                        echo '<td></td>';
                                    }
                                    
                                    echo '</tr>';
                                    echo '</tbody>';
                                    echo '</table>';
                                    
                                    // Calendar legend
                                    echo '<div class="calendar-legend">';
                                    echo '<div class="legend-item"><div class="legend-color bg-success bg-opacity-25"></div> Present</div>';
                                    echo '<div class="legend-item"><div class="legend-color bg-warning bg-opacity-25"></div> Late</div>';
                                    echo '<div class="legend-item"><div class="legend-color bg-danger bg-opacity-25"></div> Absent</div>';
                                    echo '</div>';
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-arrow-left-circle display-1 text-muted"></i>
                                <p class="mt-3">Select a subject from the list to view attendance records.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include('../includes/footer.php'); ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
</body>
</html> 