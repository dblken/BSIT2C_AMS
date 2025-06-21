<?php
require_once '../../config/database.php';

// Check if AJAX request
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == 1;

// If it's an AJAX request, only include core content
if (!$is_ajax) {
    include '../includes/admin_header.php';
}

// Function to determine status color
function getStatusColor($status) {
    switch ($status) {
        case 'Active':
            return '#198754'; // green
        case 'Inactive':
            return '#dc3545'; // red
        default:
            return '#6c757d'; // gray
    }
}

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $records_per_page;

// Get search term if provided
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Count statistics
$total_students_query = "SELECT COUNT(*) as total FROM students";
$active_students_query = "SELECT COUNT(*) as total FROM students WHERE status = 'Active'";
$inactive_students_query = "SELECT COUNT(*) as total FROM students WHERE status = 'Inactive'";

$total_result = mysqli_query($conn, $total_students_query);
$active_result = mysqli_query($conn, $active_students_query);
$inactive_result = mysqli_query($conn, $inactive_students_query);

$total_students = mysqli_fetch_assoc($total_result)['total'];
$active_students = mysqli_fetch_assoc($active_result)['total'];
$inactive_students = mysqli_fetch_assoc($inactive_result)['total'];

// Base query for students
$base_query = "SELECT * FROM students";
$count_query = "SELECT COUNT(*) as total FROM students";

// Add search conditions if search term is provided
if (!empty($search_term)) {
    $search_param = '%' . $search_term . '%';
    $where_clause = " WHERE (student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $base_query .= $where_clause;
    $count_query .= $where_clause;
}

// Add ordering
$base_query .= " ORDER BY student_id DESC";

// Execute count query with or without search parameters
if (!empty($search_term)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
} else {
    $count_result = $conn->query($count_query);
}

$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Finalize query with pagination
$final_query = $base_query . " LIMIT $offset, $records_per_page";

