<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM assignments")->fetch_assoc()['count']
];

// Get assignments with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$query = "SELECT a.*, t.first_name, t.last_name, s.subject_name, s.subject_code
          FROM assignments a
          JOIN teachers t ON a.teacher_id = t.id
          JOIN subjects s ON a.subject_id = s.id
          ORDER BY a.created_at DESC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Get total records for pagination
$total_records = $conn->query("SELECT COUNT(*) as count FROM assignments")->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);

include '../includes/admin_header.php';
?>

<style>
    :root {
        --primary-color: #021F3F;
        --secondary-color: #C8A77E;
        --primary-hover: #042b59;
        --secondary-hover: #b39268;
    }
    
    .icon-circle {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        font-size: 1.25rem;
    }
    
    .search-group {
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        border-radius: 0.375rem;
        overflow: hidden;
    }
    
    .search-group .form-control {
        border-right: none;
    }
    
    .search-group .btn {
        border-left: none;
    }
    
    .bg-gradient-primary-to-secondary {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    }
    
    .clickable-row {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .clickable-row:hover {
        background-color: #f8f9fa;
    }
    
    .text-primary {
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
    
    .badge.bg-primary {
        background-color: var(--primary-color) !important;
    }
    
    .icon-circle.bg-primary {
        background-color: var(--primary-color) !important;
    }
    
    .icon-circle.bg-success {
        background-color: var(--secondary-color) !important;
        color: white !important;
    }
    
    .icon-circle.bg-info {
        background-color: var(--primary-color) !important;
        opacity: 0.8;
    }
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-10">
            <!-- Page Header -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <h2 class="fw-bold text-primary mb-2">
                            <i class="fas fa-chalkboard-teacher me-2"></i> Assignment Management
                        </h2>
                        <p class="text-muted mb-0">Create, view and manage teacher-subject assignments</p>
                    </div>
                </div>
            </div>
            
            <!-- Stats Row -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-primary fw-bold mb-1">Total Assignments</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $stats['total']; ?></h3>
                                </div>
                                <div class="icon-circle bg-primary text-white">
                                    <i class="fas fa-tasks"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-success fw-bold mb-1">Active Subjects</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $conn->query("SELECT COUNT(DISTINCT subject_id) FROM assignments")->fetch_assoc()['COUNT(DISTINCT subject_id)']; ?></h3>
                                </div>
                                <div class="icon-circle bg-success text-white">
                                    <i class="fas fa-book"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-info fw-bold mb-1">Active Teachers</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $conn->query("SELECT COUNT(DISTINCT teacher_id) FROM assignments")->fetch_assoc()['COUNT(DISTINCT teacher_id)']; ?></h3>
                                </div>
                                <div class="icon-circle bg-info text-white">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                            </div>
                </div>
            </div>
        </div>
    </div>

            <!-- Assignments Table Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-gradient-primary-to-secondary p-4 text-white d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-list me-2"></i> Assignment List
                    </h5>
                    <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                        <i class="fas fa-plus me-2"></i> New Assignment
                    </button>
                </div>
                <div class="card-body p-4">
                    <!-- Search and Filter -->
                    <div class="row mb-4 align-items-center">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="input-group search-group">
                                <input type="text" id="searchAssignment" class="form-control" placeholder="Search by teacher, subject...">
                                <button class="btn btn-outline-secondary" type="button">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="assignmentsTable">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" width="20%">Teacher</th>
                                    <th scope="col" width="15%">Subject Code</th>
                                    <th scope="col" width="20%">Subject Name</th>
                                    <th scope="col" width="35%">Schedule Details</th>
                                    <th scope="col" width="10%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="icon-circle bg-light text-primary me-2" style="width: 40px; height: 40px; font-size: 1rem;">
                                                    <i class="fas fa-user-tie"></i>
                                                </div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-primary border"><?php echo htmlspecialchars($row['subject_code']); ?></span>
                                        </td>
                                        <td class="fw-medium"><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-calendar-alt text-muted me-2"></i>
                                                <span><?php echo formatDays($row['preferred_day']); ?></span>
                                            </div>
                                            <div class="small text-muted">
                                                <i class="fas fa-clock me-1"></i> 
                                                <?php echo date('h:i A', strtotime($row['time_start'])); ?> - 
                                                <?php echo date('h:i A', strtotime($row['time_end'])); ?>
                                            </div>
                                            <div class="small text-muted">
                                                <i class="far fa-calendar-check me-1"></i>
                                                <?php echo date('M j, Y', strtotime($row['month_from'])); ?> - 
                                                <?php echo date('M j, Y', strtotime($row['month_to'])); ?>
                                            </div>
                                            <div class="small text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($row['location']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-assignment" data-id="<?php echo $row['id']; ?>" title="Delete Assignment">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">No assignments found. Click the 'New Assignment' button to create one.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Assignment Modal -->
<div class="modal fade" id="addAssignmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-gradient-primary-to-secondary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-plus-circle me-2"></i> New Assignment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAssignmentForm">
                <div class="modal-body p-4">
                    <!-- Teacher Selection Section -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3 border-bottom pb-2"><i class="fas fa-user-tie me-2"></i>Select Teacher</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Select</th>
                                        <th>Teacher ID</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Current Load</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $teacher_query = "SELECT t.*, 
                                        (SELECT COUNT(*) FROM assignments a WHERE a.teacher_id = t.id) as current_load
                                        FROM teachers t 
                                        WHERE t.status = 'Active'
                                        ORDER BY t.last_name, t.first_name";
                                    $teacher_result = mysqli_query($conn, $teacher_query);
                                    while ($teacher = mysqli_fetch_assoc($teacher_result)) {
                                        echo "<tr>
                                                <td>
                                                    <input type='radio' name='teacher_id' value='{$teacher['id']}' 
                                                           class='form-check-input' required>
                                                </td>
                                                <td>{$teacher['teacher_id']}</td>
                                                <td>{$teacher['first_name']} {$teacher['last_name']}</td>
                                                <td>{$teacher['department']}</td>
                                                <td>{$teacher['current_load']} subjects</td>
                                            </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Subject Selection Section -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3 border-bottom pb-2"><i class="fas fa-book me-2"></i>Select Subject</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Select</th>
                                        <th>Subject Code</th>
                                        <th>Subject Name</th>
                                        <th>Units</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $subject_query = "SELECT s.id, s.subject_code, s.subject_name, 
                                    CASE 
                                        WHEN a.subject_id IS NOT NULL THEN 'Assigned'
                                        ELSE 'Available'
                                    END as assignment_status";
                                    
                                    // Check if units column exists
                                    $check_units = $conn->query("SHOW COLUMNS FROM subjects LIKE 'units'");
                                    if ($check_units->num_rows > 0) {
                                        $subject_query .= ", s.units";
                                    }
                                    
                                    $subject_query .= " FROM subjects s
                                    LEFT JOIN assignments a ON s.id = a.subject_id
                                    ORDER BY s.subject_code";
                                    $subject_result = mysqli_query($conn, $subject_query);
                                    while ($subject = mysqli_fetch_assoc($subject_result)) {
                                        $disabled = $subject['assignment_status'] == 'Assigned' ? 'disabled' : '';
                                        $status_class = $subject['assignment_status'] == 'Assigned' ? 'text-danger' : 'text-success';
                                        echo "<tr>
                                                <td>
                                                    <input type='radio' name='subject_id' value='{$subject['id']}' 
                                                           class='form-check-input' required {$disabled}>
                                                </td>
                                                <td>{$subject['subject_code']}</td>
                                                <td>{$subject['subject_name']}</td>
                                                <td>" . (isset($subject['units']) ? $subject['units'] : 'N/A') . "</td>
                                                <td class='{$status_class}'>{$subject['assignment_status']}</td>
                                            </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Schedule Section -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3 border-bottom pb-2"><i class="fas fa-calendar-alt me-2"></i>Schedule Information</h6>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Day(s) <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap">
                                    <?php
                                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                    foreach ($days as $day) {
                                        echo "<div class='form-check form-check-inline mb-2'>
                                            <input class='form-check-input' type='checkbox' name='days[]' value='{$day}' id='day{$day}'>
                                            <label class='form-check-label' for='day{$day}'>{$day}</label>
                                          </div>";
                                    }
                                    ?>
                                </div>
                                <small class="form-text text-muted">Select one or more days for this class</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="far fa-clock"></i></span>
                                    <input type="time" class="form-control" name="time_start" min="07:00" max="19:00" required>
                                </div>
                                <small class="form-text text-muted">Time must be between 7:00 AM and 7:00 PM</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="far fa-clock"></i></span>
                                    <input type="time" class="form-control" name="time_end" min="07:00" max="19:00" required>
                                </div>
                                <small class="form-text text-muted">Time must be between 7:00 AM and 7:00 PM</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="far fa-calendar"></i></span>
                                    <input type="date" class="form-control" name="month_from" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <small class="form-text text-muted">When this assignment begins</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="far fa-calendar"></i></span>
                                    <input type="date" class="form-control" name="month_to" value="<?php echo date('Y-m-d', strtotime('+6 months')); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <small class="form-text text-muted">When this assignment ends</small>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Location <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                    <input type="text" class="form-control" name="location" placeholder="Room number or building" required>
                                </div>
                                <small class="form-text text-muted">Specify where this class will be held</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p>Are you sure you want to delete this assignment? This action cannot be undone.</p>
                <p class="mb-0"><strong>Warning:</strong> Deleting this assignment may affect attendance records and enrollments.</p>
                <input type="hidden" id="deleteAssignmentId">
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">
                    <i class="fas fa-trash me-1"></i> Delete Assignment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add this before including admin_footer.php -->
<script>
$(document).ready(function() {
    // Time validation
    $('input[name="time_start"], input[name="time_end"]').on('change', function() {
        const startTime = $('input[name="time_start"]').val();
        const endTime = $('input[name="time_end"]').val();
        
        if (startTime && endTime) {
            const start = new Date(`2000-01-01T${startTime}`);
            const end = new Date(`2000-01-01T${endTime}`);
            
            // Check if times are within allowed range (7 AM to 7 PM)
            const minTime = new Date('2000-01-01T07:00');
            const maxTime = new Date('2000-01-01T19:00');
            
            if (start < minTime || end > maxTime) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Time',
                    text: 'Time must be between 7:00 AM and 7:00 PM'
                });
                $(this).val('');
                return;
            }
            
            // Check if end time is after start time
            if (end <= start) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Time Range',
                    text: 'End time must be after start time'
                });
                $('input[name="time_end"]').val('');
            }
        }
    });
    
    // Date validation
    $('input[name="month_from"], input[name="month_to"]').on('change', function() {
        const startDate = new Date($('input[name="month_from"]').val());
        const endDate = new Date($('input[name="month_to"]').val());
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Check if dates are in the past
        if (startDate < today || endDate < today) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Date',
                text: 'Dates cannot be in the past'
            });
            $(this).val('');
            return;
        }
        
        // Check if end date is after start date
        if (endDate < startDate) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Date Range',
                text: 'End date must be after start date'
            });
            $('input[name="month_to"]').val('');
        }
    });
    
    // Add Assignment Form Submit
    document.getElementById('addAssignmentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const modalBody = $(this).closest('.modal-content').find('.modal-body');
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');
        modalBody.css('opacity', '0.5');
        
        // Additional validation
        const timeStart = this.elements['time_start'].value;
        const timeEnd = this.elements['time_end'].value;
        const monthFrom = new Date(this.elements['month_from'].value);
        const monthTo = new Date(this.elements['month_to'].value);

        if (monthTo < monthFrom) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'End date must be after start date'
            });
            submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Assignment');
            modalBody.css('opacity', '1');
            return;
        }

        if (timeEnd <= timeStart) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'End time must be after start time'
            });
            submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Assignment');
            modalBody.css('opacity', '1');
            return;
        }

        // Check if at least one day is selected
        const selectedDays = $('input[name="days[]"]:checked').length;
        if (selectedDays === 0) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please select at least one day for the schedule'
            });
            submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Assignment');
            modalBody.css('opacity', '1');
            return;
        }

        let formData = new FormData(this);
        
        // Use the optimized version
        $.ajax({
            url: 'add_assignment_optimized.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    // Parse the response if it's a string
                    if (typeof response === 'string') {
                        response = JSON.parse(response);
                    }
                    
                    if (response.success) {
                        // Close modal and refresh immediately
                        $('#addAssignmentModal').modal('hide');
                        window.location.reload();
                    } else {
                        // Check if it's a schedule conflict
                        if (response.message && response.message.includes('Schedule Conflict')) {
                            // Format the conflict message for better readability
                            let conflictMessage = response.message;
                            if (conflictMessage.includes('Schedule Conflict Detected')) {
                                conflictMessage = conflictMessage.replace('Schedule Conflict Detected!', '');
                                conflictMessage = conflictMessage.replace(/\n\n/g, '<br><br>');
                                conflictMessage = conflictMessage.replace(/\n/g, '<br>');
                                
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Schedule Conflict Detected!',
                                    html: conflictMessage,
                                    confirmButtonText: 'OK',
                                    confirmButtonColor: '#021F3F',
                                    customClass: {
                                        container: 'schedule-conflict-alert'
                                    }
                                });
                            } else {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Schedule Conflict',
                                    text: response.message,
                                    confirmButtonText: 'OK',
                                    confirmButtonColor: '#021F3F'
                                });
                            }
                        } else {
                            // Show regular error message
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: response.message || 'Failed to create assignment'
                            });
                        }
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'An unexpected error occurred. Please try again.'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Submission error:', error);
                let errorMessage = 'An error occurred while creating the assignment.';
                
                if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMessage = response.message || errorMessage;
                    } catch (e) {
                        // If response is not JSON, use the raw text
                        errorMessage = xhr.responseText;
                    }
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: errorMessage
                });
            },
            complete: function() {
                // Reset form state
                submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Assignment');
                modalBody.css('opacity', '1');
            }
        });
    });

    // Delete assignment
    $('.delete-assignment').click(function() {
        const id = $(this).data('id');
        $('#deleteAssignmentId').val(id);
        $('#deleteConfirmationModal').modal('show');
    });

    // Handle delete confirmation
    $('#confirmDelete').click(function() {
        const id = $('#deleteAssignmentId').val();
        const deleteBtn = $(this);
        const modal = $('#deleteConfirmationModal');
        
        // Disable the button and show loading state
        deleteBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Deleting...');
        
        // Close the modal immediately for better UX
        modal.modal('hide');
        
        // Send delete request
        $.ajax({
            url: 'delete_assignment.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Reload the page immediately
                    window.location.reload();
                } else {
                    // Show error message
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: response.message || 'Failed to delete the assignment.'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while deleting the assignment. Please try again.'
                });
            },
            complete: function() {
                // Reset button state
                deleteBtn.prop('disabled', false).html('<i class="fas fa-trash me-1"></i> Delete Assignment');
            }
        });
    });

    // Search functionality
    $('#searchAssignment').on('keyup', function() {
        const searchText = $(this).val().toLowerCase();
        $('#assignmentsTable tbody tr').each(function() {
            const rowText = $(this).text().toLowerCase();
            $(this).toggle(rowText.indexOf(searchText) > -1);
        });
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?>