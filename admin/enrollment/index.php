<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

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

// Get statistics
// Total Active Students
                            $query = "SELECT COUNT(*) as count FROM students WHERE status = 'Active'";
                            $result = $conn->query($query);
                            $total_students = $result->fetch_assoc()['count'];

// Total Active Subjects
                            $check_status = $conn->query("SHOW COLUMNS FROM subjects LIKE 'status'");
                            if ($check_status->num_rows > 0) {
                                $query = "SELECT COUNT(*) as count FROM subjects WHERE status = 'Active'";
                            } else {
                                $query = "SELECT COUNT(*) as count FROM subjects";
                            }
                            $result = $conn->query($query);
                            $total_subjects = $result->fetch_assoc()['count'];

// Total Active Enrollments
// First check if status column exists in enrollments table
$check_enrollment_status = $conn->query("SHOW COLUMNS FROM enrollments LIKE 'status'");
if ($check_enrollment_status->num_rows > 0) {
    $query = "SELECT COUNT(*) as count FROM enrollments WHERE status = 'Active'";
} else {
    $query = "SELECT COUNT(*) as count FROM enrollments";
}
$result = $conn->query($query);
$total_enrollments = $result->fetch_assoc()['count'];

// Get current semester/term
$schoolYear = '';
$semester = '';
// Check if academic_terms table exists first
$table_check = $conn->query("SHOW TABLES LIKE 'academic_terms'");
if ($table_check->num_rows > 0) {
    // Table exists, proceed with query
    $term_query = "SELECT * FROM academic_terms WHERE is_current = 1 LIMIT 1";
    $term_result = $conn->query($term_query);
    if ($term_result && $term_result->num_rows > 0) {
        $current_term = $term_result->fetch_assoc();
        $schoolYear = $current_term['school_year'];
        $semester = $current_term['semester'];
    } else {
        // No current term found, use calculated default
        $currentYear = date('Y');
        $schoolYear = ($currentYear) . '-' . ($currentYear + 1);
    }
} else {
    // Table doesn't exist, use calculated default
    $currentYear = date('Y');
    $schoolYear = ($currentYear) . '-' . ($currentYear + 1);
}