// Execute the query
if (!empty($search_term)) {
    $stmt = $conn->prepare($final_query);
    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($final_query);
}
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-10">
            <!-- Page Header -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <h2 class="fw-bold text-primary mb-2">
                            <i class="fas fa-user-graduate me-2"></i> Student Management
                        </h2>
                        <p class="text-muted mb-0">Register, view and manage student information</p>
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
                                    <h6 class="text-primary fw-bold mb-1">Total Students</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $total_students; ?></h3>
                                </div>
                                <div class="icon-circle bg-primary text-white">
                                    <i class="fas fa-users"></i>
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
                                    <h6 class="text-success fw-bold mb-1">Active Students</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $active_students; ?></h3>
                                </div>
                                <div class="icon-circle bg-success text-white">
                                    <i class="fas fa-user-check"></i>
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
                                    <h6 class="text-danger fw-bold mb-1">Inactive Students</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $inactive_students; ?></h3>
                                </div>
                                <div class="icon-circle bg-danger text-white">
                                    <i class="fas fa-user-slash"></i>
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
                        <i class="fas fa-list me-2"></i> Student List
                    </h5>
                    <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="fas fa-plus me-2"></i> Add New Student
                    </button>
                </div>
                <div class="card-body p-4">
                    <!-- Search and Filter -->
                    <div class="row mb-4 align-items-center">
                        <div class="col-md-8 mb-3 mb-md-0">
                            <div class="input-group search-group">
                                <input type="text" id="searchStudent" name="search" class="form-control" placeholder="Search by name, ID, email..." 
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
                        <div class="col-md-4 d-flex justify-content-md-end">
                            <select id="statusFilter" class="form-select" style="width: auto;">
                                <option value="all">All Status</option>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="studentsTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="15%">Student ID</th>
                                    <th width="25%">Name</th>
                                    <th width="20%">Email</th>
                                    <th width="15%">Phone Number</th>
                                    <th width="10%">Status</th>
                                    <th width="15%" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (mysqli_num_rows($result) > 0) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        $full_name = $row['first_name'] . ' ' . 
                                                    (!empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '. ' : '') . 
                                                    $row['last_name'];
                                        
                                        echo "<tr class='clickable-row' onclick='viewStudent({$row['id']})' style='cursor: pointer;'>
                                                <td><span class='badge bg-primary'>{$row['student_id']}</span></td>
                                                <td>
                                                    <div class='d-flex align-items-center'>
                                                        <div class='icon-circle bg-light text-primary me-2' style='width: 40px; height: 40px; font-size: 1rem;'>
                                                            <i class='fas fa-user-graduate'></i>
                                                        </div>
                                                        <div class='fw-bold'>{$full_name}</div>
                                                    </div>
                                                </td>
                                                <td><a href='mailto:{$row['email']}' class='text-decoration-none' onclick='event.stopPropagation()'>{$row['email']}</a></td>
                                                <td>" . (!empty($row['phone']) ? $row['phone'] : '<span class="text-muted">--</span>') . "</td>
                                                <td onclick='event.stopPropagation();'>
                                                    <select class='form-select status-select " . ($row['status'] == 'Active' ? 'bg-success text-white' : 'bg-danger text-white') . "' 
                                                            onchange='updateStatus({$row['id']}, this.value, event)'
                                                            onclick='event.stopPropagation();'>
                                                        <option value='Active' " . ($row['status'] == 'Active' ? 'selected' : '') . ">Active</option>
                                                        <option value='Inactive' " . ($row['status'] == 'Inactive' ? 'selected' : '') . ">Inactive</option>
                                                    </select>
                                                </td>
                                                <td class='text-center' onclick='event.stopPropagation();'>
                                                    <button class='btn btn-sm btn-outline-primary me-1' onclick='editStudent({$row['id']}, event)' title='Edit Student'>
                                                        <i class='fas fa-edit'></i>
                                                    </button>
                                                    <button class='btn btn-sm btn-outline-danger' onclick='deleteStudent({$row['id']})' title='Delete Student'>
                                                        <i class='fas fa-trash'></i>
                                                    </button>
                                                </td>
                                            </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center py-4'>No students found. Click the 'Add New Student' button to register one.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <nav>
                        <ul class="pagination justify-content-center m-0">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" tabindex="-1">Previous</a>
                            </li>
                            
                            <?php
                            // Show up to 5 page links
                            $start_page = max(1, min($page - 2, $total_pages - 4));
                            $end_page = min($total_pages, max(5, $page + 2));
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">
                                        <a class="page-link" href="?page=' . $i . (!empty($search_term) ? '&search=' . urlencode($search_term) : '') . '">' . $i . '</a>
                                      </li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-gradient-primary-to-secondary text-white">
                <h5 class="modal-title fw-bold" id="addStudentModalLabel">
                    <i class="fas fa-user-plus me-2"></i> Add New Student
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="addStudentForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="student_id" class="form-label">Student ID <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-id-card"></i>
                                </span>
                                <input type="text" class="form-control" id="student_id" name="student_id" placeholder="Enter student ID">
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="text-primary mb-3 border-bottom pb-2"><i class="fas fa-user me-2"></i>Personal Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" placeholder="First name">
                        </div>
                        <div class="col-md-4">
                            <label for="middle_name" class="form-label">Middle Name <small class="text-muted">(Optional)</small></label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name" placeholder="Middle name (optional)">
                            <small class="form-text text-muted">Can be left blank if not applicable</small>
                        </div>
                        <div class="col-md-4">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Last name">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth">
                        </div>
                    </div>
                    
                    <h6 class="text-primary mb-3 border-bottom pb-2"><i class="fas fa-address-card me-2"></i>Contact Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email" placeholder="email@example.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-phone"></i>
                                </span>
                                <input type="text" class="form-control" id="phone" name="phone" placeholder="e.g. 09123456789">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2" placeholder="Enter complete address"></textarea>
                    </div>
                    
                    <h6 class="text-primary mb-3 border-bottom pb-2"><i class="fas fa-user-lock me-2"></i>Account Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="username" name="username" placeholder="Username for login">
                            </div>
                            <small class="form-text text-muted">Will be auto-generated based on name and ID</small>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter password">
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-primary generate-password" type="button" title="Generate Password">
                                    <i class="fas fa-key"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Minimum 8 characters with uppercase, lowercase, number and special character</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="saveNewStudent">
                    <i class="fas fa-save me-2"></i> Save Student
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Student Modal -->
<div class="modal fade" id="viewStudentModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-gradient-primary-to-secondary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-user-graduate me-2"></i> Student Profile
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="student-info">
                    <div class="row">
                        <div class="col-md-4 text-center mb-4 mb-md-0">
                            <div class="avatar-circle mx-auto mb-3 bg-primary text-white">
                                <i class="fas fa-user-graduate fa-4x"></i>
                            </div>
                            <h4 class="fw-bold mb-1" id="view_student_name"></h4>
                            <p class="text-muted" id="view_student_id_display"></p>
                            <div class="mt-3" id="view_status"></div>
                        </div>
                        <div class="col-md-8">
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="fas fa-info-circle me-2"></i>Contact Information</h6>
                                    <div class="row mb-3">
                                        <div class="col-sm-6">
                                            <div class="info-group">
                                                <label class="text-muted"><i class="fas fa-envelope me-2"></i>Email</label>
                                                <p id="view_email" class="fw-medium"></p>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="info-group">
                                                <label class="text-muted"><i class="fas fa-phone me-2"></i>Phone Number</label>
                                                <p id="view_phone_number" class="fw-medium"></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div class="info-group">
                                                <label class="text-muted"><i class="fas fa-venus-mars me-2"></i>Gender</label>
                                                <p id="view_gender" class="fw-medium"></p>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="info-group">
                                                <label class="text-muted"><i class="fas fa-birthday-cake me-2"></i>Date of Birth</label>
                                                <p id="view_date_of_birth" class="fw-medium"></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="info-group">
                                                <label class="text-muted"><i class="fas fa-map-marker-alt me-2"></i>Address</label>
                                                <p id="view_address" class="fw-medium"></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="fas fa-graduation-cap me-2"></i>Academic Information</h6>
                                    <div class="row text-center">
                                        <div class="col-md-4">
                                            <div class="p-2">
                                                <div class="small text-muted">Program</div>
                                                <div class="fw-bold">BSIT</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-2">
                                                <div class="small text-muted">Year Level</div>
                                                <div class="fw-bold">2nd Year</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-2">
                                                <div class="small text-muted">Section</div>
                                                <div class="fw-bold">Block C</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Close
                </button>
                <button type="button" class="btn btn-primary" onclick="editStudentFromView(event)">
                    <i class="fas fa-edit me-2"></i> Edit Profile
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-gradient-primary-to-secondary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-user-edit me-2"></i> Edit Student
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStudentForm">
                <div class="modal-body p-4">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Student ID</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-id-card"></i>
                                </span>
                                <input type="text" class="form-control" id="edit_student_id" name="student_id" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="text-primary mb-3 border-bottom pb-2"><i class="fas fa-user me-2"></i>Personal Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="edit_middle_name" name="middle_name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_gender" name="gender" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_date_of_birth" name="date_of_birth" required>
                        </div>
                    </div>

                    <h6 class="text-primary mb-3 border-bottom pb-2"><i class="fas fa-address-card me-2"></i>Contact Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_phone" class="form-label">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-phone"></i>
                                </span>
                                <input type="text" class="form-control" id="edit_phone" name="phone" placeholder="e.g. 09123456789">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                    </div>
                    
                    <h6 class="text-primary mb-3 border-bottom pb-2"><i class="fas fa-user-lock me-2"></i>Account Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="edit_username" name="username" readonly>
                            </div>
                            <small class="form-text text-muted">Username cannot be changed</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reset Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="edit_password" name="new_password" placeholder="Leave blank to keep current password">
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-primary generate-password" type="button" title="Generate Password">
                                    <i class="fas fa-key"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Minimum 6 characters. Leave empty to keep current password.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="successModalLabel"><i class="fas fa-check-circle me-2"></i>Success</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="mb-4" id="successModalMessage">Operation completed successfully!</p>
                <div class="alert alert-info mb-3" id="credentialsInfo" style="display: none;">
                    <p class="mb-2"><strong>Student account has been created successfully.</strong></p>
                    <p class="mb-0 small">The login credentials have been created based on the information provided.</p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<?php if (!$is_ajax): ?>
