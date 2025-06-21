<?php
session_start();
require_once '../../config/database.php';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../../login.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'];
$today = date('Y-m-d');
$past_month_start = date('Y-m-d', strtotime('-30 days'));
$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$day_of_week = date('N'); // 1 (Monday) to 7 (Sunday)

// Get pending attendance records (explicitly marked as pending)
$existing_pending_query = "
    SELECT 
        a.id as attendance_id,
        a.subject_id,
        a.assignment_id,
        a.attendance_date,
        s.subject_code,
        s.subject_name,
        CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
        ass.time_start,
        ass.time_end,
        ass.location,
        COUNT(ar.id) as records_count
    FROM 
        attendance a
    JOIN 
        subjects s ON a.subject_id = s.id
    JOIN 
        teachers t ON a.teacher_id = t.id
    JOIN
        assignments ass ON a.assignment_id = ass.id
    LEFT JOIN 
        attendance_records ar ON a.id = ar.attendance_id
    WHERE 
        a.teacher_id = ? AND
        a.is_pending = 1
    GROUP BY 
        a.id, a.subject_id, a.assignment_id, a.attendance_date, s.subject_code, 
        s.subject_name, teacher_name, ass.time_start, ass.time_end, ass.location
    ORDER BY 
        a.attendance_date DESC";

$stmt = mysqli_prepare($conn, $existing_pending_query);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$existing_pending = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// IMPROVED: Get missed classes for today where attendance wasn't taken
// This now checks all classes including past ones
$missed_classes_query = "
    SELECT 
        a.id as assignment_id,
        a.subject_id,
        a.preferred_day,
        a.time_start,
        a.time_end,
        a.location,
        s.subject_code,
        s.subject_name,
        (SELECT COUNT(*) FROM enrollments e JOIN timetable t ON e.schedule_id = t.id WHERE t.subject_id = s.id) as enrolled_students
    FROM 
        assignments a
    JOIN 
        subjects s ON a.subject_id = s.id
    WHERE 
        a.teacher_id = ? AND
        a.preferred_day = ? AND
        NOT EXISTS (
            SELECT 1 FROM attendance att 
            WHERE att.teacher_id = a.teacher_id 
            AND att.subject_id = a.subject_id 
            AND att.assignment_id = a.id 
            AND att.attendance_date = ?
        )
    ORDER BY 
        a.time_start ASC";

$stmt = mysqli_prepare($conn, $missed_classes_query);
mysqli_stmt_bind_param($stmt, "iis", $teacher_id, $day_of_week, $current_date);
mysqli_stmt_execute($stmt);
$missed_classes = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Process auto-creation of pending records
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_pending'])) {
    $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
    $subject_id = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : 0;
    
    if ($assignment_id && $subject_id) {
        try {
            // Start transaction
            mysqli_autocommit($conn, FALSE);
            
            // Create pending attendance record
            $insert_query = "
                INSERT INTO attendance (
                    teacher_id, subject_id, assignment_id, attendance_date, is_pending, created_at
                ) VALUES (?, ?, ?, ?, 1, NOW())";
            
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "iiis", $teacher_id, $subject_id, $assignment_id, $current_date);
            $result = mysqli_stmt_execute($stmt);
            
            if (!$result) {
                throw new Exception("Failed to create pending record: " . mysqli_stmt_error($stmt));
            }
            
            mysqli_stmt_close($stmt);
            
            // Commit the transaction
            mysqli_commit($conn);
            
            // Redirect to refresh the page
            header("Location: pending.php?success=1");
            exit;
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = $e->getMessage();
        } finally {
            mysqli_autocommit($conn, TRUE);
        }
    }
}

// Process taking attendance for pending record
if (isset($_GET['success'])) {
    $success_message = "Pending record has been created successfully. You can now take attendance.";
}

// Add additional CSS
$additional_css = '
<style>
    .pending-card {
        border-left: 4px solid #ffc107;
        transition: all 0.2s;
    }
    .pending-card:hover {
        background-color: #fff8e1;
    }
    .missed-card {
        border-left: 4px solid #dc3545;
        transition: all 0.2s;
    }
    .missed-card:hover {
        background-color: #fff5f5;
    }
    .status-badge {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }
    .action-buttons {
        white-space: nowrap;
    }
</style>
';