include '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-10">
            <!-- Page Header -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <h2 class="fw-bold text-primary mb-2">
                            <i class="fas fa-user-plus me-2"></i> Enrollment Management
                        </h2>
                        <p class="text-muted mb-0">Manage student enrollment in subjects and monitor course registrations</p>
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
                                    <h6 class="text-primary fw-bold mb-1">Active Students</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $total_students; ?></h3>
                </div>
                                <div class="icon-circle bg-primary text-white">
                                    <i class="fas fa-user-graduate"></i>
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
                                    <h3 class="fw-bold mb-0"><?php echo $total_subjects; ?></h3>
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
                                    <h6 class="text-warning fw-bold mb-1">Academic Year</h6>
                            <h3 class="fw-bold mb-0"><?php echo $schoolYear; ?></h3>
                                    <?php if (!empty($semester)): ?>
                                    <small class="text-muted"><?php echo $semester; ?> Semester</small>
                                    <?php endif; ?>
                        </div>
                        <div class="icon-circle bg-warning text-white">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

            <!-- Students Table Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-gradient-primary-to-secondary p-4 text-white d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-users me-2"></i> Student Enrollment
                    </h5>
                    <div class="d-flex">
                        <div class="input-group search-group" style="width: 250px;">
                            <input type="text" id="searchInput" name="search" class="form-control" placeholder="Search students..." 
                                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <div class="input-group-append d-flex">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-search"></i>
                                </span>
                                <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                <button class="btn btn-outline-secondary" id="clearSearch" type="button" title="Clear search">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="studentsTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="15%" class="ps-4">Student ID</th>
                                    <th width="35%">Name</th>
                                    <th width="50%" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // First check if status column exists in students table
                $check_status = $conn->query("SHOW COLUMNS FROM students LIKE 'status'");
                
                // Set up pagination
                $records_per_page = 10;
                $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
                $offset = ($page - 1) * $records_per_page;
                
                // Get search term if provided
                $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
                
                // Base query
                if ($check_status->num_rows > 0) {
                    $base_query = "SELECT id, student_id, first_name, last_name 
                             FROM students 
                             WHERE status = 'Active'";
                } else {
                    $base_query = "SELECT id, student_id, first_name, last_name 
                             FROM students";
                }
                
                // Add search condition if search term provided
                if (!empty($search_term)) {
                    $search_param = '%' . $search_term . '%';
                    $base_query .= " AND (student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
                }
                
                // Add ordering
                $base_query .= " ORDER BY last_name, first_name";
                
                // Count total records for pagination
                $count_query = "SELECT COUNT(*) as total FROM students";
                if ($check_status->num_rows > 0) {
                    $count_query .= " WHERE status = 'Active'";
                }
                
                // Add search condition to count query if search term provided
                if (!empty($search_term)) {
                    if (strpos($count_query, 'WHERE') !== false) {
                        $count_query .= " AND (student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
                    } else {
                        $count_query .= " WHERE (student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
                    }
                }
                
                // Execute count query with or without search parameters
                if (!empty($search_term)) {
                    $count_stmt = $conn->prepare($count_query);
                    $count_stmt->bind_param("sss", $search_param, $search_param, $search_param);
                    $count_stmt->execute();
                    $count_result = $count_stmt->get_result();
                } else {
                    $count_result = $conn->query($count_query);
                }
                
                $total_records = $count_result->fetch_assoc()['total'];
                $total_pages = ceil($total_records / $records_per_page);
                
                // Get paginated data
                $query = $base_query . " LIMIT $offset, $records_per_page";
                
                // Execute query with or without search parameters
                if (!empty($search_term)) {
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    $result = $conn->query($query);
                }
                
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td class='ps-4'><span class='badge bg-primary'>{$row['student_id']}</span></td>";
                        echo "<td>
                                <div class='d-flex align-items-center'>
                                    <div class='icon-circle bg-light text-primary me-2' style='width: 40px; height: 40px; font-size: 1rem;'>
                                        <i class='fas fa-user-graduate'></i>
                                    </div>
                                    <div class='fw-bold'>{$row['first_name']} {$row['last_name']}</div>
                                </div>
                              </td>";
                        echo "<td class='text-center'>
                            <button type='button' 
                                class='btn btn-primary btn-sm me-2'
                                onclick='showEnrollModal({$row['id']}, \"{$row['first_name']} {$row['last_name']}\")'>
                                <i class='fas fa-user-plus me-1'></i> Enroll
                            </button>
                            <button type='button' 
                                class='btn btn-outline-info btn-sm'
                                onclick='showViewModal({$row['id']}, \"{$row['first_name']} {$row['last_name']}\")'>
                                <i class='fas fa-eye me-1'></i> View
                            </button>
                          </td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='3' class='text-center py-4'>No students found</td></tr>";
                }
                ?>
            </tbody>
        </table>
                    </div>
                </div>
                <div class="card-footer bg-light py-3">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" <?php echo ($page <= 1) ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Previous</a>
                            </li>
                            <?php
                            // Calculate range of page numbers to display
                            $start_page = max(1, min($page - 2, $total_pages - 4));
                            $end_page = min($total_pages, max(5, $page + 2));
                            
                            // Display first page if not in range
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1' . (!empty($search_term) ? '&search=' . urlencode($search_term) : '') . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                }
                            }
                            
                            // Display page numbers
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li class="page-item ' . (($page == $i) ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . (!empty($search_term) ? '&search=' . urlencode($search_term) : '') . '">' . $i . '</a></li>';
                            }
                            
                            // Display last page if not in range
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . (!empty($search_term) ? '&search=' . urlencode($search_term) : '') . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" <?php echo ($page >= $total_pages) ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enrollment Modal -->
    <div class="modal fade" id="enrollModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-gradient-primary-to-secondary text-white">
                <h5 class="modal-title fw-bold" id="enrollModalLabel">
                    <i class="fas fa-user-plus me-2"></i> Enroll Student
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            <div class="modal-body p-4">
                    <form id="enrollmentForm" method="post">
                        <input type="hidden" id="modalStudentId" name="student_id">
                        <input type="hidden" id="modalStudentName" name="student_name">
                        
                        <div class="mb-4">
                        <h6 class="text-primary mb-3 border-bottom pb-2">
                            <i class="fas fa-book me-2"></i> Available Subjects
                        </h6>
                        <div id="subjectScheduleList" class="mt-3">
                                <!-- Subjects will be loaded here -->
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading available subjects...</p>
                            </div>
                            </div>
                        </div>
                    </form>
                </div>
            <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEnrollmentBtn">
                        <i class="fas fa-save me-1"></i> Save Enrollment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-gradient-primary-to-secondary text-white">
                <h5 class="modal-title fw-bold" id="viewModalTitle">
                    <i class="fas fa-clipboard-list me-2"></i> Student Enrollments
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            <div class="modal-body p-0">
                <div id="viewModalContent" class="px-4 py-3">
                        <!-- Content will be loaded here -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                    </div>
                        <p class="mt-2 text-muted">Loading enrollment data...</p>
                </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="deleteConfirmTitle">
                    <i class="fas fa-trash-alt me-2"></i> Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="icon-circle bg-danger text-white mx-auto mb-3">
                        <i class="fas fa-exclamation-triangle fa-lg"></i>
                    </div>
                    <h5 class="fw-bold">Are you sure you want to remove this enrollment?</h5>
                    <p class="text-muted">This action cannot be undone and will permanently delete the student's enrollment record for this subject.</p>
                </div>
                <input type="hidden" id="enrollmentToDelete" value="">
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash-alt me-1"></i> Delete Enrollment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold" id="successModalTitle">
                    <i class="fas fa-check-circle me-2"></i> Success
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="icon-circle bg-success text-white mx-auto mb-3">
                        <i class="fas fa-check fa-lg"></i>
                    </div>
                    <h5 class="fw-bold" id="successModalMessage">Operation completed successfully</h5>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="successDismissBtn">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Enrollment Confirmation Modal -->