<style>
    :root {
        --primary-color: #021F3F;
        --secondary-color: #C8A77E;
        --primary-hover: #042b59;
        --secondary-hover: #b39268;
    }
    
    /* Search styles */
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
    
    /* Status select styling */
    .status-select {
        border-radius: 0.25rem;
        font-weight: 600;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .status-select.bg-success {
        background-color: #28a745 !important;
    }
    
    .status-select.bg-danger {
        background-color: #dc3545 !important;
    }
    
    .status-select:focus {
        box-shadow: 0 0 0 0.25rem rgba(2, 31, 63, 0.25);
    }
    
    .clickable-row {
        transition: background-color 0.2s;
    }
    
    .clickable-row:hover {
        background-color: rgba(2, 31, 63, 0.05);
    }
</style>
<?php endif; ?>

<script>
// Add Student
$(document).ready(function() {
    // Toggle password visibility
    $('.toggle-password').click(function() {
        const passwordField = $(this).closest('.input-group').find('input');
        const fieldType = passwordField.attr('type');
        
        if (fieldType === 'password') {
            passwordField.attr('type', 'text');
            $(this).find('i').removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordField.attr('type', 'password');
            $(this).find('i').removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // Generate random password
    $('.generate-password').click(function() {
        const length = 10;
        const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-+=';
        let password = '';
        
        // Ensure at least one uppercase, one lowercase, one number, and one special character
        password += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.charAt(Math.floor(Math.random() * 26));
        password += 'abcdefghijklmnopqrstuvwxyz'.charAt(Math.floor(Math.random() * 26));
        password += '0123456789'.charAt(Math.floor(Math.random() * 10));
        password += '!@#$%^&*()_-+='.charAt(Math.floor(Math.random() * 14));
        
        // Fill the rest randomly, but only with alphanumeric characters to avoid potential issues
        for (let i = 0; i < length - 4; i++) {
            password += 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'.charAt(
                Math.floor(Math.random() * 62)
            );
        }
        
        // Shuffle the password
        password = password.split('').sort(() => 0.5 - Math.random()).join('');
        
        // Set the password and make it visible
        const passwordField = $(this).closest('.input-group').find('input');
        passwordField.val(password);
        passwordField.attr('type', 'text');
        $(this).siblings('.toggle-password').find('i').removeClass('fa-eye').addClass('fa-eye-slash');
        passwordField.trigger('input');
    });
    
    // Auto-generate username from student ID and name
    $('#student_id, #first_name, #last_name').on('blur', function() {
        if ($('#username').val() === '') {
            const studentId = $('#student_id').val().trim();
            const firstName = $('#first_name').val().trim().toLowerCase();
            const lastName = $('#last_name').val().trim().toLowerCase();
            
            if (studentId && firstName && lastName) {
                // Generate username from first letter of first name + last name + last 4 digits of student ID
                let username = firstName.charAt(0) + lastName;
                
                // Add the last 4 digits of student ID if it's long enough
                if (studentId.length >= 4) {
                    username += studentId.slice(-4);
                } else {
                    username += studentId;
                }
                
                // Remove spaces and special characters
                username = username.replace(/[^a-z0-9]/g, '');
                
                $('#username').val(username);
                $('#username').trigger('input');
            } else if (studentId) {
                // Fallback to just using student ID if names aren't provided
                $('#username').val('student' + studentId.replace(/[^a-z0-9]/g, ''));
                $('#username').trigger('input');
            }
        }
    });
    
    // Form validation function
    function validateStudentForm(form) {
        let isValid = true;
        
        // Clear previous error messages
        $('.error-message').remove();
        $('.is-invalid').removeClass('is-invalid');
        $('.is-valid').removeClass('is-valid');
        window.firstErrorElement = null;
        
        // Student ID validation
        const studentId = form.querySelector('[name="student_id"]');
        if (!studentId.value.trim()) {
            showError(studentId, 'Please enter a Student ID.');
            isValid = false;
        } else if (!/^[a-zA-Z0-9-]+$/.test(studentId.value.trim())) {
            showError(studentId, 'Please enter a valid Student ID. It should contain only alphanumeric characters and be at least 6 characters long.');
            isValid = false;
        } else if (studentId.value.trim().length < 6) {
            showError(studentId, 'Please enter a valid Student ID. It should contain only alphanumeric characters and be at least 6 characters long.');
            isValid = false;
        } else {
            studentId.classList.add('is-valid');
        }
        
        // First name validation
        const firstName = form.querySelector('[name="first_name"]');
        if (!firstName.value.trim()) {
            showError(firstName, 'Please enter your first name.');
            isValid = false;
        } else if (!/^[a-zA-Z\s]+$/.test(firstName.value.trim())) {
            showError(firstName, 'Please enter your first name. It should contain only alphabetic characters and be at least 3 characters long.');
            isValid = false;
        } else if (firstName.value.trim().length < 3) {
            showError(firstName, 'Please enter your first name. It should contain only alphabetic characters and be at least 3 characters long.');
            isValid = false;
        } else {
            firstName.classList.add('is-valid');
        }
        
        // Middle name validation (optional)
        const middleName = form.querySelector('[name="middle_name"]');
        if (middleName.value.trim() && !/^[a-zA-Z\s]+$/.test(middleName.value.trim())) {
            showError(middleName, 'Middle name should only contain alphabetic characters.');
            isValid = false;
        } else if (middleName.value.trim() && middleName.value.trim().length < 2) {
            showError(middleName, 'If provided, middle name should be at least 2 characters long.');
            isValid = false;
        } else if (middleName.value.trim()) {
            middleName.classList.add('is-valid');
        }
        
        // Last name validation
        const lastName = form.querySelector('[name="last_name"]');
        if (!lastName.value.trim()) {
            showError(lastName, 'Please enter your last name.');
            isValid = false;
        } else if (!/^[a-zA-Z\s]+$/.test(lastName.value.trim())) {
            showError(lastName, 'Please enter your last name. It should contain only alphabetic characters and be at least 3 characters long.');
            isValid = false;
        } else if (lastName.value.trim().length < 3) {
            showError(lastName, 'Please enter your last name. It should contain only alphabetic characters and be at least 3 characters long.');
            isValid = false;
        } else {
            lastName.classList.add('is-valid');
        }
        
        // Gender validation
        const gender = form.querySelector('[name="gender"]');
        if (!gender.value) {
            showError(gender, 'Please select a gender.');
            isValid = false;
        } else {
            gender.classList.add('is-valid');
        }
        
        // Date of Birth validation
        const dob = form.querySelector('[name="date_of_birth"]');
        if (!dob.value) {
            showError(dob, 'Please enter your date of birth.');
            isValid = false;
        } else {
            const dobDate = new Date(dob.value);
            const today = new Date();
            const minDate = new Date();
            minDate.setFullYear(today.getFullYear() - 80); // Minimum age: 80 years
            const maxDate = new Date();
            maxDate.setFullYear(today.getFullYear() - 10); // Maximum age: 10 years
            
            if (dobDate > today) {
                showError(dob, 'Date of birth cannot be in the future.');
                isValid = false;
            } else if (dobDate < minDate) {
                showError(dob, 'Please enter a valid date of birth (must be less than 80 years ago).');
                isValid = false;
            } else if (dobDate > maxDate) {
                showError(dob, 'Student must be at least 10 years old.');
                isValid = false;
            } else {
                dob.classList.add('is-valid');
            }
        }
        
        // Email validation
        const email = form.querySelector('[name="email"]');
        if (!email.value.trim()) {
            showError(email, 'Please enter an email address.');
            isValid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
            showError(email, 'Please enter a valid email address in the format example@domain.com.');
            isValid = false;
        } else {
            email.classList.add('is-valid');
        }
        
        // Phone number validation (optional)
        const phone = form.querySelector('[name="phone"]');
        if (phone.value.trim() && !/^09\d{9}$/.test(phone.value.trim())) {
            showError(phone, 'Please enter a valid phone number starting with 09 followed by 9 digits.');
            isValid = false;
        } else if (phone.value.trim()) {
            phone.classList.add('is-valid');
        }
        
        // Username validation
        const username = form.querySelector('[name="username"]');
        if (!username.value.trim()) {
            showError(username, 'Please enter a username.');
            isValid = false;
        } else if (!/^[a-zA-Z0-9_]+$/.test(username.value.trim())) {
            showError(username, 'Please choose a unique username. It should contain only letters, numbers, and underscores, and be between 6 and 20 characters long.');
            isValid = false;
        } else if (username.value.trim().length < 6 || username.value.trim().length > 20) {
            showError(username, 'Please choose a unique username. It should contain only letters, numbers, and underscores, and be between 6 and 20 characters long.');
            isValid = false;
        } else {
            username.classList.add('is-valid');
        }
        
        // Password validation
        const password = form.querySelector('[name="password"]');
        if (!password.value.trim()) {
            showError(password, 'Please enter a password.');
            isValid = false;
        } else if (password.value.trim().length < 8) {
            showError(password, 'Please enter a secure password. It should be at least 8 characters long, include at least one uppercase letter, one lowercase letter, one number, and one special character (e.g., !, @, #, $).');
            isValid = false;
        } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_\-+=])/.test(password.value.trim())) {
            showError(password, 'Please enter a secure password. It should be at least 8 characters long, include at least one uppercase letter, one lowercase letter, one number, and one special character (e.g., !, @, #, $).');
            isValid = false;
        } else {
            password.classList.add('is-valid');
        }
        
        return isValid;
    }
    
    // Function to display error message
    function showError(element, message) {
        element.classList.add('is-invalid');
        
        // Remove any existing error message
        const existingError = element.parentElement.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        
        // Create and append error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        
        // Determine where to append the error message
        if (element.closest('.input-group')) {
            element.closest('.input-group').after(errorDiv);
        } else {
            element.parentElement.appendChild(errorDiv);
        }
        
        // Scroll to first error
        if (!window.firstErrorElement) {
            window.firstErrorElement = element;
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    // Expose the showError function to the global scope
    window.showError = showError;
    
    // Live validation
    $('#addStudentForm input, #addStudentForm select, #addStudentForm textarea').on('input change blur', function() {
        // Reset first error element
        window.firstErrorElement = null;
        
        // Remove existing validation classes and error messages
        $(this).removeClass('is-invalid is-valid');
        
        // Remove error message
        if ($(this).closest('.input-group').length) {
            $(this).closest('.input-group').siblings('.error-message').remove();
        } else {
            $(this).siblings('.error-message').remove();
        }
        
        const fieldName = this.name;
        const fieldValue = this.value.trim();
        
        // Skip validation for empty optional fields
        if (!fieldValue && (fieldName === 'middle_name' || fieldName === 'phone' || fieldName === 'address')) {
            return;
        }
        
        // Validate field based on its name
        switch(fieldName) {
            case 'student_id':
                if (!fieldValue) {
                    showError(this, 'Please enter a Student ID.');
                } else if (!/^[a-zA-Z0-9-]+$/.test(fieldValue)) {
                    showError(this, 'Please enter a valid Student ID. It should contain only alphanumeric characters and be at least 6 characters long.');
                } else if (fieldValue.length < 6) {
                    showError(this, 'Please enter a valid Student ID. It should contain only alphanumeric characters and be at least 6 characters long.');
                } else {
                    // Add check for existing student ID
                    const inputElement = this;
                    $.ajax({
                        url: 'check_student_id.php',
                        type: 'POST',
                        data: { student_id: fieldValue },
                        dataType: 'json',
                        success: function(response) {
                            if (response.exists) {
                                showError(inputElement, 'This Student ID already exists. Please use a different ID.');
                            } else {
                                $(inputElement).addClass('is-valid');
                            }
                        },
                        error: function() {
                            // If error occurs, we'll skip this validation
                            $(inputElement).addClass('is-valid');
                        }
                    });
                }
                break;
                
            case 'first_name':
            case 'last_name':
                if (!fieldValue) {
                    showError(this, 'Please enter your ' + (fieldName === 'first_name' ? 'first' : 'last') + ' name.');
                } else if (!/^[a-zA-Z\s]+$/.test(fieldValue)) {
                    showError(this, 'Please enter your ' + (fieldName === 'first_name' ? 'first' : 'last') + ' name. It should contain only alphabetic characters and be at least 3 characters long.');
                } else if (fieldValue.length < 3) {
                    showError(this, 'Please enter your ' + (fieldName === 'first_name' ? 'first' : 'last') + ' name. It should contain only alphabetic characters and be at least 3 characters long.');
                } else {
                    $(this).addClass('is-valid');
                }
                break;
                
            case 'middle_name':
                if (fieldValue && (!/^[a-zA-Z\s]+$/.test(fieldValue))) {
                    showError(this, 'Middle name should only contain alphabetic characters.');
                } else if (fieldValue && fieldValue.length < 2) {
                    showError(this, 'If provided, middle name should be at least 2 characters long.');
                } else if (fieldValue) {
                    $(this).addClass('is-valid');
                }
                break;
                
            case 'gender':
                if (!fieldValue) {
                    showError(this, 'Please select a gender.');
                } else {
                    $(this).addClass('is-valid');
                }
                break;
                
            case 'date_of_birth':
                if (!fieldValue) {
                    showError(this, 'Please enter your date of birth.');
                } else {
                    const dobDate = new Date(fieldValue);
                    const today = new Date();
                    const minDate = new Date();
                    minDate.setFullYear(today.getFullYear() - 80); // Minimum age: 80 years
                    const maxDate = new Date();
                    maxDate.setFullYear(today.getFullYear() - 10); // Maximum age: 10 years
                    
                    if (dobDate > today) {
                        showError(this, 'Date of birth cannot be in the future.');
                    } else if (dobDate < minDate) {
                        showError(this, 'Please enter a valid date of birth (must be less than 80 years ago).');
                    } else if (dobDate > maxDate) {
                        showError(this, 'Student must be at least 10 years old.');
                    } else {
                        $(this).addClass('is-valid');
                    }
                }
                break;
                
            case 'email':
                if (!fieldValue) {
                    showError(this, 'Please enter an email address.');
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fieldValue)) {
                    showError(this, 'Please enter a valid email address in the format example@domain.com.');
                } else {
                    $(this).addClass('is-valid');
                }
                break;
                
            case 'phone':
                if (fieldValue && !/^09\d{9}$/.test(fieldValue)) {
                    showError(this, 'Please enter a valid phone number starting with 09 followed by 9 digits.');
                } else if (fieldValue) {
                    $(this).addClass('is-valid');
                }
                break;
                
            case 'username':
                if (!fieldValue) {
                    showError(this, 'Please enter a username.');
                } else if (!/^[a-zA-Z0-9_]+$/.test(fieldValue)) {
                    showError(this, 'Please choose a unique username. It should contain only letters, numbers, and underscores, and be between 6 and 20 characters long.');
                } else if (fieldValue.length < 6 || fieldValue.length > 20) {
                    showError(this, 'Please choose a unique username. It should contain only letters, numbers, and underscores, and be between 6 and 20 characters long.');
                } else {
                    $(this).addClass('is-valid');
                }
                break;
                
            case 'password':
                if (!fieldValue) {
                    showError(this, 'Please enter a password.');
                } else if (fieldValue.length < 8) {
                    showError(this, 'Please enter a secure password. It should be at least 8 characters long, include at least one uppercase letter, one lowercase letter, one number, and one special character (!@#$%^&*()_-+=).');
                } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_\-+=])/.test(fieldValue)) {
                    showError(this, 'Please enter a secure password. It should be at least 8 characters long, include at least one uppercase letter, one lowercase letter, one number, and one special character (!@#$%^&*()_-+=).');
                } else {
                    $(this).addClass('is-valid');
                }
                break;
        }
    });
    
    // Save new student with improved error handling
    $('#saveNewStudent').click(function() {
        // Validate form
        const form = document.getElementById('addStudentForm');
        if (!validateStudentForm(form)) {
            return;
        }
        
        // Show loading
        $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
        $(this).prop('disabled', true);
        
        // Get form data
        var formData = $('#addStudentForm').serialize();
        
        // Submit via AJAX
        $.ajax({
            url: 'add_student.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success modal
                    $('#successModalMessage').text('Student added successfully!');
                    $('#credentialsInfo').show();
                    
                    // Reset the form
                    $('#addStudentForm')[0].reset();
                    
                    // Close add student modal
                    $('#addStudentModal').modal('hide');
                    
                    // Show success modal
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    successModal.show();
                    
                    // Add event listener for when success modal is hidden
                    $('#successModal').on('hidden.bs.modal', function () {
                        location.reload();
                    });
                } else {
                    // Show error message
                    alert(response.message || 'Unknown error occurred');
                }
            },
            error: function(xhr, status, error) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    alert('Error adding student: ' + (response.message || error));
                } catch(e) {
                    alert('Error adding student: ' + error + '\nCheck console for details');
                }
            },
            complete: function() {
                // Reset button
                $('#saveNewStudent').html('<i class="fas fa-save me-2"></i> Save Student');
                $('#saveNewStudent').prop('disabled', false);
            }
        });
    });
    
    // Reset the form validation on modal open
    $('#addStudentModal').on('show.bs.modal', function() {
        $('#addStudentForm')[0].reset();
        $('#addStudentForm input, #addStudentForm select, #addStudentForm textarea').removeClass('is-invalid is-valid');
        $('.error-message').remove();
        window.firstErrorElement = null;
    });
    
    // Initialize status filter
    $('#statusFilter').on('change', function() {
        filterStudents();
    });
    
    // Search student functionality
    $('#searchStudent').on('input', function() {
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
                        const paginationMatch = data.match(/<ul class="pagination">([\s\S]*?)<\/ul>/);
                        if (paginationMatch && paginationMatch[0]) {
                            $('.pagination').html($(paginationMatch[0]).html());
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
    
    // Clear search button
    $('#clearSearch').on('click', function() {
        $('#searchStudent').val('');
        
        // Update URL without page reload
        const url = new URL(window.location);
        url.searchParams.delete('search');
        window.history.pushState({}, '', url);
        
        // Reload page to show all results
        window.location.href = 'index.php';
    });
    
    // Function to filter students based on status
    $('#statusFilter').on('change', function() {
        filterStudents();
    });
    
    // Function to filter students based on status
    function filterStudents() {
        const statusFilter = $('#statusFilter').val();
        
        $('#studentsTable tbody tr').each(function() {
            let visible = true;
            
            // Filter by status
            if (statusFilter !== 'all') {
                const rowStatus = $(this).find('select.status-select').val();
                visible = rowStatus === statusFilter;
            }
            
            $(this).toggle(visible);
        });
    }

    // Function to populate edit form
    window.editStudent = function(id, event) {
        if (event) {
            event.stopPropagation();
        }
        
        // Show loading state in modal
        $('#addStudentModalLabel').html('<i class="fas fa-edit me-2"></i> Edit Student');
        $('#addStudentModal').modal('show');
        
        // Clear form and show loading state
        $('#addStudentForm')[0].reset();
        const modalBody = $('#addStudentModal .modal-body');
        modalBody.css('opacity', '0.5');
        
        // Fetch student data
        $.ajax({
            url: 'get_student.php',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const student = response.student;
                    
                    // Populate form fields with existing data
                    $('#student_id').val(student.student_id);
                    $('#first_name').val(student.first_name);
                    $('#middle_name').val(student.middle_name);
                    $('#last_name').val(student.last_name);
                    $('#email').val(student.email);
                    $('#phone').val(student.phone);
                    $('#address').val(student.address);
                    $('#date_of_birth').val(student.date_of_birth);
                    $('#gender').val(student.gender);
                    $('#username').val(student.username);
                    
                    // Make student_id field readonly in edit mode
                    $('#student_id').prop('readonly', true);
                    $('#username').prop('readonly', true);
                    
                    // Add student ID to form for update
                    if (!$('input[name="student_id_for_update"]').length) {
                        $('#addStudentForm').append('<input type="hidden" name="student_id_for_update" value="' + student.id + '">');
                    }
                    
                    // Update form action and button text
                    $('#addStudentForm').attr('action', 'update_student.php');
                    $('#saveNewStudent').html('<i class="fas fa-save me-2"></i>Update Student');
                    
                    // Show form
                    modalBody.css('opacity', '1');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: response.message || 'Failed to load student data'
                    });
                    $('#addStudentModal').modal('hide');
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to load student data. Please try again.'
                });
                $('#addStudentModal').modal('hide');
            }
        });
    };

    // Reset form when modal is closed
    $('#addStudentModal').on('hidden.bs.modal', function() {
        $('#addStudentForm')[0].reset();
        $('#addStudentForm input[name="student_id_for_update"]').remove();
        $('#addStudentForm').attr('action', 'add_student.php');
        $('#addStudentModalLabel').html('<i class="fas fa-user-plus me-2"></i> Add New Student');
        $('#saveNewStudent').html('<i class="fas fa-save me-2"></i>Save Student');
        
        // Reset readonly fields
        $('#student_id').prop('readonly', false);
        $('#username').prop('readonly', false);
    });

    // Handle form submission
    $('#addStudentForm').on('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const modalBody = $(this).closest('.modal-content').find('.modal-body');
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Saving...');
        modalBody.css('opacity', '0.5');
        
        // Get form data
        const formData = new FormData(this);
        const isUpdate = formData.has('student_id_for_update');
        
        // Send request
        $.ajax({
            url: isUpdate ? 'update_student.php' : 'add_student.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Close modal and refresh page
                    $('#addStudentModal').modal('hide');
                    window.location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: response.message || 'Failed to save student'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred. Please try again.'
                });
            },
            complete: function() {
                // Reset form state
                submitBtn.prop('disabled', false)
                    .html('<i class="fas fa-save me-2"></i>' + (isUpdate ? 'Update' : 'Save') + ' Student');
                modalBody.css('opacity', '1');
            }
        });
    });

    // Function to generate password from email and birthday
    function generatePasswordFromEmailAndBirthday() {
        const email = $('#email').val().trim();
        const birthday = $('#date_of_birth').val();
        
        if (email && birthday) {
            // Format birthday as YYYYMMDD
            const cleanBirthday = birthday.replace(/-/g, '');
            // Create password: email + birthday + special character
            const password = email.charAt(0).toUpperCase() + email.slice(1) + cleanBirthday + '#';
            $('#password').val(password);
            // Show password
            $('#password').attr('type', 'text');
            $('.toggle-password i').removeClass('fa-eye').addClass('fa-eye-slash');
        }
    }

    // Update password when email or birthday changes
    $('#email, #date_of_birth').on('change', function() {
        generatePasswordFromEmailAndBirthday();
    });

    // Override the generate password button click
    $('.generate-password').off('click').on('click', function() {
        generatePasswordFromEmailAndBirthday();
    });
});

