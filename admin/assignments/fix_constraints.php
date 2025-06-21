<?php
// Include common files
require_once '../includes/admin_header.php';
require_once '../../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// Initialize variables
$messages = [];
$errors = [];
$invalid_attendance = [];
$has_fixed = false;
$removed_count = 0;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_constraints'])) {
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // 1. Find records with invalid assignment_id
        $find_invalid_sql = "
            SELECT a.id, a.teacher_id, a.subject_id, a.assignment_id, a.attendance_date,
                   CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                   s.subject_code
            FROM attendance a
            LEFT JOIN assignments ass ON a.assignment_id = ass.id
            LEFT JOIN teachers t ON a.teacher_id = t.id
            LEFT JOIN subjects s ON a.subject_id = s.id
            WHERE ass.id IS NULL";
        
        $result = $conn->query($find_invalid_sql);
        $invalid_count = $result->num_rows;
        
        if ($invalid_count > 0) {
            // Store invalid records for display
            while ($row = $result->fetch_assoc()) {
                $invalid_attendance[] = $row;
            }
            
            // Get IDs of invalid records
            $invalid_ids = array_column($invalid_attendance, 'id');
            
            // Delete related attendance records first
            foreach ($invalid_ids as $attendance_id) {
                $delete_records_sql = "DELETE FROM attendance_records WHERE attendance_id = ?";
                $stmt = $conn->prepare($delete_records_sql);
                $stmt->bind_param("i", $attendance_id);
                $stmt->execute();
            }
            
            // Delete invalid attendance records
            $placeholders = implode(',', array_fill(0, count($invalid_ids), '?'));
            $delete_attendance_sql = "DELETE FROM attendance WHERE id IN ($placeholders)";
            $stmt = $conn->prepare($delete_attendance_sql);
            
            // Bind all parameters
            $types = str_repeat('i', count($invalid_ids));
            $stmt->bind_param($types, ...$invalid_ids);
            $stmt->execute();
            
            $removed_count = $invalid_count;
            $messages[] = "Successfully removed $removed_count invalid attendance records.";
            $has_fixed = true;
        } else {
            $messages[] = "No invalid attendance records found. Your database is clean!";
        }
        
        // Commit transaction
        $conn->commit();
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $errors[] = "Error: " . $e->getMessage();
    }
}

// Get current state of attendance records
$current_stats_sql = "
    SELECT COUNT(*) as total_attendance,
           (SELECT COUNT(*) FROM attendance_records) as total_records
    FROM attendance";
$stats_result = $conn->query($current_stats_sql);
$stats = $stats_result->fetch_assoc();

// Find current invalid records for display
$current_invalid_sql = "
    SELECT a.id, a.teacher_id, a.subject_id, a.assignment_id, a.attendance_date,
           CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
           s.subject_code
    FROM attendance a
    LEFT JOIN assignments ass ON a.assignment_id = ass.id
    LEFT JOIN teachers t ON a.teacher_id = t.id
    LEFT JOIN subjects s ON a.subject_id = s.id
    WHERE ass.id IS NULL
    LIMIT 100";

$current_invalid_result = $conn->query($current_invalid_sql);
$current_invalid_count = $current_invalid_result->num_rows;
$current_invalid_records = [];

if ($current_invalid_count > 0) {
    while ($row = $current_invalid_result->fetch_assoc()) {
        $current_invalid_records[] = $row;
    }
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Fix Database Constraints</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="index.php">Assignments</a></li>
        <li class="breadcrumb-item active">Fix Constraints</li>
    </ol>
    
    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-xl-12">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-database me-1"></i>
                    Database Integrity Tool
                </div>
                <div class="card-body">
                    <p>This tool helps fix foreign key constraint issues in the attendance system. It will identify and remove attendance records that reference non-existent assignments.</p>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-primary text-white mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Attendance Records</h5>
                                    <h2 class="display-4"><?php echo number_format($stats['total_attendance']); ?></h2>
                                </div>
                                <div class="card-footer d-flex align-items-center justify-content-between">
                                    <span>Total attendance entries</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-warning text-white mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Invalid Records</h5>
                                    <h2 class="display-4"><?php echo number_format($current_invalid_count); ?></h2>
                                </div>
                                <div class="card-footer d-flex align-items-center justify-content-between">
                                    <span><?php echo $current_invalid_count > 0 ? 'Records with invalid assignments' : 'No invalid records found'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($current_invalid_count > 0): ?>
                        <div class="alert alert-warning" role="alert">
                            <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i> Foreign Key Constraint Issues Detected</h4>
                            <p>Found <?php echo $current_invalid_count; ?> attendance records that reference non-existent assignments. These records may cause errors when teachers try to access attendance pages.</p>
                            <hr>
                            <p class="mb-0">Use the button below to automatically fix these issues by removing the invalid records.</p>
                        </div>
                        
                        <form method="POST" action="">
                            <button type="submit" name="fix_constraints" class="btn btn-danger btn-lg mb-4">
                                <i class="fas fa-tools me-2"></i> Fix Constraint Issues
                            </button>
                        </form>
                        
                        <h5 class="mt-4 mb-3">Invalid Records (<?php echo $current_invalid_count; ?>)</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Teacher</th>
                                        <th>Subject</th>
                                        <th>Missing Assignment ID</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($current_invalid_records as $record): ?>
                                        <tr>
                                            <td><?php echo $record['id']; ?></td>
                                            <td><?php echo htmlspecialchars($record['teacher_name'] ?? 'Unknown'); ?></td>
                                            <td><?php echo htmlspecialchars($record['subject_code'] ?? 'Unknown'); ?></td>
                                            <td><?php echo $record['assignment_id']; ?></td>
                                            <td><?php echo $record['attendance_date']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success" role="alert">
                            <h4 class="alert-heading"><i class="fas fa-check-circle me-2"></i> No Issues Found</h4>
                            <p>Your database is in good shape! There are no attendance records with invalid assignment references.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($has_fixed && $removed_count > 0): ?>
                        <div class="mt-4">
                            <h5>Previously Removed Records (<?php echo count($invalid_attendance); ?>)</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>ID</th>
                                            <th>Teacher</th>
                                            <th>Subject</th>
                                            <th>Assignment ID</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invalid_attendance as $record): ?>
                                            <tr>
                                                <td><?php echo $record['id']; ?></td>
                                                <td><?php echo htmlspecialchars($record['teacher_name'] ?? 'Unknown'); ?></td>
                                                <td><?php echo htmlspecialchars($record['subject_code'] ?? 'Unknown'); ?></td>
                                                <td><?php echo $record['assignment_id']; ?></td>
                                                <td><?php echo $record['attendance_date']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../includes/admin_footer.php';
?> 