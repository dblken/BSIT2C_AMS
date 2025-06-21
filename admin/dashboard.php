<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session_protection.php'; // Include the session protection file

// Use the verify_session function to check admin session
verify_session('admin');

// Get admin details
$sql = "SELECT a.*, u.username, u.last_login 
        FROM admins a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// If admin not found, get basic info
if (!$admin) {
    // Attempt to get admin info directly from admins table without join
    $sql = "SELECT * FROM admins WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    // If still no admin, set defaults
    if (!$admin) {
        $admin = [
            'username' => 'Administrator',
            'last_login' => date('Y-m-d H:i:s')
        ];
    }
}

// Get statistics
$stats = [
    'students' => $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'],
    'teachers' => $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'],
    'subjects' => $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'],
    'assignments' => $conn->query("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subjects' AND COLUMN_NAME = 'teacher_id'")->fetch_assoc()['count'] > 0 
        ? $conn->query("SELECT COUNT(*) as count FROM subjects WHERE teacher_id IS NOT NULL")->fetch_assoc()['count']
        : $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count']
];

// Get recent subjects
$recent_subjects = $conn->query("SELECT * FROM subjects ORDER BY created_at DESC LIMIT 5");

// Get recent teacher assignments
// First check if teacher_id column exists in subjects table
$check_teacher_id = $conn->query("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subjects' AND COLUMN_NAME = 'teacher_id'")->fetch_assoc()['count'];

