<?php
// Start session
session_start();

// Check login
if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Include database connection
require_once '../../config/database.php';

// Get parameters
$teacher_id = $_SESSION['teacher_id'];
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$assignment_id = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : 0;
$current_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Variables
$error_message = '';
$success_message = '';
$students = [];

// Determine if this is a past attendance record (for pending status)
$is_after_class = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['status']) || empty($_POST['status'])) {
            throw new Exception("No attendance status data received");
        }
        
        // Get data from form
        $status_data = $_POST['status'];
        $remarks_data = isset($_POST['remarks']) ? $_POST['remarks'] : [];
        
        // Start transaction
        mysqli_autocommit($conn, FALSE);
        
        // Step 1: Check if attendance record exists
        $check_sql = "SELECT id FROM attendance WHERE teacher_id = ? AND subject_id = ? AND assignment_id = ? AND attendance_date = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        
        if (!$check_stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($check_stmt, "iiis", $teacher_id, $subject_id, $assignment_id, $current_date);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        // Initialize attendance ID
        $attendance_id = 0;
        
        // If attendance exists, update it
        if (mysqli_num_rows($check_result) > 0) {
            $row = mysqli_fetch_assoc($check_result);
            $attendance_id = $row['id'];
            
            // Delete existing records
            $delete_sql = "DELETE FROM attendance_records WHERE attendance_id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_sql);
            
            if (!$delete_stmt) {
                throw new Exception("Prepare failed for delete: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($delete_stmt, "i", $attendance_id);
            $delete_result = mysqli_stmt_execute($delete_stmt);
            
            if (!$delete_result) {
                throw new Exception("Failed to delete old records: " . mysqli_stmt_error($delete_stmt));
            }
            
            mysqli_stmt_close($delete_stmt);
        } else {
            // Create new attendance record
            $insert_sql = "INSERT INTO attendance (teacher_id, subject_id, assignment_id, attendance_date, created_at) 
                          VALUES (?, ?, ?, ?, NOW())";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            
            if (!$insert_stmt) {
                throw new Exception("Prepare failed for insert: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($insert_stmt, "iiis", $teacher_id, $subject_id, $assignment_id, $current_date);
            $insert_result = mysqli_stmt_execute($insert_stmt);
            
            if (!$insert_result) {
                throw new Exception("Failed to create attendance: " . mysqli_stmt_error($insert_stmt));
            }
            
            $attendance_id = mysqli_insert_id($conn);
            mysqli_stmt_close($insert_stmt);
        }
        
        mysqli_stmt_close($check_stmt);
        
        // Step 2: Insert new records for each student
        $student_count = 0;
        $record_sql = "INSERT INTO attendance_records (attendance_id, student_id, status, remarks, created_at) 
                      VALUES (?, ?, ?, ?, NOW())";
        
        foreach ($status_data as $student_id => $status) {
            $student_id = intval($student_id);
            $remarks = isset($remarks_data[$student_id]) ? $remarks_data[$student_id] : '';
            
            $record_stmt = mysqli_prepare($conn, $record_sql);
            
            if (!$record_stmt) {
                throw new Exception("Prepare failed for record: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($record_stmt, "iiss", $attendance_id, $student_id, $status, $remarks);
            $record_result = mysqli_stmt_execute($record_stmt);
            
            if (!$record_result) {
                throw new Exception("Failed to save record for student $student_id: " . mysqli_stmt_error($record_stmt));
            }
            
            $student_count++;
            mysqli_stmt_close($record_stmt);
        }
        
        // Commit transaction
        $commit_result = mysqli_commit($conn);
        
        if (!$commit_result) {
            throw new Exception("Failed to commit transaction: " . mysqli_error($conn));
        }
        
        $success_message = "Attendance for $student_count students has been saved successfully.";
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $error_message = "Error: " . $e->getMessage();
    } finally {
        // Re-enable autocommit
        mysqli_autocommit($conn, TRUE);
    }
}

// Get basic information
try {
    // Get subject info
    $subject_query = "SELECT s.subject_code, s.subject_name 
                     FROM subjects s 
                     WHERE s.id = ?";
    $stmt = mysqli_prepare($conn, $subject_query);
    mysqli_stmt_bind_param($stmt, "i", $subject_id);
    mysqli_stmt_execute($stmt);
    $subject_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($subject_result) > 0) {
        $subject_data = mysqli_fetch_assoc($subject_result);
        $subject_code = $subject_data['subject_code'];
        $subject_name = $subject_data['subject_name'];
    } else {
        $subject_code = "Unknown";
        $subject_name = "Unknown Subject";
    }
    
    mysqli_stmt_close($stmt);
    
    // Get students
    $students_query = "SELECT 
                          s.id as student_id,
                          s.student_id as student_number,
                          CONCAT(s.last_name, ', ', s.first_name) as student_name
                      FROM 
                          enrollments e
                      JOIN 
                          students s ON e.student_id = s.id
                      JOIN 
                          timetable t ON e.schedule_id = t.id
                      WHERE 
                          t.subject_id = ?
                      ORDER BY 
                          s.last_name, s.first_name";
    
    $stmt = mysqli_prepare($conn, $students_query);
    mysqli_stmt_bind_param($stmt, "i", $subject_id);
    mysqli_stmt_execute($stmt);
    $students_result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($students_result)) {
        $students[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    
    // Get existing attendance
    $existing_attendance = [];
    
    $existing_query = "SELECT 
                         a.id as attendance_id,
                         ar.student_id,
                         ar.status,
                         ar.remarks
                       FROM 
                         attendance a
                       LEFT JOIN 
                         attendance_records ar ON a.id = ar.attendance_id
                       WHERE 
                         a.teacher_id = ? AND 
                         a.subject_id = ? AND 
                         a.assignment_id = ? AND 
                         a.attendance_date = ?";
    
    $stmt = mysqli_prepare($conn, $existing_query);
    mysqli_stmt_bind_param($stmt, "iiis", $teacher_id, $subject_id, $assignment_id, $current_date);
    mysqli_stmt_execute($stmt);
    $existing_result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($existing_result)) {
        if ($row['student_id']) {
            $existing_attendance[$row['student_id']] = [
                'status' => $row['status'],
                'remarks' => $row['remarks']
            ];
        }
    }
    
    mysqli_stmt_close($stmt);
    
    // Reload existing attendance after form submission
    if ($success_message) {
        // Clear existing attendance data and reload it
        $existing_attendance = [];
        
        $reload_query = "SELECT 
                             ar.student_id,
                             ar.status,
                             ar.remarks
                           FROM 
                             attendance a
                           JOIN 
                             attendance_records ar ON a.id = ar.attendance_id
                           WHERE 
                             a.teacher_id = ? AND 
                             a.subject_id = ? AND 
                             a.assignment_id = ? AND 
                             a.attendance_date = ?";
        
        $stmt = mysqli_prepare($conn, $reload_query);
        mysqli_stmt_bind_param($stmt, "iiis", $teacher_id, $subject_id, $assignment_id, $current_date);
        mysqli_stmt_execute($stmt);
        $reload_result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($reload_result)) {
            $existing_attendance[$row['student_id']] = [
                'status' => $row['status'],
                'remarks' => $row['remarks']
            ];
        }
        
        mysqli_stmt_close($stmt);
    }
    
    // Check if current time is after class end time
    $class_end_time = strtotime($current_date . ' ' . $assignment['time_end']);
    $current_timestamp = strtotime('now');
    $is_after_class = $current_timestamp > $class_end_time;

    // For dates other than today, always mark as pending
    if ($current_date != date('Y-m-d')) {
        $is_after_class = true;
    }
    
} catch (Exception $e) {
    $error_message = "Error loading data: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Attendance - <?= htmlspecialchars($subject_code) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        .student-row:hover { background-color: #f8f9fa; }
        .status-radio {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .status-label {
            cursor: pointer;
            padding: 3px 8px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .status-label.present {
            background-color: #d1e7dd;
        }
        .status-label.late {
            background-color: #fff3cd;
        }
        .status-label.absent {
            background-color: #f8d7da;
        }
        .status-label:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">Take Attendance: <?= htmlspecialchars($subject_code) ?> - <?= htmlspecialchars($subject_name) ?></h1>
            <a href="../index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= $error_message ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i> <?= $success_message ?>
            </div>
            
            <div class="alert alert-info">
                <strong>Attendance Summary:</strong>
                <ul>
                    <?php 
                    $present_count = 0;
                    $late_count = 0;
                    $absent_count = 0;
                    
                    foreach ($_POST['status'] as $status) {
                        if ($status === 'present') $present_count++;
                        elseif ($status === 'late') $late_count++;
                        elseif ($status === 'absent') $absent_count++;
                    }
                    ?>
                    <li>Present: <?= $present_count ?> students</li>
                    <li>Late: <?= $late_count ?> students</li>
                    <li>Absent: <?= $absent_count ?> students</li>
                    <li>Total: <?= count($_POST['status']) ?> students</li>
                </ul>
                <p>Attendance was successfully recorded on <?= date('F d, Y', strtotime($current_date)) ?> at <?= date('h:i A') ?>.</p>
                <div class="mt-3">
                    <a href="../index.php" class="btn btn-primary me-2">
                        <i class="bi bi-speedometer2"></i> Return to Dashboard
                    </a>
                    <a href="history.php?subject_id=<?= $subject_id ?>" class="btn btn-info">
                        <i class="bi bi-clock-history"></i> View Attendance History
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between">
                    <div>Attendance for <?= date('F d, Y', strtotime($current_date)) ?></div>
                    <div><?= count($students) ?> students enrolled</div>
                </div>
            </div>
            <div class="card-body">
                <!-- Add this right after the attendance header -->
                <?php if ($current_date != date('Y-m-d')): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill"></i> You are recording attendance for a past date: <strong><?= date('F d, Y', strtotime($current_date)) ?></strong>. This will be marked as pending.
                    </div>
                    <input type="hidden" name="is_pending" value="1">
                <?php endif; ?>
                
                <!-- Simple form with basic functionality -->
                <form method="POST" action="" id="attendanceForm">
                    <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
                    <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
                    <input type="hidden" name="is_pending" value="<?= $is_after_class ? '1' : '0' ?>">
                    
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="40%">Student</th>
                                <th width="40%">Status</th>
                                <th width="20%">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No students enrolled in this class.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <tr class="student-row">
                                        <td>
                                            <strong><?= htmlspecialchars($student['student_name']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($student['student_number']) ?></small>
                                        </td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input status-radio" type="radio" 
                                                    name="status[<?= $student['student_id'] ?>]" 
                                                    id="present_<?= $student['student_id'] ?>" 
                                                    value="present" 
                                                    <?= (isset($existing_attendance[$student['student_id']]) && 
                                                        $existing_attendance[$student['student_id']]['status'] === 'present') ? 'checked' : 
                                                        (!isset($existing_attendance[$student['student_id']]) ? 'checked' : '') ?>>
                                                <label class="form-check-label status-label present" for="present_<?= $student['student_id'] ?>">Present</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input status-radio" type="radio" 
                                                    name="status[<?= $student['student_id'] ?>]" 
                                                    id="late_<?= $student['student_id'] ?>" 
                                                    value="late" 
                                                    <?= (isset($existing_attendance[$student['student_id']]) && 
                                                        $existing_attendance[$student['student_id']]['status'] === 'late') ? 'checked' : '' ?>>
                                                <label class="form-check-label status-label late" for="late_<?= $student['student_id'] ?>">Late</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input status-radio" type="radio" 
                                                    name="status[<?= $student['student_id'] ?>]" 
                                                    id="absent_<?= $student['student_id'] ?>" 
                                                    value="absent" 
                                                    <?= (isset($existing_attendance[$student['student_id']]) && 
                                                        $existing_attendance[$student['student_id']]['status'] === 'absent') ? 'checked' : '' ?>>
                                                <label class="form-check-label status-label absent" for="absent_<?= $student['student_id'] ?>">Absent</label>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" 
                                                name="remarks[<?= $student['student_id'] ?>]" 
                                                placeholder="Remarks" 
                                                value="<?= isset($existing_attendance[$student['student_id']]) ? 
                                                    htmlspecialchars($existing_attendance[$student['student_id']]['remarks']) : '' ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <div class="d-flex justify-content-end mt-3">
                        <a href="../index.php" class="btn btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-success" id="saveBtn">
                            <i class="bi bi-check-circle"></i> Save Attendance
                        </button>
                        <a href="history.php?subject_id=<?= $subject_id ?>" class="btn btn-info ms-2">
                            <i class="bi bi-clock-history"></i> View History
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
    <script>
        $(document).ready(function() {
            // Form submission loading state
            $('#attendanceForm').on('submit', function() {
                $('#saveBtn').html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...').prop('disabled', true);
            });
            
            // Status label highlight
            $('.status-label').click(function() {
                $(this).closest('tr').find('.status-radio').prop('checked', true);
            });
        });
    </script>
</body>
</html> 