<div class="modal fade" id="enrollmentConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="enrollmentConfirmTitle">
                    <i class="fas fa-question-circle me-2"></i> Confirm Enrollment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="icon-circle bg-primary text-white mx-auto mb-3">
                        <i class="fas fa-user-plus fa-lg"></i>
                    </div>
                    <h5 class="fw-bold">Are you sure you want to enroll this student?</h5>
                    <p class="text-muted">The student will be enrolled in all selected subjects.</p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmEnrollmentBtn">
                    <i class="fas fa-check me-1"></i> Yes, Enroll Student
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Conflict Modal -->
<div class="modal fade" id="scheduleConflictModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="conflictModalTitle">
                    <i class="fas fa-exclamation-triangle me-2"></i> Schedule Conflict
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="icon-circle bg-danger text-white mx-auto mb-3">
                        <i class="fas fa-calendar-times fa-lg"></i>
                    </div>
                    <h5 class="fw-bold" id="conflictModalMessage">Unable to enroll due to a schedule conflict</h5>
                    <div class="alert alert-light border mt-3">
                        <div class="text-start">
                            <p class="mb-1"><strong>Conflict Details:</strong></p>
                            <div id="conflictDetails" class="text-danger"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

<!-- Additional custom styles -->
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
        color: white !important;
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
    
    .btn-success, 
    .btn-info {
        background-color: var(--secondary-color) !important;
        border-color: var(--secondary-color) !important;
        color: white !important;
    }
    
    .btn-success:hover, 
    .btn-info:hover,
    .btn-success:focus, 
    .btn-info:focus,
    .btn-success:active, 
    .btn-info:active {
        background-color: var(--secondary-hover) !important;
        border-color: var(--secondary-hover) !important;
    }
    
    .btn-outline-info {
        color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
    }
    
    .btn-outline-info:hover,
    .btn-outline-info:focus,
    .btn-outline-info:active {
        background-color: var(--primary-color) !important;
        color: white !important;
    }
    
    .bg-primary {
        background-color: var(--primary-color) !important;
    }
    
    .bg-success, 
    .bg-info, 
    .bg-warning {
        background-color: var(--secondary-color) !important;
    }
    
    .badge.bg-primary {
        background-color: var(--primary-color) !important;
    }
    
    .badge.bg-success, 
    .badge.bg-info {
        background-color: var(--secondary-color) !important;
    }
    
    .page-item.active .page-link {
        background-color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
    }
    
    .page-link {
        color: var(--primary-color) !important;
    }
    
    .page-link:hover {
        color: var(--secondary-color) !important;
    }
    
    .modal-header.bg-primary,
    .modal-header.bg-info {
        background-color: var(--primary-color) !important;
    }
    
    .custom-checkbox .form-check-input {
        width: 20px;
        height: 20px;
    }
    
    .custom-checkbox .form-check-input:checked {
        background-color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
    }
    
    .alert-info {
        background-color: rgba(2, 31, 63, 0.1) !important;
        border-color: rgba(2, 31, 63, 0.2) !important;
        color: var(--primary-color) !important;
    }
    
    .custom-checkbox .form-check-label {
        padding-left: 0.5rem;
        display: flex;
        align-items: center;
    }
    
    #searchInput::placeholder {
        font-size: 0.85rem;
    }
    
    .search-group .btn {
        border-color: #dee2e6;
    }
    
    /* Centered layout improvements */
    #studentsTable {
        table-layout: fixed;
        width: 100%;
    }
    
    #studentsTable td {
        vertical-align: middle;
        padding: 0.75rem 1rem;
    }
    
    #studentsTable th {
        padding: 1rem;
        font-weight: 600;
    }
    
    .pagination .page-link {
        min-width: 40px;
        text-align: center;
    }
    
    @media (max-width: 768px) {
        .actions-cell {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .actions-cell .btn {
            width: 100%;
            margin: 0 !important;
        }
    }
    
    /* Toast styling */
    .toast-container {
        z-index: 9999;
    }
    
    .toast {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        opacity: 1;
    }
    
    .toast-header {
        padding: 0.75rem 1rem;
        font-weight: 500;
    }
    
    .toast-body {
        padding: 1rem;
        line-height: 1.5;
    }

    /* Modal View Styling */
    #viewModal .modal-body {
        padding: 1.5rem 2rem;
    }
    
    #viewModal .modal-content {
        max-width: 1200px;
        margin: 0 auto;
    }

    #viewModal .table-responsive {
        margin: 0 -0.5rem;
    }

    /* Search input styles */
    .search-group {
        position: relative;
    }
    
    .search-group .form-control:focus {
        box-shadow: 0 0 0 0.25rem rgba(2, 31, 63, 0.25);
        border-color: var(--primary-color);
        z-index: 1;
    }
    
    .input-group-append {
        margin-left: -1px;
    }
    
    .input-group-text {
        border-left: none;
        border-radius: 0;
    }
    
    #clearSearch {
        border-left: none;
        border-top-right-radius: 0.25rem;
        border-bottom-right-radius: 0.25rem;
    }