// View Student
function viewStudent(id) {
    fetch('get_student.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const student = data.student;
                
                // Format date of birth if available
                let formattedDob = 'Not provided';
                if (student.date_of_birth) {
                    const dobDate = new Date(student.date_of_birth);
                    formattedDob = dobDate.toLocaleDateString('en-US', {
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric'
                    });
                }
                
                // Populate the view modal
                document.getElementById('view_student_name').textContent = 
                    `${student.first_name} ${student.middle_name ? student.middle_name + ' ' : ''}${student.last_name}`;
                
                const idElement = document.getElementById('view_student_id_display');
                idElement.textContent = `Student ID: ${student.student_id}`;
                idElement.setAttribute('data-id', student.id); // Store database ID as data attribute
                
                document.getElementById('view_email').textContent = student.email || 'Not provided';
                document.getElementById('view_phone_number').textContent = student.phone || 'Not provided';
                document.getElementById('view_gender').textContent = student.gender || 'Not provided';
                document.getElementById('view_date_of_birth').textContent = formattedDob;
                document.getElementById('view_address').textContent = student.address || 'Not provided';
                document.getElementById('view_status').innerHTML = 
                    `<span class="badge ${student.status === 'Active' ? 'bg-success' : 'bg-danger'}" style="font-size: 1rem; padding: 8px 15px;">${student.status}</span>`;

                // Show the modal
                $('#viewStudentModal').modal('show');
            }
        });
}

