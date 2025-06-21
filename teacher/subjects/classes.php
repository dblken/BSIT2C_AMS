<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
include '../includes/header.php';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../login.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'];
$current_semester = 'First'; // You can make this dynamic based on system settings
$current_school_year = '2023-2024'; // You can make this dynamic based on system settings

// Get teacher's subjects with their corresponding assignment_ids and latest attendance
$query = "SELECT 
    s.id as subject_id,
    s.subject_code,
    s.subject_name,
    a.id as assignment_id,
    a.preferred_day,
    a.time_start,
    a.time_end,
    a.location,
    a.month_from,
    a.month_to,
    (SELECT att.id 
     FROM attendance att 
     WHERE att.subject_id = s.id 
     AND att.teacher_id = ? 
     ORDER BY att.attendance_date DESC, att.id DESC 
     LIMIT 1) as latest_attendance_id
FROM assignments a
JOIN subjects s ON a.subject_id = s.id
WHERE a.teacher_id = ?
ORDER BY s.subject_code";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $teacher_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<style>
    :root {
        --primary-color: #021F3F;
        --secondary-color: #C8A77E;
        --primary-dark: #011327;
        --secondary-light: #d8b78e;
        --text-dark: #1f2937;
        --text-light: #6b7280;
        --success-color: #059669;
        --warning-color: #d97706;
        --danger-color: #dc2626;
        --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
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
        background-color: var(--secondary-color);
        border-radius: 2px;
    }
    
    .subject-card {
        background-color: white;
        border-radius: 10px;
        box-shadow: var(--card-shadow);
        margin-bottom: 1.5rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .subject-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px -1px rgba(0, 0, 0, 0.1), 0 3px 6px -1px rgba(0, 0, 0, 0.06);
    }
    
    .subject-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 10px 10px 0 0;
    }
    
    .subject-body {
        padding: 1.5rem;
    }
    
    .subject-info {
        display: flex;
        align-items: center;
        margin-bottom: 0.5rem;
        color: var(--text-light);
    }
    
    .subject-info i {
        width: 24px;
        color: var(--secondary-color);
    }
    
    .subject-footer {
        padding: 1rem 1.5rem;
        background-color: rgba(2, 31, 63, 0.05);
        border-radius: 0 0 10px 10px;
    }
    
    .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }
    
    .btn-primary:hover {
        background-color: var(--primary-dark);
        border-color: var(--primary-dark);
    }
    
    .btn-outline-secondary {
        color: var(--primary-color);
        border-color: var(--secondary-color);
    }
    
    .btn-outline-secondary:hover {
        background-color: var(--secondary-color);
        color: var(--primary-color);
        border-color: var(--secondary-color);
    }
    
    .no-subjects {
        text-align: center;
        padding: 3rem;
        color: var(--text-light);
    }
    
    .no-subjects i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: #d1d5db;
    }
</style>

<div class="container py-4">
    <h1 class="page-title">My Subjects</h1>
    
    <?php if ($result->num_rows === 0): ?>
    <div class="no-subjects">
        <i class="bi bi-book"></i>
        <h3>No Subjects Found</h3>
        <p class="text-muted">You don't have any assigned subjects yet.</p>
    </div>
    <?php else: ?>
    <div class="row">
        <?php while ($subject = $result->fetch_assoc()): ?>
        <div class="col-md-6 col-lg-4">
            <div class="subject-card">
                <div class="subject-header">
                    <h5 class="mb-1"><?= htmlspecialchars($subject['subject_code']) ?></h5>
                    <p class="mb-0"><?= htmlspecialchars($subject['subject_name']) ?></p>
                </div>
                <div class="subject-body">
                    <div class="subject-info">
                        <i class="bi bi-calendar-event"></i>
                        <span>
                        <?php 
                        // Check if preferred_day is in JSON format and format it properly
                        echo formatDays($subject['preferred_day']);
                        ?>
                        </span>
                    </div>
                    <div class="subject-info">
                        <i class="bi bi-clock"></i>
                        <span><?= date('h:i A', strtotime($subject['time_start'])) ?> - <?= date('h:i A', strtotime($subject['time_end'])) ?></span>
                    </div>
                    <div class="subject-info">
                        <i class="bi bi-geo-alt"></i>
                        <span><?= htmlspecialchars($subject['location']) ?></span>
                    </div>
                </div>
                <div class="subject-footer">
                    <div class="d-grid gap-2">
                        <a href="../attendance/take_attendance.php?subject_id=<?= $subject['subject_id'] ?>&assignment_id=<?= $subject['assignment_id'] ?>&date=<?= date('Y-m-d') ?>" class="btn btn-primary">
                            <i class="bi bi-clipboard-check me-1"></i> Take Attendance
                        </a>
                        <a href="../attendance/view_attendance.php?subject_id=<?= $subject['subject_id'] ?>&assignment_id=<?= $subject['assignment_id'] ?><?= $subject['latest_attendance_id'] ? '&attendance_id=' . $subject['latest_attendance_id'] : '' ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-clock-history me-1"></i> View History
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?> 