</style>

<!-- Initialize modals with jQuery which is more reliable -->
<script>
$(document).ready(function() {
    // Initialize the save button click event
    $('#saveEnrollmentBtn').on('click', function() {
        submitEnrollment();
    });
    
    // Search without losing focus on input
    $('#searchInput').on('input', function() {
        const searchValue = $(this).val().trim();
        
        // If search value is empty, go back to main list
        if (searchValue.length === 0) {
            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.delete('search');
            window.history.pushState({}, '', url);
            
            // No need to reload page for empty search - we'll handle this server-side later
            if (!window.location.href.includes('search=')) {
                // Only reload if we're not already on the clean URL
                window.location.href = 'index.php';
            }
            return;
        }
        
        // Update URL without page reload to preserve search term
        const url = new URL(window.location);
        url.searchParams.set('search', searchValue);
        url.searchParams.set('page', '1'); // Always start at page 1 for new searches
        window.history.pushState({}, '', url);
        
        // Use AJAX to fetch results without refreshing the page
        $.ajax({
            url: 'index.php',
            type: 'GET',
            data: {
                search: searchValue,
                page: 1,
                ajax: 1 // Flag to indicate AJAX request
            },
            success: function(data) {
                try {
                    // Extract just the table body content using a regex pattern
                    const tableBodyMatch = data.match(/<tbody>([\s\S]*?)<\/tbody>/);
                    if (tableBodyMatch && tableBodyMatch[1]) {
                        $('tbody').html(tableBodyMatch[1]);
                        
                        // Also update pagination
                        const paginationMatch = data.match(/<nav aria-label="Page navigation">([\s\S]*?)<\/nav>/);
                        if (paginationMatch && paginationMatch[1]) {
                            $('nav[aria-label="Page navigation"]').html(paginationMatch[1]);
                        }
                    }
                } catch (e) {
                    console.error('Error parsing AJAX response:', e);
                }
            },
            error: function() {
                console.error('Failed to fetch search results');
            }
        });
    });
    
    // Clear search button with AJAX
    $('#clearSearch').on('click', function() {
        $('#searchInput').val('');
        
        // Update URL without page reload
        const url = new URL(window.location);
        url.searchParams.delete('search');
        window.history.pushState({}, '', url);
        
        // Reload page to show all results
        window.location.href = 'index.php';
    });
});