// Function to initiate edit from view modal
function editStudentFromView(event) {
    // Prevent default behavior if event is provided
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    // Get the student ID from the display (this contains the database ID not student_id)
    const studentElement = document.getElementById('view_student_id_display');
    const studentText = studentElement.textContent;
    
    // Close view modal
    $('#viewStudentModal').modal('hide');
    
    // Get the student database ID directly from a data attribute we'll add
    const studentId = studentElement.getAttribute('data-id');
    
    if (studentId) {
        // Wait a bit for the view modal to close then open edit modal
        setTimeout(() => {
            editStudent(studentId, null); // Pass null as the event
        }, 500);
    } else {
        console.error("Unable to find student ID for editing");
    }
}

// Delete Student
function deleteStudent(id) {
    if (confirm('Are you sure you want to delete this student?')) {
        fetch('delete_student.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Student deleted successfully!');
                location.reload();
            } else {
                alert(data.message || 'Error deleting student');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting student');
        });
    }
}

// Update Status
function updateStatus(id, newStatus, event) {
    console.log('Updating status for student ID:', id, 'to:', newStatus);
    
    // Show loading indicator
    const selectElement = event.target;
    selectElement.disabled = true;
    selectElement.style.opacity = '0.7';
    selectElement.style.cursor = 'wait';
    
    fetch('update_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: id,
            status: newStatus
        })
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        // Re-enable select
        selectElement.disabled = false;
        selectElement.style.opacity = '1';
        selectElement.style.cursor = 'pointer';
        
        if (data.success) {
            // Refresh the page after successful status update
            location.reload();
        } else {
            alert(data.message || 'Error updating status');
            console.error('Status update failed:', data.message);
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error during status update:', error);
        alert('Error updating status: ' + error.message);
        location.reload();
    });
}

