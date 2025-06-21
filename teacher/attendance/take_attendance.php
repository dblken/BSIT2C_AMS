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

// Include functions
require_once '../../includes/functions.php';

// Get parameters
$teacher_id = $_SESSION['teacher_id'];
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$assignment_id = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : 0;
$current_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Variables
$error_message = '';
$success_message = '';
$students = [];
$override_confirmed = false;

// If assignment_id is 0, try to find a valid one for this subject
if ($assignment_id === 0 && $subject_id > 0) {
    $find_assignment_query = "
        SELECT a.id 
        FROM assignments a 
        WHERE a.subject_id = ? AND a.teacher_id = ? 
        LIMIT 1";
    
    $find_stmt = $conn->prepare($find_assignment_query);
    $find_stmt->bind_param("ii", $subject_id, $teacher_id);
    $find_stmt->execute();
    $find_result = $find_stmt->get_result();
    
    if ($find_result->num_rows > 0) {
        $assignment_row = $find_result->fetch_assoc();
        $assignment_id = $assignment_row['id'];
        
        // Redirect to the same page with the correct assignment_id
        header("Location: take_attendance.php?subject_id=$subject_id&assignment_id=$assignment_id&date=$current_date");
        exit;
    }
    
    $find_stmt->close();
}

// Check if assignment exists
if ($assignment_id > 0) {
    // Comprehensive validation for the assignment
    $validation_query = "
        SELECT a.id, a.subject_id, s.subject_code, s.subject_name,
               a.preferred_day, a.time_start, a.time_end, a.location
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.id
        WHERE a.id = ? 
        AND a.teacher_id = ?";
    
    $check_stmt = $conn->prepare($validation_query);
    $check_stmt->bind_param("ii", $assignment_id, $teacher_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        // Check if the assignment exists at all
        $exists_query = "SELECT id FROM assignments WHERE id = ?";
        $exists_stmt = $conn->prepare($exists_query);
        $exists_stmt->bind_param("i", $assignment_id);
        $exists_stmt->execute();
        $exists_result = $exists_stmt->get_result();
        
        if ($exists_result->num_rows === 0) {
            $error_message = "Error: The specified assignment (ID: $assignment_id) does not exist in the database. Please select a valid assignment.";
        } else {
            $error_message = "Error: You do not have permission to take attendance for this assignment or the assignment is no longer active.";
        }
        
        $assignment_id = 0; // Reset to prevent further errors
        $exists_stmt->close();
    } else {
        // Get the assignment details
        $assignment_data = $check_result->fetch_assoc();
        $subject_id = $assignment_data['subject_id']; // Set subject_id from the assignment
        $subject_code = $assignment_data['subject_code'];
        $subject_name = $assignment_data['subject_name'];
        $schedule = [
            'preferred_day' => $assignment_data['preferred_day'],
            'time_start' => $assignment_data['time_start'],
            'time_end' => $assignment_data['time_end'],
            'location' => $assignment_data['location']
        ];
    }
    
    $check_stmt->close();
}

// If no valid assignment was found and no error message has been set yet
if ($assignment_id === 0 && empty($error_message)) {
    $error_message = "Please select a valid assignment to take attendance.";
}

// If there's an error with the assignment, get a list of valid assignments for this teacher
if ($assignment_id === 0 && !empty($error_message)) {
    $valid_assignments_sql = "SELECT a.id, a.subject_id, s.subject_code, s.subject_name, a.preferred_day, a.time_start, a.time_end
                           FROM assignments a
                           JOIN subjects s ON a.subject_id = s.id
                           WHERE a.teacher_id = ?
                           ORDER BY s.subject_code";
    $valid_stmt = $conn->prepare($valid_assignments_sql);
    $valid_stmt->bind_param("i", $teacher_id);
    $valid_stmt->execute();
    $valid_result = $valid_stmt->get_result();
    
    $valid_assignments = [];
    while ($assignment = $valid_result->fetch_assoc()) {
        $valid_assignments[] = $assignment;
    }
    $valid_stmt->close();
}

// Determine if this is a past attendance record (for pending status)
$is_after_class = false;

// Check if the current date matches the preferred day of the week
$is_correct_day = false;
$day_name_for_date = date('l', strtotime($current_date));
$pending_required = false;
$current_day = strtolower($day_name_for_date);