// Function to submit enrollment
function submitEnrollment() {
    // Get selected assignment
    const selectedAssignment = $('input[name="subject_schedule[]"]:checked');
    
    if (selectedAssignment.length === 0) {
        alert('Please select a subject schedule to enroll.');
        return;
    }
    
    // Get form data
    const formData = new FormData(document.getElementById('enrollmentForm'));
    
    // Show confirmation modal instead of using confirm()
    const enrollmentConfirmModal = new bootstrap.Modal(document.getElementById('enrollmentConfirmModal'));
    enrollmentConfirmModal.show();
}

// Handle confirmation from the modal
document.getElementById('confirmEnrollmentBtn').addEventListener('click', function() {
    // Get form and form data
    const form = document.getElementById('enrollmentForm');
    const formData = new FormData(form);
    
    // Set loading state
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
    
    // Hide confirmation modal
    bootstrap.Modal.getInstance(document.getElementById('enrollmentConfirmModal')).hide();
    
    // Submit form via AJAX to process_enrollment.php
    $.ajax({
        url: 'process_enrollment.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            try {
                // Log the raw response for debugging
                console.log('Raw server response:', response);
                
                // Try to parse if it's a string
                let data;
                if (typeof response === 'string') {
                    // Check if the response contains HTML or PHP errors
                    if (response.includes('<!DOCTYPE html>') || response.includes('PHP Error')) {
                        throw new Error('Server returned HTML or PHP error instead of JSON');
                    }
                    
                    // Try to parse JSON
                    data = JSON.parse(response);
                } else {
                    // Already an object
                    data = response;
                }
                
                if (data.success) {
                    // Show success message in modal instead of alert
                    document.getElementById('successModalMessage').textContent = data.message || 'Enrollment successful';
                    
                    // Close enrollment modal
                    bootstrap.Modal.getInstance(document.getElementById('enrollModal')).hide();
                    
                    // Show success modal
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    successModal.show();
                } else {
                    // Check if this is the "No timetable entry found" error
                    if (data.message && data.message.includes('No timetable entry found')) {
                        if (confirm("The system needs to create timetable entries first. Click OK to fix this automatically.")) {
                            // Open the fix_timetable.php script in a new window
                            window.open('../../fix_timetable.php', '_blank');
                            
                            // Re-enable the button after a delay to give time for the fix to complete
                            setTimeout(function() {
                                $('#confirmEnrollmentBtn').prop('disabled', false).html('<i class="fas fa-check me-1"></i> Yes, Enroll Student');
                                $('#saveEnrollmentBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Enrollment');
                            }, 3000);
                            return;
                        }
                    }
                    
                    // Check if this is a schedule conflict error
                    if (data.message && data.message.includes('Schedule conflict with')) {
                        // Parse the conflict message for better display
                        const conflictMsg = data.message.replace('Error: ', '');
                        parseAndDisplayConflict(conflictMsg);
                    } else {
                        // Show other error messages as alert
                        alert('Error: ' + (data.message || 'Failed to process enrollment'));
                    }
                    
                    // Re-enable buttons
                    $('#confirmEnrollmentBtn').prop('disabled', false).html('<i class="fas fa-check me-1"></i> Yes, Enroll Student');
                    $('#saveEnrollmentBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Enrollment');
                }
            } catch (e) {
                console.error('Error parsing JSON response:', e);
                console.error('Raw response:', response);
                
                // If it's a long response, truncate it for the alert
                let errorMsg = response;
                if (typeof response === 'string' && response.length > 100) {
                    errorMsg = response.substring(0, 100) + '... (see console for full response)';
                }
                
                // Check if this is a schedule conflict error
                if (typeof response === 'string' && response.includes('Schedule conflict with')) {
                    // Parse the conflict message for better display
                    parseAndDisplayConflict(response.replace('Error: ', ''));
                } else {
                    alert('Error: Invalid server response. Check browser console for details.\n\nResponse preview: ' + errorMsg);
                }
                
                // Re-enable buttons
                $('#confirmEnrollmentBtn').prop('disabled', false).html('<i class="fas fa-check me-1"></i> Yes, Enroll Student');
                $('#saveEnrollmentBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Enrollment');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.error('Status:', status);
            console.error('Status code:', xhr.status);
            console.error('Response Text:', xhr.responseText);
            
            let errorMsg = 'Server returned status code ' + xhr.status;
            if (xhr.responseText) {
                if (xhr.responseText.length > 100) {
                    errorMsg += ': ' + xhr.responseText.substring(0, 100) + '... (see console for details)';
                } else {
                    errorMsg += ': ' + xhr.responseText;
                }
            }
            
            // Check if this is a schedule conflict error
            if (xhr.responseText && xhr.responseText.includes('Schedule conflict with')) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    // Parse the conflict message for better display
                    parseAndDisplayConflict(data.message.replace('Error: ', ''));
                } catch (e) {
                    // Parse the conflict message for better display
                    parseAndDisplayConflict(xhr.responseText.replace('Error: ', ''));
                }
            } else {
                alert('Error: ' + errorMsg);
            }
            
            // Re-enable buttons
            $('#confirmEnrollmentBtn').prop('disabled', false).html('<i class="fas fa-check me-1"></i> Yes, Enroll Student');
            $('#saveEnrollmentBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Enrollment');
        }
    });
});