// Edit Student
function editStudent(id, event) {
    // Prevent the row click event if event is provided
    if (event) {
        event.stopPropagation();
    }
    
    // Fetch student data
    fetch('edit.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const student = data.student;
                
                // Populate the edit form
                document.getElementById('edit_id').value = student.id;
                document.getElementById('edit_student_id').value = student.student_id;
                document.getElementById('edit_first_name').value = student.first_name;
                document.getElementById('edit_middle_name').value = student.middle_name;
                document.getElementById('edit_last_name').value = student.last_name;
                document.getElementById('edit_gender').value = student.gender;
                document.getElementById('edit_date_of_birth').value = student.date_of_birth;
                document.getElementById('edit_email').value = student.email;
                document.getElementById('edit_phone').value = student.phone;
                document.getElementById('edit_address').value = student.address;
                document.getElementById('edit_username').value = student.username;

                // Show the modal
                $('#editStudentModal').modal('show');
            } else {
                alert('Error fetching student data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error fetching student data');
        });
}

// Handle edit form submission
document.getElementById('editStudentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validate form (with special handling for password, which is optional in edit form)
    const form = document.getElementById('editStudentForm');
    
    // Store original password field validator temporarily
    const originalPasswordCheck = validateEditForm();
    
    if (!originalPasswordCheck) {
        return;
    }
    
    const formData = new FormData(this);
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
    
    fetch('update_student.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success modal
            $('#successModalMessage').text('Student updated successfully!');
            $('#credentialsInfo').hide();
            
            // Close edit student modal
            $('#editStudentModal').modal('hide');
            
            // Show success modal
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
            
            // Add event listener for when success modal is hidden
            $('#successModal').on('hidden.bs.modal', function () {
                location.reload();
            });
        } else {
            alert(data.message || 'Error updating student');
            console.error('Update error:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating student: ' + error.message);
    })
    .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Validation function for edit form (extends main validation with special handling for password)