if ($check_teacher_id > 0) {
    $recent_assignments = $conn->query("
        SELECT s.subject_name, t.first_name, t.last_name, COALESCE(s.updated_at, s.created_at) as updated_at
        FROM subjects s
        JOIN teachers t ON s.teacher_id = t.id
        WHERE s.teacher_id IS NOT NULL
        ORDER BY s.updated_at DESC, s.created_at DESC
        LIMIT 5
    ");
} else {
    // Fallback to just showing recent subjects if teacher_id doesn't exist
    $recent_assignments = $conn->query("
        SELECT s.subject_name, 'Not Assigned' as first_name, '' as last_name, COALESCE(s.updated_at, s.created_at) as updated_at
        FROM subjects s
        ORDER BY s.updated_at DESC, s.created_at DESC
        LIMIT 5
    ");
}

// Get recent attendance
$recent_attendance = $conn->query("
    SELECT a.attendance_date as date, COUNT(ar.id) as count, s.subject_name
    FROM attendance a
    JOIN subjects s ON a.subject_id = s.id
    JOIN attendance_records ar ON a.id = ar.attendance_id
    GROUP BY a.attendance_date, a.subject_id
    ORDER BY a.attendance_date DESC
    LIMIT 5
");

include 'includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4 justify-content-center">
        <div class="col-12 col-xxl-10">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <h2 class="fw-bold text-primary mb-2">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard Overview
                        </h2>
                        <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($admin['username'] ?? 'Administrator'); ?>! Here's what's happening with your attendance management system.</p>
                        <div class="mt-2">
                            <span class="badge bg-light text-dark">Last login: <?php echo isset($admin['last_login']) ? date('M d, Y h:i A', strtotime($admin['last_login'])) : date('M d, Y h:i A'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4 justify-content-center">
        <div class="col-12 col-xxl-10">
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body position-relative p-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="fw-bold text-primary mb-0">Teachers</h5>
                                <div class="icon-circle bg-primary text-white">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                            </div>
                            <h2 class="display-4 fw-bold mb-0"><?php echo $stats['teachers']; ?></h2>
                            <p class="text-muted mb-0">Active teaching staff</p>
                            <a href="/BSIT2C_AMS/admin/teachers/index.php" class="stretched-link"></a>
                        </div>
                        <div class="card-footer border-0 bg-primary bg-opacity-10 py-2">
                            <small class="text-primary fw-bold">
                                <i class="fas fa-arrow-right me-1"></i> View teachers
                            </small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body position-relative p-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="fw-bold text-success mb-0">Students</h5>
                                <div class="icon-circle bg-success text-white">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                            </div>
                            <h2 class="display-4 fw-bold mb-0"><?php echo $stats['students']; ?></h2>
                            <p class="text-muted mb-0">Enrolled students</p>
                            <a href="/BSIT2C_AMS/admin/students/index.php" class="stretched-link"></a>
                        </div>
                        <div class="card-footer border-0 bg-success bg-opacity-10 py-2">
                            <small class="text-success fw-bold">
                                <i class="fas fa-arrow-right me-1"></i> View students
                            </small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body position-relative p-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="fw-bold text-info mb-0">Subjects</h5>
                                <div class="icon-circle bg-info text-white">
                                    <i class="fas fa-book"></i>
                                </div>
                            </div>
                            <h2 class="display-4 fw-bold mb-0"><?php echo $stats['subjects']; ?></h2>
                            <p class="text-muted mb-0">Active courses</p>
                            <a href="/BSIT2C_AMS/admin/subjects/index.php" class="stretched-link"></a>
                        </div>
                        <div class="card-footer border-0 bg-info bg-opacity-10 py-2">
                            <small class="text-info fw-bold">
                                <i class="fas fa-arrow-right me-1"></i> View subjects
                            </small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body position-relative p-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="fw-bold text-warning mb-0">Assignments</h5>
                                <div class="icon-circle bg-warning text-white">
                                    <i class="fas fa-tasks"></i>
                                </div>
                            </div>
                            <h2 class="display-4 fw-bold mb-0"><?php echo $stats['assignments']; ?></h2>
                            <p class="text-muted mb-0">Teacher-subject assignments</p>
                            <a href="/BSIT2C_AMS/admin/assignments/index.php" class="stretched-link"></a>
                        </div>
                        <div class="card-footer border-0 bg-warning bg-opacity-10 py-2">
                            <small class="text-warning fw-bold">
                                <i class="fas fa-arrow-right me-1"></i> View assignments
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity Section -->
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-10">
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-gradient-primary-to-secondary p-4 text-white">
                            <h5 class="fw-bold mb-0">
                                <i class="fas fa-history me-2"></i> Recent Teacher Assignments
                            </h5>
                        </div>
                        <div class="card-body px-4 pb-4">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th scope="col" width="40%">Subject</th>
                                            <th scope="col" width="35%">Teacher</th>
                                            <th scope="col" width="25%">Date Assigned</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($recent_assignments->num_rows > 0): ?>
                                            <?php while ($assignment = $recent_assignments->fetch_assoc()): ?>
                                            <tr>
                                                <td class="fw-medium"><?php echo htmlspecialchars($assignment['subject_name']); ?></td>
                                                <td><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?></td>
                                                <td><span class="badge bg-light text-dark"><?php echo $assignment['updated_at'] ? date('M d, Y', strtotime($assignment['updated_at'])) : 'Not Available'; ?></span></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center py-4">No recent assignments found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="/BSIT2C_AMS/admin/subjects/index.php" class="btn btn-sm btn-primary mt-2">
                                View All Subjects
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-gradient-primary-to-secondary p-4 text-white">
                            <h5 class="fw-bold mb-0">
                                <i class="fas fa-clipboard-check me-2"></i> Recent Attendance Records
                            </h5>
                        </div>
                        <div class="card-body px-4 pb-4">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th scope="col" width="25%">Date</th>
                                            <th scope="col" width="50%">Subject</th>
                                            <th scope="col" width="25%">Records</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($recent_attendance && $recent_attendance->num_rows > 0): ?>
                                            <?php while ($record = $recent_attendance->fetch_assoc()): ?>
                                            <tr>
                                                <td><span class="badge bg-light text-dark"><?php echo date('M d, Y', strtotime($record['date'])); ?></span></td>
                                                <td class="fw-medium"><?php echo htmlspecialchars($record['subject_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-success rounded-pill"><?php echo $record['count']; ?> students</span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center py-4">No recent attendance records found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="/BSIT2C_AMS/admin/attendance/index.php" class="btn btn-sm btn-primary mt-2">
                                View All Attendance
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    :root {
        --primary-color: #021F3F;
        --secondary-color: #C8A77E;
        --primary-hover: #042b59;
        --secondary-hover: #b39268;
    }
    
    .bg-gradient-primary-to-secondary {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    }
    
    .icon-circle {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    
    .icon-circle.bg-primary {
        background-color: var(--primary-color) !important;
    }
    
    .icon-circle.bg-success {
        background-color: var(--secondary-color) !important;
        color: #fff !important;
    }
    
    .icon-circle.bg-info, 
    .icon-circle.bg-warning {
        background-color: var(--primary-color) !important;
        opacity: 0.8;
    }
    
    .text-primary {
        color: var(--primary-color) !important;
    }
    
    .text-success, 
    .text-info, 
    .text-warning {
        color: var(--secondary-color) !important;
    }
    
    .card-footer.bg-primary {
        background-color: rgba(2, 31, 63, 0.1) !important;
    }
    
    .card-footer.bg-success,
    .card-footer.bg-info,
    .card-footer.bg-warning {
        background-color: rgba(200, 167, 126, 0.1) !important;
    }
    
    .text-primary.fw-bold,
    .text-success.fw-bold,
    .text-info.fw-bold,
    .text-warning.fw-bold {
        color: var(--primary-color) !important;
    }
    
    .btn-primary {
        background-color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
    }
    
    .btn-primary:hover, 
    .btn-primary:focus, 
    .btn-primary:active {
        background-color: var(--primary-hover) !important;
        border-color: var(--primary-hover) !important;
    }
    
    .btn-outline-primary {
        color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
    }
    
    .btn-outline-primary:hover,
    .btn-outline-primary:focus,
    .btn-outline-primary:active {
        background-color: var(--primary-color) !important;
        color: white !important;
    }
    
    .card {
        transition: transform 0.2s ease-in-out;
        overflow: hidden;
        border-radius: 0.5rem;
    }
    
    .card:hover {
        transform: translateY(-5px);
    }
    
    .stretched-link::after {
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 1;
        content: "";
    }
    
    .table th {
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 1rem;
    }
    
    .table td {
        font-size: 0.95rem;
        padding: 0.75rem 1rem;
        vertical-align: middle;
    }
    
    .badge {
        font-weight: 500;
        padding: 0.4em 0.8em;
    }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .bg-light.text-dark {
        background-color: rgba(2, 31, 63, 0.1) !important;
        color: var(--primary-color) !important;
    }
    
    @media (max-width: 768px) {
        .display-4 {
            font-size: 2.5rem;
        }
    }
</style>

<?php include 'includes/admin_footer.php'; ?> 