// Add event listener for success modal dismiss
document.addEventListener('DOMContentLoaded', function() {
    // Set up success modal dismiss
    document.getElementById('successDismissBtn').addEventListener('click', function() {
        location.reload();
    });
});

// Function to show enrollment modal
function showEnrollModal(studentId, studentName) {
    // Set modal title
    document.getElementById('enrollModalLabel').innerHTML = '<i class="fas fa-user-plus me-2"></i> Enroll: ' + studentName;
    
    // Set form values
    document.getElementById('modalStudentId').value = studentId;
    document.getElementById('modalStudentName').value = studentName;
    
    // Clear previous list and show loading
    document.getElementById('subjectScheduleList').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
                                </div>
            <p class="mt-2 text-muted">Loading available subjects...</p>
                            </div>
                        `;
    
    // Load subject schedules
    fetch('get_subjects.php?student_id=' + studentId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Format data for display
                let html = '';
                
                if (data.assignments && data.assignments.length > 0) {
                    // Group assignments by subject for better organization
                        const subjectGroups = {};
                    
                        data.assignments.forEach(assignment => {
                            if (!subjectGroups[assignment.subject_id]) {
                                subjectGroups[assignment.subject_id] = {
                                    subject_id: assignment.subject_id,
                                    subject_code: assignment.subject_code,
                                    subject_name: assignment.subject_name,
                                    assignments: []
                                };
                            }
                            subjectGroups[assignment.subject_id].assignments.push(assignment);
                        });
                        
                        // Generate HTML for each subject group
                        Object.values(subjectGroups).forEach(subject => {
                            // Check if any assignment for this subject is already enrolled
                        const isAnyEnrolled = subject.assignments.some(a => a.is_enrolled);
                            
                        html += `
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-light text-primary border me-2">${subject.subject_code}</span>
                                                <span class="fw-bold">${subject.subject_name}</span>
                                            </div>
                                            ${isAnyEnrolled ? '<span class="badge bg-success">Already Enrolled</span>' : ''}
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="list-group">
                            `;
                            
                            // Add each assignment schedule as an option
                            subject.assignments.forEach(assignment => {
                            html += `
                                    <div class="list-group-item">
                                        <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                            name="subject_schedule[]" 
                                                id="assignment_${assignment.assignment_id}" 
                                                value="${assignment.assignment_id}" 
                                            ${assignment.is_enrolled ? 'checked disabled' : ''}
                                                ${isAnyEnrolled && !assignment.is_enrolled ? 'disabled' : ''}>
                                        <label class="form-check-label w-100" for="assignment_${assignment.assignment_id}">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                <div>
                                                    <i class="fas fa-user-tie me-1 text-muted"></i> ${assignment.teacher_name}
                                                </div>
                                                <div class="mt-1">
                                                        <i class="fas fa-calendar-alt me-1 text-muted"></i> ${assignment.preferred_day}
                                                    <span class="mx-1">•</span>
                                                        <i class="fas fa-clock me-1 text-muted"></i> ${assignment.formatted_start_time} - ${assignment.formatted_end_time}
                                                </div>
                                                ${assignment.location ? `
                                                <div class="mt-1">
                                                    <i class="fas fa-map-marker-alt me-1 text-muted"></i> ${assignment.location}
                                                </div>` : ''}
                                                </div>
                                                ${assignment.is_enrolled ? '<span class="badge bg-success">Currently Enrolled</span>' : ''}
                                            </div>
                                            </label>
                                        </div>
                                    </div>
                                `;
                            });
                            
                        html += `
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                } else {
                    html = `
                        <div class="alert alert-warning">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                                </div>
                                <div>
                                    <strong>No Available Subjects</strong>
                                    <p class="mb-0 small">No subjects have been assigned to teachers yet. Please assign subjects to teachers first.</p>
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                document.getElementById('subjectScheduleList').innerHTML = html;
                } else {
                document.getElementById('subjectScheduleList').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i> Error: ${data.message || 'Could not load subjects'}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('subjectScheduleList').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i> Error loading subjects: ${error}
                </div>
            `;
        });
    
    // Show modal
    const enrollModal = new bootstrap.Modal(document.getElementById('enrollModal'));
    enrollModal.show();
}