function validateEditForm() {
    let isValid = true;
    
    // Clear previous error messages
    $('.error-message').remove();
    $('.is-invalid').removeClass('is-invalid');
    $('.is-valid').removeClass('is-valid');
    window.firstErrorElement = null;
    
    const form = document.getElementById('editStudentForm');
    
    // Student ID is readonly in edit form, so we don't validate it
    
    // First name validation
    const firstName = form.querySelector('[name="first_name"]');
    if (!firstName.value.trim()) {
        window.showError(firstName, 'Please enter your first name.');
        isValid = false;
    } else if (!/^[a-zA-Z\s]+$/.test(firstName.value.trim())) {
        window.showError(firstName, 'Please enter your first name. It should contain only alphabetic characters and be at least 3 characters long.');
        isValid = false;
    } else if (firstName.value.trim().length < 3) {
        window.showError(firstName, 'Please enter your first name. It should contain only alphabetic characters and be at least 3 characters long.');
        isValid = false;
    } else {
        firstName.classList.add('is-valid');
    }
    
    // Middle name validation (optional)
    const middleName = form.querySelector('[name="middle_name"]');
    if (middleName.value.trim() && !/^[a-zA-Z\s]+$/.test(middleName.value.trim())) {
        window.showError(middleName, 'Middle name should only contain alphabetic characters.');
        isValid = false;
    } else if (middleName.value.trim() && middleName.value.trim().length < 2) {
        window.showError(middleName, 'If provided, middle name should be at least 2 characters long.');
        isValid = false;
    } else if (middleName.value.trim()) {
        middleName.classList.add('is-valid');
    }
    
    // Last name validation
    const lastName = form.querySelector('[name="last_name"]');
    if (!lastName.value.trim()) {
        window.showError(lastName, 'Please enter your last name.');
        isValid = false;
    } else if (!/^[a-zA-Z\s]+$/.test(lastName.value.trim())) {
        window.showError(lastName, 'Please enter your last name. It should contain only alphabetic characters and be at least 3 characters long.');
        isValid = false;
    } else if (lastName.value.trim().length < 3) {
        window.showError(lastName, 'Please enter your last name. It should contain only alphabetic characters and be at least 3 characters long.');
        isValid = false;
    } else {
        lastName.classList.add('is-valid');
    }
    
    // Gender validation
    const gender = form.querySelector('[name="gender"]');
    if (!gender.value) {
        window.showError(gender, 'Please select a gender.');
        isValid = false;
    } else {
        gender.classList.add('is-valid');
    }
    
    // Date of Birth validation
    const dob = form.querySelector('[name="date_of_birth"]');
    if (!dob.value) {
        window.showError(dob, 'Please enter your date of birth.');
        isValid = false;
    } else {
        const dobDate = new Date(dob.value);
        const today = new Date();
        const minDate = new Date();
        minDate.setFullYear(today.getFullYear() - 80); // Minimum age: 80 years
        const maxDate = new Date();
        maxDate.setFullYear(today.getFullYear() - 10); // Maximum age: 10 years
        
        if (dobDate > today) {
            window.showError(dob, 'Date of birth cannot be in the future.');
            isValid = false;
        } else if (dobDate < minDate) {
            window.showError(dob, 'Please enter a valid date of birth (must be less than 80 years ago).');
            isValid = false;
        } else if (dobDate > maxDate) {
            window.showError(dob, 'Student must be at least 10 years old.');
            isValid = false;
        } else {
            dob.classList.add('is-valid');
        }
    }
    
    // Email validation
    const email = form.querySelector('[name="email"]');
    if (!email.value.trim()) {
        window.showError(email, 'Please enter an email address.');
        isValid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
        window.showError(email, 'Please enter a valid email address in the format example@domain.com.');
        isValid = false;
    } else {
        email.classList.add('is-valid');
    }
    
    // Phone number validation (optional)
    const phone = form.querySelector('[name="phone"]');
    if (phone.value.trim() && !/^09\d{9}$/.test(phone.value.trim())) {
        window.showError(phone, 'Please enter a valid phone number starting with 09 followed by 9 digits.');
        isValid = false;
    } else if (phone.value.trim()) {
        phone.classList.add('is-valid');
    }
    
    // Username is readonly in edit form, so we don't validate it
    
    // Password validation - OPTIONAL in edit form
    const password = form.querySelector('[name="new_password"]');
    if (password.value.trim()) {
        if (password.value.trim().length < 8) {
            window.showError(password, 'Please enter a secure password. It should be at least 8 characters long, include at least one uppercase letter, one lowercase letter, one number, and one special character (!@#$%^&*()_-+=).');
            isValid = false;
        } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_\-+=])/.test(password.value.trim())) {
            window.showError(password, 'Please enter a secure password. It should be at least 8 characters long, include at least one uppercase letter, one lowercase letter, one number, and one special character (!@#$%^&*()_-+=).');
            isValid = false;
        } else {
            password.classList.add('is-valid');
        }
    }
    
    return isValid;
}

