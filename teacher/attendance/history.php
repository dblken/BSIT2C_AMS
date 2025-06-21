<?php
session_start();
require_once '../../config/database.php';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../../login.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'];

// Check if subject_id and assignment_id are provided
if (!isset($_GET['subject_id']) || !isset($_GET['assignment_id'])) {
    header('Location: index.php');
    exit;
}

$subject_id = $_GET['subject_id'];
$assignment_id = $_GET['assignment_id'];

// Verify that the subject is assigned to this teacher
$verify_query = "SELECT a.id 
                FROM assignments a 
                WHERE a.id = ? AND a.teacher_id = ? AND a.subject_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("iii", $assignment_id, $teacher_id, $subject_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    header('Location: index.php');
    exit;
}

// Get subject details
$subject_query = "SELECT 
    s.subject_code,
    s.subject_name,
    a.preferred_day,
    TIME_FORMAT(a.time_start, '%h:%i %p') as start_time,
    TIME_FORMAT(a.time_end, '%h:%i %p') as end_time,
    a.location
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.id = ? AND a.teacher_id = ?";
    
$stmt = $conn->prepare($subject_query);
$stmt->bind_param("ii", $assignment_id, $teacher_id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();

// Pagination and filtering
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 10;
$start_record = ($current_page - 1) * $records_per_page;

// Filter parameters
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Filters for the query
$filter_conditions = [];
$filter_params = [$subject_id];
$filter_types = "i";

// Add date filter
if ($filter_month != 'all') {
    $filter_conditions[] = "MONTH(a.attendance_date) = ?";
    $filter_params[] = $filter_month;
    $filter_types .= "s";
}

if ($filter_year != 'all') {
    $filter_conditions[] = "YEAR(a.attendance_date) = ?";
    $filter_params[] = $filter_year;
    $filter_types .= "s";
}

// Add pending status filter
if ($filter_status !== 'all') {
    $is_pending = ($filter_status === 'pending') ? 1 : 0;
    $filter_conditions[] = "a.is_pending = ?";
    $filter_params[] = $is_pending;
    $filter_types .= "i";
}

// Build the query
$filter_condition = "";
if (!empty($filter_conditions)) {
    $filter_condition = "AND " . implode(" AND ", $filter_conditions);
}

// Get total records for pagination
$count_query = "SELECT COUNT(*) as total 
               FROM attendance a
               WHERE a.subject_id = ? $filter_condition";

$stmt = $conn->prepare($count_query);
$stmt->bind_param($filter_types, ...$filter_params);
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get all attendance dates for this subject with filtering and pagination
$attendance_dates_query = "SELECT 
    a.id,
    a.attendance_date,
    a.is_pending,
    a.created_at,
    COUNT(ar.id) as student_count,
    SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
    SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count
    FROM attendance a
    LEFT JOIN attendance_records ar ON a.id = ar.attendance_id
    WHERE a.subject_id = ? $filter_condition
    GROUP BY a.id
    ORDER BY a.attendance_date DESC
    LIMIT ?, ?";
    
$stmt = $conn->prepare($attendance_dates_query);
$stmt->bind_param($filter_types . "ii", ...[...$filter_params, $start_record, $records_per_page]);
$stmt->execute();
$attendance_dates = $stmt->get_result();

// Get months for the filter dropdown
$months_query = "SELECT DISTINCT MONTH(attendance_date) as month, 
                MONTHNAME(attendance_date) as month_name
                FROM attendance 
                WHERE subject_id = ?
                ORDER BY month";
$stmt = $conn->prepare($months_query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$months = $stmt->get_result();

// Get years for the filter dropdown
$years_query = "SELECT DISTINCT YEAR(attendance_date) as year
               FROM attendance 
               WHERE subject_id = ?
               ORDER BY year DESC";
$stmt = $conn->prepare($years_query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$years = $stmt->get_result();

// Get detailed attendance for a specific date if selected
$attendance_details = null;
$selected_date = null;
$attendance_summary = [
    'total' => 0,
    'present' => 0,
    'late' => 0,
    'absent' => 0
];

if (isset($_GET['attendance_id'])) {
    $attendance_id = $_GET['attendance_id'];
    
    // Get date of selected attendance
    $date_query = "SELECT attendance_date FROM attendance WHERE id = ?";
    $stmt = $conn->prepare($date_query);
    $stmt->bind_param("i", $attendance_id);
    $stmt->execute();
    $date_result = $stmt->get_result();
    if ($date_result->num_rows > 0) {
        $selected_date = $date_result->fetch_assoc()['attendance_date'];
    }
    
    // Get attendance details
    $details_query = "SELECT 
        ar.id,
        s.student_id as student_code,
        s.first_name,
        s.middle_name,
        s.last_name,
        ar.status,
        ar.remarks
        FROM attendance_records ar
        JOIN students s ON ar.student_id = s.id
        JOIN attendance a ON ar.attendance_id = a.id
        WHERE a.id = ?
        ORDER BY s.last_name, s.first_name";
        
    $stmt = $conn->prepare($details_query);
    $stmt->bind_param("i", $attendance_id);
    $stmt->execute();
    $attendance_details = $stmt->get_result();
    
    // Calculate summary
    $attendance_summary['total'] = $attendance_details->num_rows;
    
    // Need to reset the result pointer
    while ($record = $attendance_details->fetch_assoc()) {
        if ($record['status'] === 'present') {
            $attendance_summary['present']++;
        } elseif ($record['status'] === 'late') {
            $attendance_summary['late']++;
        } elseif ($record['status'] === 'absent') {
            $attendance_summary['absent']++;
        }
    }
    
    // Reset result pointer
    $attendance_details->data_seek(0);
}

// Convert preferred_day to day name
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
    <title>Attendance History - <?= $subject ? htmlspecialchars($subject['subject_code']) : 'Subject' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        /* Custom styles for the attendance history page */
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
        
        .history-card, .details-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            height: 100%;
        }
        
        .history-card .card-header, .details-card .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }
        
        .history-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .history-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--secondary-color);
            transition: background-color 0.2s ease;
        }
        
        .history-item:hover {
            background-color: rgba(59, 130, 246, 0.05);
        }
        
        .history-item.active {
            background-color: rgba(59, 130, 246, 0.1);
            border-left: 4px solid var(--accent-color);
        }
        
        .history-date {
            font-weight: 600;
            flex-grow: 1;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-present {
            background-color: rgba(5, 150, 105, 0.1);
            color: var(--success-color);
        }
        
        .status-late {
            background-color: rgba(217, 119, 6, 0.1);
            color: var(--warning-color);
        }
        
        .status-absent {
            background-color: rgba(220, 38, 38, 0.1);
            color: var(--danger-color);
        }
        
        .summary-card {
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
        }
        
        .summary-item {
            padding: 1rem;
            text-align: center;
        }
        
        .summary-item .number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .summary-label {
            font-size: 0.875rem;
            color: var(--text-light);
            font-weight: 500;
        }
        
        .progress {
            height: 6px;
            border-radius: 3px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .container {
                width: 100%;
                max-width: 100%;
            }
            .card {
                border: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <?php include_once '../includes/header.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Attendance</a></li>
                    <li class="breadcrumb-item active" aria-current="page">History</li>
                </ol>
            </nav>
            <div>
                <a href="take_attendance.php?subject_id=<?= $subject_id ?>&assignment_id=<?= $assignment_id ?>" class="btn btn-primary">
                    <i class="bi bi-clipboard-check me-1"></i> Take Attendance
                </a>
                <?php if ($selected_date): ?>
                <button class="btn btn-outline-secondary ms-2" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="" id="filterForm" class="row g-3 align-items-end">
                    <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
                    <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
                    
                    <div class="col-md-3">
                        <label for="month" class="form-label">Month</label>
                        <select class="form-select" id="month" name="month">
                            <option value="all" <?= $filter_month == 'all' ? 'selected' : '' ?>>All Months</option>
                            <?php while ($month = $months->fetch_assoc()): ?>
                            <option value="<?= $month['month'] ?>" <?= $filter_month == $month['month'] ? 'selected' : '' ?>>
                                <?= $month['month_name'] ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="year" class="form-label">Year</label>
                        <select class="form-select" id="year" name="year">
                            <option value="all" <?= $filter_year == 'all' ? 'selected' : '' ?>>All Years</option>
                            <?php while ($year = $years->fetch_assoc()): ?>
                            <option value="<?= $year['year'] ?>" <?= $filter_year == $year['year'] ? 'selected' : '' ?>>
                                <?= $year['year'] ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?= $filter_status == 'all' ? 'selected' : '' ?>>All Records</option>
                            <option value="regular" <?= $filter_status == 'regular' ? 'selected' : '' ?>>Regular</option>
                            <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-filter me-1"></i> Filter
                        </button>
                        <a href="history.php?subject_id=<?= $subject_id ?>&assignment_id=<?= $assignment_id ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="history-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Attendance Dates</h5>
                        <span class="badge bg-primary"><?= $total_records ?> Records</span>
                    </div>
                    
                    <div class="history-list">
                        <?php if ($attendance_dates->num_rows === 0): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-calendar-x mb-2" style="font-size: 2rem;"></i>
                            <p class="mb-0">No attendance records found</p>
                        </div>
                        <?php else: ?>
                            <?php while ($date = $attendance_dates->fetch_assoc()): ?>
                            <a href="history.php?subject_id=<?= $subject_id ?>&assignment_id=<?= $assignment_id ?>&attendance_id=<?= $date['id'] ?>&month=<?= $filter_month ?>&year=<?= $filter_year ?>&status=<?= $filter_status ?>&page=<?= $current_page ?>" 
                               class="history-item text-decoration-none text-dark <?= (isset($_GET['attendance_id']) && $_GET['attendance_id'] == $date['id']) ? 'active' : '' ?>">
                                <div class="d-flex flex-column flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="history-date">
                                            <i class="bi bi-calendar-date me-2"></i>
                                            <?= date('F d, Y (l)', strtotime($date['attendance_date'])) ?>
                                        </div>
                                        <?php if ($date['is_pending']): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex mt-2 text-muted small">
                                        <div class="me-3">
                                            <i class="bi bi-people me-1"></i> <?= $date['student_count'] ?> students
                                        </div>
                                        <div class="me-3 text-success">
                                            <i class="bi bi-check-circle me-1"></i> <?= $date['present_count'] ?> present
                                        </div>
                                        <div class="me-3 text-warning">
                                            <i class="bi bi-clock me-1"></i> <?= $date['late_count'] ?> late
                                        </div>
                                        <div class="text-danger">
                                            <i class="bi bi-x-circle me-1"></i> <?= $date['absent_count'] ?> absent
                                        </div>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right text-muted ms-3"></i>
                            </a>
                            <?php endwhile; ?>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div class="d-flex justify-content-center p-3 border-top">
                                <nav aria-label="Attendance history pagination">
                                    <ul class="pagination mb-0">
                                        <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?subject_id=<?= $subject_id ?>&assignment_id=<?= $assignment_id ?>&month=<?= $filter_month ?>&year=<?= $filter_year ?>&status=<?= $filter_status ?>&page=<?= $current_page - 1 ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                        
                                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                        <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                            <a class="page-link" href="?subject_id=<?= $subject_id ?>&assignment_id=<?= $assignment_id ?>&month=<?= $filter_month ?>&year=<?= $filter_year ?>&status=<?= $filter_status ?>&page=<?= $i ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?subject_id=<?= $subject_id ?>&assignment_id=<?= $assignment_id ?>&month=<?= $filter_month ?>&year=<?= $filter_year ?>&status=<?= $filter_status ?>&page=<?= $current_page + 1 ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <?php if ($selected_date && $attendance_details): ?>
                <div class="details-card">
                    <div class="card-header">
                        <h5 class="mb-1"><?= htmlspecialchars($subject['subject_code']) ?> - <?= htmlspecialchars($subject['subject_name']) ?></h5>
                        <p class="mb-0">Attendance for <?= date('F d, Y (l)', strtotime($selected_date)) ?></p>
                    </div>
                    
                    <div class="card-body">
                        <!-- Attendance Summary -->
                        <div class="row summary-card mx-0 mb-4">
                            <div class="col-3 summary-item border-end">
                                <div class="number"><?= $attendance_summary['total'] ?></div>
                                <div class="summary-label">Students</div>
                            </div>
                            <div class="col-3 summary-item border-end">
                                <div class="number text-success"><?= $attendance_summary['present'] ?></div>
                                <div class="summary-label">Present</div>
                            </div>
                            <div class="col-3 summary-item border-end">
                                <div class="number text-warning"><?= $attendance_summary['late'] ?></div>
                                <div class="summary-label">Late</div>
                            </div>
                            <div class="col-3 summary-item">
                                <div class="number text-danger"><?= $attendance_summary['absent'] ?></div>
                                <div class="summary-label">Absent</div>
                            </div>
                            
                            <div class="col-12 px-3 pb-3">
                                <div class="progress">
                                    <?php if ($attendance_summary['total'] > 0): ?>
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?= calculatePercentage($attendance_summary['present'], $attendance_summary['total']) ?>%" 
                                         aria-valuenow="<?= $attendance_summary['present'] ?>" aria-valuemin="0" aria-valuemax="<?= $attendance_summary['total'] ?>"></div>
                                    <div class="progress-bar bg-warning" role="progressbar" 
                                         style="width: <?= calculatePercentage($attendance_summary['late'], $attendance_summary['total']) ?>%" 
                                         aria-valuenow="<?= $attendance_summary['late'] ?>" aria-valuemin="0" aria-valuemax="<?= $attendance_summary['total'] ?>"></div>
                                    <div class="progress-bar bg-danger" role="progressbar" 
                                         style="width: <?= calculatePercentage($attendance_summary['absent'], $attendance_summary['total']) ?>%" 
                                         aria-valuenow="<?= $attendance_summary['absent'] ?>" aria-valuemin="0" aria-valuemax="<?= $attendance_summary['total'] ?>"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detailed Attendance -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="5%">#</th>
                                        <th width="15%">Student ID</th>
                                        <th width="30%">Name</th>
                                        <th width="15%">Status</th>
                                        <th width="35%">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; ?>
                                    <?php while ($record = $attendance_details->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $counter++ ?></td>
                                        <td><?= htmlspecialchars($record['student_code']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($record['last_name']) ?>,
                                            <?= htmlspecialchars($record['first_name']) ?>
                                            <?= $record['middle_name'] ? htmlspecialchars($record['middle_name'][0]) . '.' : '' ?>
                                        </td>
                                        <td>
                                            <?php if ($record['status'] === 'present'): ?>
                                            <span class="status-badge status-present">
                                                <i class="bi bi-check-circle me-1"></i>Present
                                            </span>
                                            <?php elseif ($record['status'] === 'late'): ?>
                                            <span class="status-badge status-late">
                                                <i class="bi bi-clock me-1"></i>Late
                                            </span>
                                            <?php elseif ($record['status'] === 'absent'): ?>
                                            <span class="status-badge status-absent">
                                                <i class="bi bi-x-circle me-1"></i>Absent
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($record['remarks'] ?: '-') ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="details-card d-flex flex-column align-items-center justify-content-center" style="min-height: 400px;">
                    <i class="bi bi-calendar-check mb-3" style="font-size: 3rem; color: #d1d5db;"></i>
                    <h4 class="text-muted">Select a date to view attendance details</h4>
                    <?php if ($attendance_dates->num_rows === 0): ?>
                    <p class="text-muted mt-2">No attendance records available for this subject</p>
                    <a href="take_attendance.php?subject_id=<?= $subject_id ?>&assignment_id=<?= $assignment_id ?>" class="btn btn-primary mt-2">
                        <i class="bi bi-clipboard-check me-1"></i> Take Attendance
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
</body>
</html> 