// Function to show view modal
function showViewModal(studentId, studentName) {
    // Set modal title
    document.getElementById('viewModalTitle').innerHTML = '<i class="fas fa-clipboard-list me-2"></i> Enrollments: ' + studentName;
    
    // Clear previous content and show loading
    document.getElementById('viewModalContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Loading enrollment data...</p>
        </div>
    `;
    
    // Load student enrollments
    fetch('get_enrollments.php?student_id=' + studentId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('viewModalContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('viewModalContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i> Error loading enrollments: ${error}
                </div>
            `;
        });
    
    // Show modal
    const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
    viewModal.show();
}
</script>

<!-- Additional script for handling enrollment deletion -->
<script>
function deleteEnrollment(enrollmentId, subjectName) {
    // Store the enrollment ID in the modal
    document.getElementById('enrollmentToDelete').value = enrollmentId;
    
    // Update the modal text with subject info if provided
    if (subjectName) {
        document.querySelector('#deleteConfirmModal .modal-body h5').innerHTML = 
            `Are you sure you want to remove this enrollment?<br><span class="text-danger fw-bold">${subjectName}</span>`;
    }
    
    // Show the confirmation modal
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    deleteModal.show();
}

// Setup the confirmation button event handler
document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    // Get the enrollment ID from the hidden input
    const enrollmentId = document.getElementById('enrollmentToDelete').value;
    
    if (!enrollmentId) return;
    
    // Disable button and show loading state
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';
    
    // Create form data
    const formData = new FormData();
    formData.append('enrollment_id', enrollmentId);
    
    // Send request
    fetch('delete_enrollment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Hide the confirmation modal
        bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
        
        if (data.success) {
            // Use a toast or more elegant notification instead of alert
            const toast = document.createElement('div');
            toast.classList.add('position-fixed', 'bottom-0', 'end-0', 'p-3', 'toast-container');
            toast.style.zIndex = '5';
            toast.innerHTML = `
                <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header bg-success text-white">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong class="me-auto">Success</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        Successfully removed enrollment for ${data.subject}
                    </div>
                </div>
            `;
            document.body.appendChild(toast);
            
            // Auto-dismiss toast after 3 seconds
            setTimeout(() => {
                const bsToast = new bootstrap.Toast(toast.querySelector('.toast'));
                bsToast.hide();
                // Remove from DOM after animation
                toast.addEventListener('hidden.bs.toast', () => toast.remove());
            }, 3000);
            
            // Refresh the modal content
            const studentId = document.getElementById('viewModalContent').getAttribute('data-student-id');
            if (studentId) {
                showViewModal(studentId, document.getElementById('viewModalTitle').textContent.replace('Enrollments: ', ''));
            } else {
                // If student ID not available, close modal and reload page
                bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide();
                window.location.reload();
            }
        } else {
            // Error notification
            alert('Error: ' + data.message);
        }
        
        // Reset button
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-trash-alt me-1"></i> Delete Enrollment';
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while removing the enrollment.');
        
        // Reset button
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-trash-alt me-1"></i> Delete Enrollment';
        
        // Hide the confirmation modal
        bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
    });
});
</script>

