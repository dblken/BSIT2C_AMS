<?php
session_start();
require_once '../../config/database.php';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../../login.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'];
$success_message = '';
$error_message = '';

// Handle form submission to update pending status
if (isset($_POST['update_pending'])) {
    try {
        $conn->begin_transaction();
        
        $attendance_id = $_POST['attendance_id'];
        
        // Update attendance record to mark as not pending
        $update_sql = "UPDATE attendance SET is_pending = 0 WHERE id = ? AND teacher_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $attendance_id, $teacher_id);
        
        if ($update_stmt->execute()) {
            // Log the action
            $log_sql = "INSERT INTO attendance_logs (attendance_id, log_type, log_message, created_at) 
                       VALUES (?, 'status_update', 'Marked as finalized by teacher', NOW())";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("i", $attendance_id);
            $log_stmt->execute();
            
            $conn->commit();
            $success_message = "Attendance record has been successfully finalized.";
        } else {
            throw new Exception("Failed to update attendance record.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get all pending attendance records for this teacher
$pending_query = "SELECT 
    a.id,
    a.attendance_date,
    s.subject_code,
    s.subject_name,
    a.assignment_id,
    a.subject_id,
    COUNT(ar.id) as student_count,
    SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
    SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
    TIMESTAMPDIFF(DAY, a.attendance_date, CURDATE()) as days_ago,
    DATE_FORMAT(a.created_at, '%h:%i %p') as created_time
    FROM attendance a
    JOIN subjects s ON a.subject_id = s.id
    LEFT JOIN attendance_records ar ON a.id = ar.attendance_id
    WHERE a.teacher_id = ? AND a.is_pending = 1
    GROUP BY a.id
    ORDER BY a.attendance_date DESC";

$stmt = $conn->prepare($pending_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$pending_records = $stmt->get_result();

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'present':
            return 'bg-success';
        case 'late':
            return 'bg-warning text-dark';
        case 'absent':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

// Helper function to get urgency class based on days ago
function getUrgencyClass($days_ago) {
    if ($days_ago > 7) {
        return 'text-danger';
    } elseif ($days_ago > 3) {
        return 'text-warning';
    } else {
        return 'text-muted';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Attendance Records - Teacher Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
<style>
        .pending-header {
            background-color: #fbbf24;
            color: #7c2d12;
            padding: 1.5rem;
            border-radius: 8px 8px 0 0;
        }
        
        .pending-container {
            background-color: white;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }
        
    .pending-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid #fbbf24;
        }
        
        .urgent-1 {
            border-left-color: #bfdbfe;
        }
        
        .urgent-2 {
            border-left-color: #fbbf24;
        }
        
        .urgent-3 {
            border-left-color: #ef4444;
        }
        
        .pending-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .stat-badge {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
            border-radius: 20px;
        }
        
        .btn-finalize {
            background-color: #15803d;
            color: white;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-finalize:hover {
            background-color: #166534;
            color: white;
            transform: translateY(-2px);
    }
</style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="mb-4">
            <a href="../attendance.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Attendance Dashboard
            </a>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
        <div class="pending-header mb-0">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><i class="bi bi-hourglass-split me-2"></i> Pending Attendance Records</h2>
                    <p class="mb-0">
                        These records are currently marked as pending and need to be reviewed and finalized.
                    </p>
                </div>
                <div class="text-end">
                    <span class="badge bg-light text-dark fs-5"><?php echo $pending_records->num_rows; ?> Pending</span>
                </div>
            </div>
        </div>
        
        <div class="pending-container p-4">
            <?php if ($pending_records->num_rows === 0): ?>
                <div class="text-center my-5 py-5">
                    <i class="bi bi-check-circle-fill text-success mb-3" style="font-size: 3rem;"></i>
                    <h4>No Pending Records</h4>
                    <p class="text-muted">All your attendance records have been finalized.</p>
                    <a href="../attendance.php" class="btn btn-primary mt-3">Return to Dashboard</a>
                            </div>
                    <?php else: ?>
                <div class="row mb-4">
                    <div class="col">
                        <p class="mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            <span class="text-muted">
                                Please review and finalize these records. Pending records are not included in official reports until finalized.
                            </span>
                        </p>
                            </div>
                        </div>
                
                <div class="row g-4">
                    <?php while ($record = $pending_records->fetch_assoc()): ?>
                        <?php 
                            $urgency_class = 'urgent-1';
                            if ($record['days_ago'] > 7) {
                                $urgency_class = 'urgent-3';
                            } elseif ($record['days_ago'] > 3) {
                                $urgency_class = 'urgent-2';
                            }
                        ?>
                        <div class="col-md-6">
                            <div class="card pending-card <?php echo $urgency_class; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($record['subject_code']); ?></h5>
                                            <p class="card-text mb-0"><?php echo htmlspecialchars($record['subject_name']); ?></p>
                                        </div>
                                        <div class="text-end">
                                            <div class="mb-1">
                                                <i class="bi bi-calendar3"></i> 
                                                <?php echo date('F d, Y', strtotime($record['attendance_date'])); ?>
                                    </div>
                                            <div class="<?php echo getUrgencyClass($record['days_ago']); ?> small">
                                                <i class="bi bi-clock-history"></i>
                                                <?php echo $record['days_ago']; ?> days ago
            </div>
        </div>
    </div>
    
                                    <div class="d-flex gap-2 flex-wrap mb-3">
                                        <span class="stat-badge bg-success">
                                            Present: <?php echo $record['present_count']; ?> 
                                        </span>
                                        <span class="stat-badge bg-warning text-dark">
                                            Late: <?php echo $record['late_count']; ?>
                                        </span>
                                        <span class="stat-badge bg-danger">
                                            Absent: <?php echo $record['absent_count']; ?>
                                        </span>
                                        <span class="stat-badge bg-secondary">
                                            Total: <?php echo $record['student_count']; ?>
                                        </span>
        </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <a href="take_attendance.php?subject_id=<?php echo $record['subject_id']; ?>&assignment_id=<?php echo $record['assignment_id']; ?>&date=<?php echo $record['attendance_date']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </a>
                                            <a href="view_attendance.php?subject_id=<?php echo $record['subject_id']; ?>&assignment_id=<?php echo $record['assignment_id']; ?>&attendance_id=<?php echo $record['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </div>
                                        <form method="POST" class="finalize-form">
                                            <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                            <input type="hidden" name="update_pending" value="1">
                                            <button type="button" class="btn btn-finalize btn-sm" data-bs-toggle="modal" data-bs-target="#finalizeModal" 
                                                    data-attendance-id="<?php echo $record['id']; ?>"
                                                    data-subject-code="<?php echo htmlspecialchars($record['subject_code']); ?>"
                                                    data-attendance-date="<?php echo date('F d, Y', strtotime($record['attendance_date'])); ?>">
                                                <i class="bi bi-check-circle"></i> Mark as Finalized
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Finalize Confirmation Modal -->
    <div class="modal fade" id="finalizeModal" tabindex="-1" aria-labelledby="finalizeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="finalizeModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Confirm Finalization
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to finalize this attendance record? This action cannot be undone.</p>
                    <div class="alert alert-info">
                        <strong>Subject:</strong> <span id="modalSubjectCode"></span><br>
                        <strong>Date:</strong> <span id="modalAttendanceDate"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="confirmFinalize">
                        <i class="bi bi-check-circle me-2"></i>Yes, Finalize
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const finalizeModal = document.getElementById('finalizeModal');
            let currentForm = null;

            finalizeModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const subjectCode = button.getAttribute('data-subject-code');
                const attendanceDate = button.getAttribute('data-attendance-date');
                
                document.getElementById('modalSubjectCode').textContent = subjectCode;
                document.getElementById('modalAttendanceDate').textContent = attendanceDate;
                
                currentForm = button.closest('form');
            });

            document.getElementById('confirmFinalize').addEventListener('click', function() {
                if (currentForm) {
                    currentForm.submit();
                }
                bootstrap.Modal.getInstance(finalizeModal).hide();
            });
        });
    </script>
</body>
</html> 