// Include header
include '../includes/header.php';
?>

    <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Pending Attendance Records</h1>
        <a href="../index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i> <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Missed Classes (Today) -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-exclamation-circle"></i> Missed Classes (Today)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (mysqli_num_rows($missed_classes) === 0): ?>
                        <div class="p-4 text-center">
                            <i class="bi bi-check-circle-fill display-4 text-success"></i>
                            <p class="mt-3">No missed classes for today! You're all caught up.</p>
        </div>
        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php while ($class = mysqli_fetch_assoc($missed_classes)): ?>
                                <?php 
                                // Determine if class is past its time
                                $class_end_time = strtotime($current_date . ' ' . $class['time_end']);
                                $current_timestamp = strtotime('now');
                                $is_past_class = $class_end_time < $current_timestamp;
                                ?>
                                <div class="list-group-item missed-card p-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <h6 class="mb-1"><?= htmlspecialchars($class['subject_code']) ?> - <?= htmlspecialchars($class['subject_name']) ?></h6>
                                            <p class="mb-0 text-muted">
                                                <i class="bi bi-clock"></i> <?= date('h:i A', strtotime($class['time_start'])) ?> - <?= date('h:i A', strtotime($class['time_end'])) ?> | 
                                                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($class['location']) ?>
                                            </p>
                                            <div class="mt-1">
                                                <?php if ($is_past_class): ?>
                                                    <span class="badge bg-danger status-badge">
                                                        <i class="bi bi-exclamation-triangle"></i> Missed
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark status-badge">
                                                        <i class="bi bi-hourglass"></i> Upcoming
                                                    </span>
                                                <?php endif; ?>
                                                <span class="text-muted ms-2"><?= $class['enrolled_students'] ?> students</span>
                                            </div>
                                        </div>
                                        <div class="col-md-5 mt-2 mt-md-0 text-md-end action-buttons">
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="assignment_id" value="<?= $class['assignment_id'] ?>">
                                                <input type="hidden" name="subject_id" value="<?= $class['subject_id'] ?>">
                                                <button type="submit" name="create_pending" class="btn btn-warning btn-sm">
                                                    <i class="bi bi-plus-circle"></i> Create Pending
                                                </button>
                                            </form>
                                            <a href="take_attendance.php?subject_id=<?= $class['subject_id'] ?>&assignment_id=<?= $class['assignment_id'] ?>" class="btn btn-primary btn-sm ms-1">
                                                <i class="bi bi-clipboard-check"></i> Take Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Existing Pending Records -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-hourglass-split"></i> Pending Attendance Records
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (mysqli_num_rows($existing_pending) === 0): ?>
                        <div class="p-4 text-center">
                            <i class="bi bi-check-circle-fill display-4 text-success"></i>
                            <p class="mt-3">No pending attendance records. You're all caught up!</p>
                            </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php while ($record = mysqli_fetch_assoc($existing_pending)): ?>
                                <div class="list-group-item pending-card p-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <h6 class="mb-1"><?= htmlspecialchars($record['subject_code']) ?> - <?= htmlspecialchars($record['subject_name']) ?></h6>
                                            <p class="mb-0 text-muted">
                                                <i class="bi bi-calendar"></i> <?= date('M d, Y', strtotime($record['attendance_date'])) ?>
                                            </p>
                                            <div class="mt-1">
                                                <span class="badge bg-warning text-dark status-badge">
                                                    <i class="bi bi-hourglass-split"></i> Pending
                                </span>
                                                <?php if ($record['records_count'] > 0): ?>
                                                    <span class="badge bg-info text-dark status-badge">
                                                        <i class="bi bi-people"></i> <?= $record['records_count'] ?> records
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary status-badge">
                                                        <i class="bi bi-exclamation-circle"></i> No records
                                                    </span>
                                                <?php endif; ?>
                            </div>
                        </div>
                                        <div class="col-md-5 mt-2 mt-md-0 text-md-end action-buttons">
                                            <?php if ($record['records_count'] > 0): ?>
                                                <a href="view_attendance.php?attendance_id=<?= $record['attendance_id'] ?>" class="btn btn-info btn-sm">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <a href="take_attendance.php?subject_id=<?= $record['subject_id'] ?>&assignment_id=<?= $record['assignment_id'] ?>&attendance_id=<?= $record['attendance_id'] ?>" class="btn btn-primary btn-sm ms-1">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            <?php else: ?>
                                                <a href="take_attendance.php?subject_id=<?= $record['subject_id'] ?>&assignment_id=<?= $record['assignment_id'] ?>&date=<?= $record['attendance_date'] ?>" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-clipboard-check"></i> Take Attendance
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0">About Pending Attendance Records</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="card h-100 border-danger">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0">Missed Classes</h6>
                        </div>
                        <div class="card-body">
                            <p>These are classes scheduled for today where you haven't taken attendance yet.</p>
                            <p>Options:</p>
                            <ul>
                                <li><strong>Create Pending</strong> - Mark this class for later attendance entry</li>
                                <li><strong>Take Now</strong> - Take attendance immediately</li>
                </ul>
            </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100 border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">Pending Records</h6>
                        </div>
                        <div class="card-body">
                            <p>These are classes where attendance is marked as pending either because:</p>
                            <ul>
                                <li>You took attendance after the class ended</li>
                                <li>You manually marked a class as pending</li>
                            </ul>
                            <p>Pending records should be completed as soon as possible.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

<?php include '../includes/footer.php'; ?> 