<!-- Additional script for handling finalize modal -->
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

    // Ensure only one event listener gets attached to the successDismissBtn
    const successDismissBtn = document.getElementById('successDismissBtn');
    if (successDismissBtn) {
        successDismissBtn.addEventListener('click', function() {
            location.reload();
        });
    }
});
</script>

<!-- Additional script for handling schedule conflict -->
<script>
// Add this function to better parse and display schedule conflicts
function parseAndDisplayConflict(conflictMsg) {
    // Example conflict message: "Schedule conflict with ooooooooo (ooooooooo) on 5 at 07:00:00 - 08:00:00"
    let subject = "Unknown subject";
    let day = "Unknown day";
    let time = "Unknown time";
    
    // Extract subject
    const subjectMatch = conflictMsg.match(/conflict with ([^(]+)/);
    if (subjectMatch && subjectMatch[1]) {
        subject = subjectMatch[1].trim();
    }
    
    // Extract day
    const dayMatch = conflictMsg.match(/on (\d+|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)/);
    if (dayMatch && dayMatch[1]) {
        // Convert numeric day to name if needed
        const dayNum = parseInt(dayMatch[1]);
        if (!isNaN(dayNum)) {
            const days = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
            day = days[dayNum % 7];
        } else {
            day = dayMatch[1];
        }
    }
    
    // Extract time
    const timeMatch = conflictMsg.match(/at ([0-9:]+) - ([0-9:]+)/);
    if (timeMatch && timeMatch[1] && timeMatch[2]) {
        // Format time to be more readable
        const startTime = formatTime(timeMatch[1]);
        const endTime = formatTime(timeMatch[2]);
        time = `${startTime} - ${endTime}`;
    }
    
    // Set modal content
    document.getElementById('conflictDetails').innerHTML = `
        <div class="d-flex flex-column">
            <div class="mb-2">
                <i class="fas fa-book me-2"></i><strong>Subject:</strong> ${subject}
            </div>
            <div class="mb-2">
                <i class="fas fa-calendar-day me-2"></i><strong>Day:</strong> ${day}
            </div>
            <div>
                <i class="fas fa-clock me-2"></i><strong>Time:</strong> ${time}
            </div>
        </div>
    `;
    
    // Show the conflict modal
    const conflictModal = new bootstrap.Modal(document.getElementById('scheduleConflictModal'));
    conflictModal.show();
}

// Helper function to format time (24h to 12h format)
function formatTime(timeStr) {
    // Handle times with or without seconds
    const timeParts = timeStr.split(':');
    let hours = parseInt(timeParts[0]);
    const minutes = timeParts[1];
    
    // Format as 12-hour time
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // Convert 0 to 12
    
    return `${hours}:${minutes} ${ampm}`;
}
</script>
</body>
</html>
