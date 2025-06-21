<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
include '../includes/header.php';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../../login.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'];

// Get all attendance records for the teacher
$query = "SELECT 
    a.id as attendance_id,
    a.attendance_date,
    a.is_pending,
    a.subject_id,
    a.assignment_id,
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
    ORDER BY a.attendance_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$records = $stmt->get_result();

// Get total statistics
$stats_query = "SELECT 
    COUNT(DISTINCT a.id) as total_sessions,
    COUNT(DISTINCT s.id) as total_subjects,
    SUM(CASE WHEN a.is_pending = 1 THEN 1 ELSE 0 END) as pending_count,
    COUNT(ar.id) as total_records,
    SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as total_present,
    SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as total_late,
    SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as total_absent
    FROM attendance a
    LEFT JOIN subjects s ON a.subject_id = s.id
    LEFT JOIN attendance_records ar ON a.id = ar.attendance_id
    WHERE a.teacher_id = ?
    GROUP BY NULL";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $teacher_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-dark">
                <i class="bi bi-clock-history me-2"></i>All Attendance Records
            </h1>
            <p class="text-muted mb-0">Comprehensive view of all attendance records</p>
        </div>
        <a href="../attendance.php" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-2"></i>Back to Attendance
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="stats-icon bg-primary">
                                <i class="bi bi-calendar2-check text-white fs-5"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Sessions</h6>
                            <h4 class="mb-0"><?php echo $stats['total_sessions']; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="stats-icon bg-success">
                                <i class="bi bi-person-check text-white fs-5"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Present</h6>
                            <h4 class="mb-0"><?php echo $stats['total_present']; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="stats-icon bg-warning">
                                <i class="bi bi-hourglass-split text-white fs-5"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Pending Records</h6>
                            <h4 class="mb-0"><?php echo $stats['pending_count']; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="stats-icon bg-info">
                                <i class="bi bi-book text-white fs-5"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Subjects</h6>
                            <h4 class="mb-0"><?php echo $stats['total_subjects']; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Records Table -->
    <div class="card shadow-sm border-0" style="border-radius: 10px;">
        <div class="card-header py-3" style="background-color: #021f3f; border-radius: 10px 10px 0 0;">
            <h6 class="m-0 font-weight-bold text-white">
                <i class="bi bi-list-check me-2"></i>Attendance History
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Date</th>
                            <th>Subject</th>
                            <th class="text-center">Present</th>
                            <th class="text-center">Late</th>
                            <th class="text-center">Absent</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($records->num_rows === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                                    <h5 class="text-muted mb-2">No Records Found</h5>
                                    <p class="text-muted mb-3">No attendance records have been created yet.</p>
                                    <a href="../attendance.php" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-2"></i>Take Attendance
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php while ($record = $records->fetch_assoc()): ?>
                            <tr>
                                <td class="px-4">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar-event me-2 text-muted"></i>
                                        <?php echo date('F d, Y', strtotime($record['attendance_date'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['subject_code']); ?></strong>
                                    <div class="small text-muted"><?php echo htmlspecialchars($record['subject_name']); ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success rounded-pill px-3"><?php echo $record['present_count']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-warning rounded-pill px-3"><?php echo $record['late_count']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-danger rounded-pill px-3"><?php echo $record['absent_count']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary rounded-pill px-3"><?php echo $record['total_records']; ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($record['is_pending']): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-hourglass-split me-1"></i>Pending
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>Finalized
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="view_attendance.php?attendance_id=<?php echo $record['attendance_id']; ?>&subject_id=<?php echo $record['subject_id']; ?>&assignment_id=<?php echo $record['assignment_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i>View Details
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.stats-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.badge {
    font-weight: 500;
    padding: 0.5em 0.8em;
}

.table > :not(caption) > * > * {
    padding: 1rem 0.75rem;
    vertical-align: middle;
}

.breadcrumb-item a {
    color: #021f3f;
    text-decoration: none;
}

.breadcrumb-item.active {
    color: #6c757d;
}

.btn-outline-primary {
    color: #021f3f;
    border-color: #021f3f;
}

.btn-outline-primary:hover {
    background-color: #021f3f;
    border-color: #021f3f;
    color: white;
}

.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

.table tr:hover {
    background-color: rgba(2, 31, 63, 0.02);
}

.badge.rounded-pill {
    min-width: 60px;
}
</style>

<?php include '../includes/footer.php'; ?> 