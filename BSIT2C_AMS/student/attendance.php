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
                    AND MONTH(a.attendance_date) = ?
                    AND YEAR(a.attendance_date) = ?
                ORDER BY 
                    a.attendance_date DESC";
            
            $stmt = $conn->prepare($attendance_query);
            $stmt->bind_param("iiii", $selected_subject, $student_id, $filter_month, $filter_year);
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
    </style>
</head>
<body>
    <!-- Include header -->
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-4">
        <h1 class="h3 mb-4">My Attendance Records</h1>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= $error_message ?>
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Subjects list -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">My Subjects</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($subjects as $subject): ?>
                                <a href="?subject_id=<?= $subject['subject_id'] ?>&month=<?= $filter_month ?>&year=<?= $filter_year ?>" 
                                   class="text-decoration-none text-dark">
                                    <div class="subject-card p-3 border-bottom <?= $selected_subject === $subject['subject_id'] ? 'active' : '' ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="mb-1"><?= htmlspecialchars($subject['subject_code']) ?></h5>
                                                <p class="mb-1"><?= htmlspecialchars($subject['subject_name']) ?></p>
                                                <small class="text-muted"><?= $day_names[intval($subject['preferred_day']) - 1] ?? 'Unknown' ?>, 
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
                    <?php if ($selected_subject): ?>
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
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <?= htmlspecialchars($selected_subject_details['subject_code']) ?> - 
                                    <?= htmlspecialchars($selected_subject_details['subject_name']) ?>
                                </h5>
                                <span class="badge bg-light text-dark">
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
                                                    <span class="status-badge bg-<?= $status_styles[$record['status']]['color'] ?> bg-opacity-10 text-<?= $status_styles[$record['status']]['color'] ?>">
                                                        <i class="bi bi-<?= $status_styles[$record['status']]['icon'] ?> status-icon"></i>
                                                        <?= $status_styles[$record['status']]['text'] ?>
                                                    </span>
                                                    <?php if ($record['remarks']): ?>
                                                        <div class="mt-2 small text-muted">
                                                            <i class="bi bi-chat-left-text"></i> <?= htmlspecialchars($record['remarks']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($record['is_pending']): ?>
                                                        <div class="mt-2">
                                                            <span class="badge bg-warning text-dark">Pending</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-3 mt-2 mt-md-0 text-md-end">
                                                    <small class="text-muted">By: <?= htmlspecialchars($record['teacher_name']) ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill"></i> Please select a subject to view attendance records.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <footer class="bg-light text-center text-muted py-3 mt-5">
        <div class="container">
            <small>&copy; <?= date('Y') ?> Attendance Management System. All rights reserved.</small>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
</body>
</html> 