if (!empty($schedule) && isset($schedule['preferred_day'])) {
    $preferred_day = $schedule['preferred_day'];
    
    // Check if preferred_day is JSON
    $preferred_days = @json_decode($preferred_day, true);
    if (is_array($preferred_days)) {
        // Multiple days format
        foreach ($preferred_days as $day) {
            if (strtolower(getDayName($day)) === $current_day) {
                $is_correct_day = true;
                break;
            }
        }
    } else {
        // Single day format
        $is_correct_day = (strtolower(getDayName($preferred_day)) === $current_day);
    }
    
    // If not the correct day, pending is required
    $pending_required = !$is_correct_day;
}

// Check if the override has been confirmed
if (isset($_POST['confirm_override']) && $_POST['confirm_override'] == '1') {
    $override_confirmed = true;
    $override_reason = isset($_POST['override_reason']) ? $_POST['override_reason'] : '';
    $other_reason = isset($_POST['other_reason']) ? $_POST['other_reason'] : '';
    
    // Store the override reason in the session for logging
    $_SESSION['override_reason'] = $override_reason === 'other' ? $other_reason : $override_reason;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    try {
        if (!isset($_POST['status']) || empty($_POST['status'])) {
            throw new Exception("No attendance status data received");
        }
        
        // Get data from form
        $status_data = $_POST['status'];
        $remarks_data = isset($_POST['remarks']) ? $_POST['remarks'] : [];
        $is_pending = isset($_POST['is_pending']) ? intval($_POST['is_pending']) : 0;
        
        // If it's not the correct day and pending is not set, enforce it
        if ($pending_required && $is_pending == 0) {
            $is_pending = 1;
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        // Step 1: Check if attendance record exists
        $check_sql = "SELECT id FROM attendance WHERE teacher_id = ? AND subject_id = ? AND assignment_id = ? AND attendance_date = ?";
        $check_stmt = $conn->prepare($check_sql);
        
        if (!$check_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $check_stmt->bind_param("iiis", $teacher_id, $subject_id, $assignment_id, $current_date);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        // Initialize attendance ID
        $attendance_id = 0;
        
        // If attendance exists, update it
        if ($check_result->num_rows > 0) {
            $row = $check_result->fetch_assoc();
            $attendance_id = $row['id'];
            
            // Update the pending status
            $update_pending_sql = "UPDATE attendance SET is_pending = ? WHERE id = ?";
            $update_pending_stmt = $conn->prepare($update_pending_sql);
            
            if (!$update_pending_stmt) {
                throw new Exception("Prepare failed for pending status update: " . $conn->error);
            }
            
            $update_pending_stmt->bind_param("ii", $is_pending, $attendance_id);
            $update_result = $update_pending_stmt->execute();
            
            if (!$update_result) {
                throw new Exception("Failed to update pending status: " . $update_pending_stmt->error);
            }
            
            $update_pending_stmt->close();
            
            // Delete existing records
            $delete_sql = "DELETE FROM attendance_records WHERE attendance_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            
            if (!$delete_stmt) {
                throw new Exception("Prepare failed for delete: " . $conn->error);
            }
            
            $delete_stmt->bind_param("i", $attendance_id);
            $delete_result = $delete_stmt->execute();
            
            if (!$delete_result) {
                throw new Exception("Failed to delete old records: " . $delete_stmt->error);
            }
            
            $delete_stmt->close();
        } else {
            // Create new attendance record
            $insert_sql = "INSERT INTO attendance (teacher_id, subject_id, assignment_id, attendance_date, is_pending, created_at) 
                          VALUES (?, ?, ?, ?, ?, NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            
            if (!$insert_stmt) {
                throw new Exception("Prepare failed for insert: " . $conn->error);
            }
            
            $insert_stmt->bind_param("iiisi", $teacher_id, $subject_id, $assignment_id, $current_date, $is_pending);
            $insert_result = $insert_stmt->execute();
            
            if (!$insert_result) {
                throw new Exception("Failed to create attendance: " . $insert_stmt->error);
            }
            
            $attendance_id = $conn->insert_id;
            $insert_stmt->close();
            
            // If this is a schedule override, log it
            if ($override_confirmed) {
                $override_reason = $_SESSION['override_reason'] ?? 'No reason provided';
                $log_sql = "INSERT INTO attendance_logs (attendance_id, log_type, log_message, created_at) 
                           VALUES (?, 'override', ?, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                
                if ($log_stmt) {
                    $log_message = "Schedule override: " . $override_reason;
                    $log_stmt->bind_param("is", $attendance_id, $log_message);
                    $log_stmt->execute();
                    $log_stmt->close();
                    
                    // Clear the session variable
                    unset($_SESSION['override_reason']);
                }
            }
        }
        
        $check_stmt->close();
        
        // Step 2: Insert new records for each student
        $student_count = 0;
        $record_sql = "INSERT INTO attendance_records (attendance_id, student_id, status, remarks, created_at) 
                      VALUES (?, ?, ?, ?, NOW())";
        $record_stmt = $conn->prepare($record_sql);
        
        if (!$record_stmt) {
            throw new Exception("Prepare failed for record: " . $conn->error);
        }
        
        foreach ($status_data as $student_id => $status) {
            $student_id = intval($student_id);
            $remarks = isset($remarks_data[$student_id]) ? $remarks_data[$student_id] : '';
            
            $record_stmt->bind_param("iiss", $attendance_id, $student_id, $status, $remarks);
            $record_result = $record_stmt->execute();
            
            if (!$record_result) {
                throw new Exception("Failed to save record for student $student_id: " . $record_stmt->error);
            }
            
            $student_count++;
        }
        
        $record_stmt->close();
        
        // Commit transaction
        $commit_result = $conn->commit();
        
        if (!$commit_result) {
            throw new Exception("Failed to commit transaction: " . $conn->error);
        }
        
        $success_message = "Attendance for $student_count students has been saved successfully.";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get basic information
try {
    // Get subject info
    $subject_query = "SELECT s.subject_code, s.subject_name 
                     FROM subjects s 
                     WHERE s.id = ?";
    $stmt = $conn->prepare($subject_query);
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $subject_result = $stmt->get_result();
    
    if ($subject_result->num_rows > 0) {
        $subject_data = $subject_result->fetch_assoc();
        $subject_code = $subject_data['subject_code'];
        $subject_name = $subject_data['subject_name'];
    } else {
        $subject_code = "Unknown";
        $subject_name = "Unknown Subject";
    }
    
    $stmt->close();
    
    // Get students enrolled in the subject
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
                      GROUP BY
                          s.id
                      ORDER BY 
                          s.last_name, s.first_name";
    
    $stmt = $conn->prepare($students_query);
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $students_result = $stmt->get_result();
    
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
    
    $stmt->close();
    
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
    
    $stmt = $conn->prepare($existing_query);
    $stmt->bind_param("iiis", $teacher_id, $subject_id, $assignment_id, $current_date);
    $stmt->execute();
    $existing_result = $stmt->get_result();
    
    while ($row = $existing_result->fetch_assoc()) {
        if ($row['student_id']) {
            $existing_attendance[$row['student_id']] = [
                'status' => $row['status'],
                'remarks' => $row['remarks']
            ];
        }
    }
    
    $stmt->close();
    
    // Get schedule information
    $schedule_query = "SELECT 
                          a.preferred_day,
                          a.time_start,
                          a.time_end,
                          a.location
                        FROM 
                          assignments a
                        WHERE 
                          a.id = ? AND a.teacher_id = ?";
    
    $stmt = $conn->prepare($schedule_query);
    $stmt->bind_param("ii", $assignment_id, $teacher_id);
    $stmt->execute();
    $schedule_result = $stmt->get_result();
    
    if ($schedule_result->num_rows > 0) {
        $schedule = $schedule_result->fetch_assoc();
    } else {
        $schedule = [
            'preferred_day' => 'Unknown',
            'time_start' => '00:00:00',
            'time_end' => '00:00:00',
            'location' => 'Unknown'
        ];
    }
    
    $stmt->close();
    
    // If no students are found, check if we need to use the timetable-based query
    if (count($students) === 0) {
        // Try alternative query using timetable
        $alt_students_query = "SELECT 
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
                              GROUP BY 
                                  s.id
                              ORDER BY 
                                  s.last_name, s.first_name";
        
        $stmt = $conn->prepare($alt_students_query);
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $alt_students_result = $stmt->get_result();
        
        if ($alt_students_result->num_rows > 0) {
            $students = []; // Reset students array
            while ($row = $alt_students_result->fetch_assoc()) {
                $students[] = $row;
            }
        }
        
        $stmt->close();
    }
    
    // If still no students, try directly from the students table with student_subject relationship
    if (count($students) === 0) {
        // Check if there's a student_subjects table
        $check_table = $conn->query("SHOW TABLES LIKE 'student_subjects'");
        
        if ($check_table->num_rows > 0) {
            $direct_query = "SELECT 
                                s.id as student_id,
                                s.student_id as student_number,
                                CONCAT(s.last_name, ', ', s.first_name) as student_name
                             FROM 
                                students s
                             JOIN 
                                student_subjects ss ON s.id = ss.student_id
                             WHERE 
                                ss.subject_id = ?
                             ORDER BY 
                                s.last_name, s.first_name";
            
            $stmt = $conn->prepare($direct_query);
            $stmt->bind_param("i", $subject_id);
            $stmt->execute();
            $direct_result = $stmt->get_result();
            
            if ($direct_result->num_rows > 0) {
                $students = []; // Reset students array
                while ($row = $direct_result->fetch_assoc()) {
                    $students[] = $row;
                }
            }
            
            $stmt->close();
        }
    }
} catch (Exception $e) {
    $error_message = "Error loading data: " . $e->getMessage();
}

// Format time
function formatTime($time) {
    return date('h:i A', strtotime($time));
}

// Get status badge class
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Attendance - <?php echo htmlspecialchars($subject_code); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        .attendance-header {
            background-color: #021f3f;
            color: white;
            padding: 1.5rem;
            border-radius: 8px 8px 0 0;
        }
        
        .attendance-form {
            background-color: white;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }
        
        .input-date {
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            width: 100%;
        }
        
        .btn-submit {
            background-color: #021f3f;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            background-color: #01152b;
            transform: translateY(-2px);
        }
        
        .status-badge {
            font-weight: 500;
            padding: 0.4rem 0.75rem;
            border-radius: 30px;
        }
        
        .remarks-input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            resize: vertical;
        }
        
        .student-row {
            transition: background-color 0.2s ease;
        }
        
        .student-row:hover {
            background-color: #f8fafc;
        }
        
        .status-box {
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s ease;
            padding: 0.5rem;
            border-radius: 4px;
            font-weight: 500;
            text-align: center;
        }
        
        .status-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .status-box.selected {
            border-color: #021f3f;
            box-shadow: 0 0 0 2px rgba(2, 31, 63, 0.3);
            transform: scale(1.05);
        }
        
        .status-present {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-late {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-absent {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .card-header.bg-primary {
            background-color: #021f3f !important;
        }
        
        .btn-warning {
            background-color: #021f3f;
            border-color: #021f3f;
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #01152b;
            border-color: #01152b;
            color: white;
        }
        
        .btn-outline-warning {
            color: #021f3f;
            border-color: #021f3f;
        }
        
        .btn-outline-warning:hover {
            background-color: #021f3f;
            border-color: #021f3f;
            color: white;
        }
        
        .alert-warning {
            background-color: rgba(2, 31, 63, 0.15);
            border-color: rgba(2, 31, 63, 0.3);
            color: #021f3f;
        }
        
        .modal-header.bg-warning {
            background-color: #021f3f !important;
            color: white !important;
        }
        
        /* Bootstrap override for primary color */
        :root {
            --bs-primary: #021f3f;
            --bs-primary-rgb: 2, 31, 63;
        }
        
        .form-check-input:checked {
            background-color: #021f3f;
            border-color: #021f3f;
        }
        
        .form-control:focus {
            border-color: #021f3f;
            box-shadow: 0 0 0 0.25rem rgba(2, 31, 63, 0.25);
        }
        
        a {
            color: #021f3f;
        }
        
        a:hover {
            color: #01152b;
        }
        
        .page-link {
            color: #021f3f;
        }
        
        .page-item.active .page-link {
            background-color: #021f3f;
            border-color: #021f3f;
        }
        
        .text-primary {
            color: #021f3f !important;
        }
        
        .border-primary {
            border-color: #021f3f !important;
        }
        
        .bg-primary {
            background-color: #021f3f !important;
        }
        
        /* Status dropdown styling */
        .status-dropdown {
            font-weight: 500;
            border: 2px solid #e2e8f0;
            transition: all 0.2s ease;
            cursor: pointer;
            width: auto;
            min-width: 120px;
            max-width: 160px;
            padding: 0.375rem 0.75rem;
            padding-right: 2.25rem;
            font-size: 0.9rem;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
        }
        
        .status-dropdown:focus {
            box-shadow: 0 0 0 0.25rem rgba(2, 31, 63, 0.25);
            border-color: #021f3f;
        }
        
        .status-dropdown option {
            font-weight: 500;
            background-color: white;
            color: #333;
            padding: 8px 12px;
        }
        
        /* When background color is applied to select */
        .status-dropdown.bg-success,
        .status-dropdown.bg-danger {
            color: white !important;
        }
        
        .status-dropdown.bg-warning {
            color: #333 !important;
        }
        
        .btn-primary:hover {
            background-color: #053469 !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(2, 31, 63, 0.15);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
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
            
            <?php if (isset($valid_assignments) && !empty($valid_assignments)): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-list-check me-2"></i> Available Assignments</h5>
                </div>
                <div class="card-body">
                    <p>Please select one of your valid assignments from the list below:</p>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Subject</th>
                                    <th>Schedule</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($valid_assignments as $assignment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($assignment['subject_code']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($assignment['subject_name']); ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        $day_display = formatDays($assignment['preferred_day']);
                                        echo htmlspecialchars($day_display); 
                                        ?><br>
                                        <small class="text-muted">
                                            <?php echo formatTime($assignment['time_start']); ?> - 
                                            <?php echo formatTime($assignment['time_end']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <a href="take_attendance.php?subject_id=<?php echo $assignment['subject_id']; ?>&assignment_id=<?php echo $assignment['id']; ?>&date=<?php echo $current_date; ?>" 
                                           class="btn btn-warning btn-sm">
                                            <i class="bi bi-clipboard-check me-1"></i> Take Attendance
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if ($pending_required && !$override_confirmed): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i> Attendance Date Warning</h5>
            <p>The selected date (<?php echo date('F j, Y', strtotime($current_date)); ?>, <?php echo $day_name_for_date; ?>) 
               does not match the scheduled day(s) for this class 
               (<?php 
                 $preferred_days = @json_decode($schedule['preferred_day'], true);
                 if (is_array($preferred_days)) {
                     echo implode(', ', $preferred_days);
                 } else {
                     echo getDayName($schedule['preferred_day']);
                 }
               ?>).</p>
            <hr>
            <p class="mb-0">You can still take attendance, but it will be marked as <strong>pending</strong>. 
               This indicates that the attendance was taken outside of the regular schedule.</p>
            
            <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#overrideModal">
                <i class="bi bi-calendar-check me-1"></i> Proceed with Pending Attendance
            </button>
        </div>
        
        <!-- Override Confirmation Modal -->
        <div class="modal fade" id="overrideModal" tabindex="-1" aria-labelledby="overrideModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form action="take_attendance.php?subject_id=<?php echo $subject_id; ?>&assignment_id=<?php echo $assignment_id; ?>&date=<?php echo $current_date; ?>" method="POST">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title" id="overrideModalLabel">Confirm Pending Attendance</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Please select a reason for taking attendance on a non-scheduled day:</p>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="override_reason" id="makeup_class" value="makeup_class" checked>
                                <label class="form-check-label" for="makeup_class">
                                    Make-up Class
                                </label>
                            </div>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="override_reason" id="schedule_change" value="schedule_change">
                                <label class="form-check-label" for="schedule_change">
                                    Temporary Schedule Change
                                </label>
                            </div>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="override_reason" id="special_session" value="special_session">
                                <label class="form-check-label" for="special_session">
                                    Special Session (exam, review, etc.)
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="override_reason" id="other_reason" value="other">
                                <label class="form-check-label" for="other_reason">
                                    Other Reason
                                </label>
                            </div>
                            
                            <div class="mb-3">
                                <label for="other_reason_text" class="form-label">If Other, please specify:</label>
                                <textarea class="form-control" id="other_reason_text" name="other_reason" rows="2"></textarea>
                            </div>
                            
                            <input type="hidden" name="confirm_override" value="1">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Confirm & Proceed</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="mb-4">
            <a href="../attendance.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Attendance Dashboard
            </a>
        </div>
        
        <div class="attendance-header mb-0">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2 class="mb-1"><?php echo htmlspecialchars($subject_code); ?></h2>
                    <p class="mb-0"><?php echo htmlspecialchars($subject_name); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="d-flex flex-column align-items-md-end">
                        <div class="mb-1">
                            <i class="bi bi-calendar-event"></i> 
                            <?php 
                            $day_display = formatDays($schedule['preferred_day']);
                            echo htmlspecialchars($day_display);
                            ?>
                        </div>
                        <div class="mb-1">
                            <i class="bi bi-clock"></i> 
                            <?php echo formatTime($schedule['time_start']); ?> - <?php echo formatTime($schedule['time_end']); ?>
                        </div>
                        <div>
                            <i class="bi bi-geo-alt"></i> 
                            <?php echo htmlspecialchars($schedule['location']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <form action="take_attendance.php?subject_id=<?php echo $subject_id; ?>&assignment_id=<?php echo $assignment_id; ?>&date=<?php echo $current_date; ?>" method="POST" class="attendance-form p-4">
            <div class="row mb-4">
                <div class="col-md-4">
                    <label for="attendance_date" class="form-label fw-bold">Attendance Date</label>
                    <input type="date" id="attendance_date" name="attendance_date" value="<?php echo $current_date; ?>" class="form-control input-date" 
                           onchange="window.location.href='take_attendance.php?subject_id=<?php echo $subject_id; ?>&assignment_id=<?php echo $assignment_id; ?>&date=' + this.value">
                </div>
            </div>
            
            <?php if (count($students) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 25%;">Student ID</th>
                                <th style="width: 25%;">Student Name</th>
                                <th style="width: 25%;">Attendance Status</th>
                                <th style="width: 25%;">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <?php 
                                    $student_id = $student['student_id'];
                                    $current_status = isset($existing_attendance[$student_id]) ? 
                                                    $existing_attendance[$student_id]['status'] : '';
                                    $current_remarks = isset($existing_attendance[$student_id]) ? 
                                                    $existing_attendance[$student_id]['remarks'] : '';
                                ?>
                                <tr class="student-row">
                                    <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                    <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                    <td>
                                        <select name="status[<?php echo $student_id; ?>]" class="form-select status-dropdown" data-student-id="<?php echo $student_id; ?>">
                                            <option value="" <?php echo empty($current_status) ? 'selected' : ''; ?>>Select Status</option>
                                            <option value="present" <?php echo $current_status === 'present' ? 'selected' : ''; ?>>Present</option>
                                            <option value="late" <?php echo $current_status === 'late' ? 'selected' : ''; ?>>Late</option>
                                            <option value="absent" <?php echo $current_status === 'absent' ? 'selected' : ''; ?>>Absent</option>
                                        </select>
                                    </td>
                                    <td>
                                        <textarea name="remarks[<?php echo $student_id; ?>]" class="remarks-input" 
                                                placeholder="Optional remarks..."><?php echo htmlspecialchars($current_remarks); ?></textarea>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($pending_required || $override_confirmed): ?>
                <div class="alert alert-warning mt-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
                        <div>
                            <strong>Pending Attendance:</strong> This attendance will be marked as pending because it's being taken on a non-scheduled day.
                            <?php if ($override_confirmed && isset($_SESSION['override_reason'])): ?>
                            <br><strong>Reason:</strong> <?php echo htmlspecialchars($_SESSION['override_reason']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="is_pending" value="1">
                <?php else: ?>
                <input type="hidden" name="is_pending" value="0">
                <?php endif; ?>
                
                <div class="text-end mt-4">
                    <button type="submit" name="submit_attendance" class="btn btn-primary btn-lg px-5 py-2 d-inline-flex align-items-center gap-2" style="background-color: #021F3F; border: none; transition: all 0.3s ease;">
                        <i class="bi bi-save2-fill"></i>
                        <span>Save Attendance</span>
                    </button>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> No students found enrolled in this subject.
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Validation Modal -->
    <div class="modal fade" id="validationModal" tabindex="-1" aria-labelledby="validationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="validationModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Validation Error
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Please select a status for all students before submitting.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.attendance-form');
            const validationModal = new bootstrap.Modal(document.getElementById('validationModal'));
            
            form?.addEventListener('submit', function(e) {
                const statusDropdowns = document.querySelectorAll('.status-dropdown');
                let hasEmptyStatus = false;
                
                statusDropdowns.forEach(dropdown => {
                    if (!dropdown.value) {
                        hasEmptyStatus = true;
                    }
                });
                
                if (hasEmptyStatus) {
                    e.preventDefault();
                    validationModal.show();
                }
            });
            
            // Update dropdown styling based on selection
            document.querySelectorAll('.status-dropdown').forEach(dropdown => {
                function updateDropdownStyle() {
                    dropdown.classList.remove('bg-success', 'bg-warning', 'bg-danger', 'text-white');
                    
                    switch(dropdown.value) {
                        case 'present':
                            dropdown.classList.add('bg-success', 'text-white');
                            break;
                        case 'late':
                            dropdown.classList.add('bg-warning');
                            break;
                        case 'absent':
                            dropdown.classList.add('bg-danger', 'text-white');
                            break;
                    }
                }
                
                updateDropdownStyle();
                dropdown.addEventListener('change', updateDropdownStyle);
            });
        });
    </script>
</body>
</html> 