// Reset the form validation on edit modal open
$('#editStudentModal').on('show.bs.modal', function() {
    $('#editStudentForm input, #editStudentForm select, #editStudentForm textarea').removeClass('is-invalid is-valid');
    $('.error-message').remove();
    window.firstErrorElement = null;
});

// Live validation for edit form
$('#editStudentForm input, #editStudentForm select, #editStudentForm textarea').on('input change blur', function() {
    // Skip readonly fields
    if (this.readOnly) return;
    
    // Reset first error element
    window.firstErrorElement = null;
    
    // Remove existing validation classes and error messages
    $(this).removeClass('is-invalid is-valid');
    
    // Remove error message
    if ($(this).closest('.input-group').length) {
        $(this).closest('.input-group').siblings('.error-message').remove();
    } else {
        $(this).siblings('.error-message').remove();
    }
    
    const fieldName = this.name;
    const fieldValue = this.value.trim();
    
    // Skip validation for empty optional fields
    if (!fieldValue && (fieldName === 'middle_name' || fieldName === 'phone' || fieldName === 'address' || fieldName === 'new_password')) {
        return;
    }
    
    // Validate field based on its name
    switch(fieldName) {
        case 'first_name':
        case 'last_name':
            if (!fieldValue) {
                window.showError(this, 'Please enter your ' + (fieldName === 'first_name' ? 'first' : 'last') + ' name.');
            } else if (!/^[a-zA-Z\s]+$/.test(fieldValue)) {
                window.showError(this, 'Please enter your ' + (fieldName === 'first_name' ? 'first' : 'last') + ' name. It should contain only alphabetic characters and be at least 3 characters long.');
            } else if (fieldValue.length < 3) {
                window.showError(this, 'Please enter your ' + (fieldName === 'first_name' ? 'first' : 'last') + ' name. It should contain only alphabetic characters and be at least 3 characters long.');
            } else {
                $(this).addClass('is-valid');
            }
            break;
            
        case 'middle_name':
            if (fieldValue && (!/^[a-zA-Z\s]+$/.test(fieldValue))) {
                window.showError(this, 'Middle name should only contain alphabetic characters.');
            } else if (fieldValue && fieldValue.length < 2) {
                window.showError(this, 'If provided, middle name should be at least 2 characters long.');
            } else if (fieldValue) {
                $(this).addClass('is-valid');
            }
            break;
            
        case 'gender':
            if (!fieldValue) {
                window.showError(this, 'Please select a gender.');
            } else {
                $(this).addClass('is-valid');
            }
            break;
            
        case 'date_of_birth':
            if (!fieldValue) {
                window.showError(this, 'Please enter your date of birth.');
            } else {
                const dobDate = new Date(fieldValue);
                const today = new Date();
                const minDate = new Date();
                minDate.setFullYear(today.getFullYear() - 80); // Minimum age: 80 years
                const maxDate = new Date();
                maxDate.setFullYear(today.getFullYear() - 10); // Maximum age: 10 years
                
                if (dobDate > today) {
                    window.showError(this, 'Date of birth cannot be in the future.');
                } else if (dobDate < minDate) {
                    window.showError(this, 'Please enter a valid date of birth (must be less than 80 years ago).');
                } else if (dobDate > maxDate) {
                    window.showError(this, 'Student must be at least 10 years old.');
                } else {
                    $(this).addClass('is-valid');
                }
            }
            break;
            
        case 'email':
            if (!fieldValue) {
                window.showError(this, 'Please enter an email address.');
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fieldValue)) {
                window.showError(this, 'Please enter a valid email address in the format example@domain.com.');
            } else {
                $(this).addClass('is-valid');
            }
            break;
            
        case 'phone':
            if (fieldValue && !/^09\d{9}$/.test(fieldValue)) {
                window.showError(this, 'Please enter a valid phone number starting with 09 followed by 9 digits.');
            } else if (fieldValue) {
                $(this).addClass('is-valid');
            }
            break;
            
        case 'new_password':
            if (fieldValue) {
                if (fieldValue.length < 8) {
                    window.showError(this, 'Please enter a secure password. It should be at least 8 characters long, include at least one uppercase letter, one lowercase letter, one number, and one special character (!@#$%^&*()_-+=).');
                } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_\-+=])/.test(fieldValue)) {
                    window.showError(this, 'Please enter a secure password. It should be at least 8 characters long, include at least one uppercase letter, one lowercase letter, one number, and one special character (!@#$%^&*()_-+=).');
                } else {
                    $(this).addClass('is-valid');
                }
            }
            break;
    }
});
</script>

<?php include '../includes/admin_footer.php'; ?> 