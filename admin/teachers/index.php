<?php
require_once '../../config/database.php';

// Check if AJAX request
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == 1;

// If it's an AJAX request, only include core content
if (!$is_ajax) {
    include '../includes/admin_header.php';
}

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $records_per_page;

// Get search term if provided
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get status filter if provided
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Get total number of records for pagination
$base_count_query = "SELECT COUNT(*) as total FROM teachers";
$base_query = "SELECT * FROM teachers";

// Add search and status conditions
$where_conditions = [];
$params = [];
$types = "";

if (!empty($search_term)) {
    $search_param = '%' . $search_term . '%';
    $where_conditions[] = "(teacher_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= "ssss";
}

if (!empty($status_filter) && $status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($where_conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $where_conditions);
    $base_query .= $where_clause;
    $base_count_query .= $where_clause;
}

// Add ordering
$base_query .= " ORDER BY id DESC";

// Execute count query with parameters
if (!empty($params)) {
    $count_stmt = $conn->prepare($base_count_query);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_records_result = $count_stmt->get_result();
} else {
    $total_records_result = mysqli_query($conn, $base_count_query);
}

$total_records = mysqli_fetch_assoc($total_records_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Final query with pagination
$final_query = $base_query . " LIMIT $offset, $records_per_page";

// Execute the query
if (!empty($params)) {
    $stmt = $conn->prepare($final_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = mysqli_query($conn, $final_query);
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
                            <i class="fas fa-chalkboard-teacher me-2"></i> Teacher Management
                        </h2>
                        <p class="text-muted mb-0">View, add, edit, and manage teacher information</p>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Row -->
            <div class="row mb-4">
                <?php
                // Get teacher statistics
                $totalTeachers = $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
                $activeTeachers = $conn->query("SELECT COUNT(*) as count FROM teachers WHERE status = 'Active'")->fetch_assoc()['count'];
                $inactiveTeachers = $conn->query("SELECT COUNT(*) as count FROM teachers WHERE status = 'Inactive'")->fetch_assoc()['count'];
                $onLeaveTeachers = $conn->query("SELECT COUNT(*) as count FROM teachers WHERE status = 'On Leave'")->fetch_assoc()['count'];
                ?>
                
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-info fw-bold mb-1">Total Teachers</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $totalTeachers; ?></h3>
                                </div>
                                <div class="icon-circle bg-info text-white">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-primary fw-bold mb-1">Active Teachers</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $activeTeachers; ?></h3>
                                </div>
                                <div class="icon-circle bg-primary text-white">
                                    <i class="fas fa-user-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-danger fw-bold mb-1">Inactive Teachers</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $inactiveTeachers; ?></h3>
                                </div>
                                <div class="icon-circle bg-danger text-white">
                                    <i class="fas fa-user-times"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-warning fw-bold mb-1">On Leave</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $onLeaveTeachers; ?></h3>
                                </div>
                                <div class="icon-circle bg-warning text-white">
                                    <i class="fas fa-user-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Teachers Table -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-gradient-primary-to-secondary p-4 text-white d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-list me-2"></i> Teachers List
                    </h5>
                    <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                        <i class="fas fa-user-plus me-2"></i> Add Teacher
                    </button>
                </div>
                <div class="card-body p-4">
                    <!-- Search and Filter -->
                    <div class="row mb-4 align-items-center">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap align-items-center filter-controls">
                                <div class="input-group search-group me-3 mb-2 mb-md-0" style="max-width: 400px;">
                                    <input type="text" id="searchInput" name="search" class="form-control" placeholder="Search..." 
                                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <div class="input-group-append d-flex">
                                        <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
                                        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                        <button class="btn btn-outline-secondary" id="clearSearch" type="button" title="Clear search">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <select id="statusFilter" class="form-select me-2 mb-2 mb-md-0" style="width: auto;" title="Filter by status">
                                    <option value="all">All Status</option>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                    <option value="On Leave">On Leave</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Teachers Table -->
            <div class="table-responsive">
                        <table class="table table-hover align-middle" id="teachersTable">
                    <thead>
                        <tr>
                            <th width="20%" class="sortable" data-sort="teacher_id">Teacher ID <i class="fas fa-sort ms-1"></i></th>
                            <th width="30%" class="sortable" data-sort="name">Name <i class="fas fa-sort ms-1"></i></th>
                            <th width="30%" class="sortable" data-sort="email">Email <i class="fas fa-sort ms-1"></i></th>
                            <th width="10%" class="sortable" data-sort="status">Status <i class="fas fa-sort ms-1"></i></th>
                            <th width="10%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result && mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                    // Define status badge color
                                    $statusClass = 'bg-secondary';
                                    $textClass = 'text-white';
                                    if ($row['status'] == 'Active') {
                                        $statusClass = 'bg-success';
                                        $textClass = 'text-white';
                                    } else if ($row['status'] == 'Inactive') {
                                        $statusClass = 'bg-danger';
                                        $textClass = 'text-white';
                                    } else if ($row['status'] == 'On Leave') {
                                        $statusClass = 'bg-warning';
                                        $textClass = 'text-dark';
                                    }
                                    
                            echo "<tr class='clickable-row' onclick='viewTeacher({$row['id']})' style='cursor: pointer;'>
                                            <td>" . htmlspecialchars($row['teacher_id']) . "</td>
                                            <td>
                                                <div class='d-flex align-items-center'>
                                                    <div class='icon-circle bg-light text-primary me-2' style='width: 40px; height: 40px; font-size: 1rem;'>
                                                        <i class='fas fa-user'></i>
                                                    </div>
                                                    <div>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</div>
                                                </div>
                                            </td>
                                            <td>" . htmlspecialchars($row['email']) . "</td>
                                    <td>
                                        <select class='form-select status-select {$statusClass} {$textClass}' 
                                                        onchange='event.stopPropagation(); updateStatus({$row['id']}, this.value);' 
                                                        onclick='event.stopPropagation();'>
                                            <option value='Active' " . ($row['status'] == 'Active' ? 'selected' : '') . ">Active</option>
                                            <option value='Inactive' " . ($row['status'] == 'Inactive' ? 'selected' : '') . ">Inactive</option>
                                            <option value='On Leave' " . ($row['status'] == 'On Leave' ? 'selected' : '') . ">On Leave</option>
                                        </select>
                                    </td>
                                            <td class='actions-cell'>
                                                <button type='button' class='btn btn-outline-primary btn-sm me-1' onclick='editTeacher({$row['id']}); event.stopPropagation();'>
                                                    <i class='fas fa-edit'></i>
                                                </button>
                                                <button class='btn btn-outline-danger btn-sm' onclick='showDeleteModal({$row['id']}); event.stopPropagation();'>
                                                    <i class='fas fa-trash'></i>
                                                </button>
                                    </td>
                                </tr>";
                        }
                        } else {
                            echo "<tr><td colspan='5' class='text-center py-4'>No teachers found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="d-flex justify-content-center mt-4">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
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
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Teacher Modal -->
<div class="modal fade" id="viewTeacherModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-gradient-primary-to-secondary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-chalkboard-teacher me-2"></i> Teacher Profile
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="teacher-info">
                    <div class="row">
                        <div class="col-md-4 text-center mb-4 mb-md-0">
                            <div class="avatar-circle mx-auto mb-3 bg-primary text-white">
                                <i class="fas fa-user-tie fa-4x"></i>
                            </div>
                            <h4 class="fw-bold mb-1" id="view_teacher_name"></h4>
                        <p class="text-muted" id="view_teacher_id_display"></p>
                            <div class="mt-3" id="view_status"></div>
                    </div>
                        <div class="col-md-8">
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="fas fa-info-circle me-2"></i>Personal Information</h6>
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
                                                <p id="view_phone" class="fw-medium"></p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                                        <div class="col-sm-4">
                            <div class="info-group">
                                                <label class="text-muted"><i class="fas fa-venus-mars me-2"></i>Gender</label>
                                                <p id="view_gender" class="fw-medium"></p>
                            </div>
                        </div>
                                        <div class="col-sm-4">
                                            <div class="info-group">
                                                <label class="text-muted"><i class="fas fa-birthday-cake me-2"></i>Birthday</label>
                                                <p id="view_birthday" class="fw-medium"></p>
                                            </div>
                                        </div>
                                        <div class="col-sm-4">
                            <div class="info-group">
                                                <label class="text-muted"><i class="fas fa-building me-2"></i>Department</label>
                                                <p id="view_department" class="fw-medium"></p>
                            </div>
                        </div>
                    </div>
                                </div>
                            </div>
                            
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="fas fa-book me-2"></i>Teaching Information</h6>
                                    <div class="row text-center">
                                        <div class="col-md-4">
                                            <div class="p-2">
                                                <div class="small text-muted">Subjects</div>
                                                <div class="fw-bold" id="subject_count">0</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-2">
                                                <div class="small text-muted">Classes</div>
                                                <div class="fw-bold" id="class_count">0</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-2">
                                                <div class="small text-muted">Students</div>
                                                <div class="fw-bold" id="student_count">0</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Assignments List -->
                            <div class="card bg-light mt-3">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3 border-bottom pb-2">
                                        <i class="fas fa-clipboard-list me-2"></i>Assigned Subjects
                                    </h6>
                                    <div id="teacher_assignments_container">
                                        <div class="text-center text-muted py-3" id="no_assignments_message">
                                            <i class="fas fa-info-circle fa-2x mb-2"></i>
                                            <p>No subjects assigned to this teacher yet.</p>
                                        </div>
                                        <div class="assignments-list" id="assignments_list" style="display: none;">
                                            <!-- Assignments will be loaded here -->
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
                <button type="button" class="btn btn-primary" onclick="editTeacherFromView(event)">
                    <i class="fas fa-edit me-2"></i> Edit Profile
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-gradient-primary-to-secondary text-white">
                <h5 class="modal-title fw-bold" id="addTeacherModalLabel">
                    <i class="fas fa-user-plus me-2"></i> Add New Teacher
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
            <form id="addTeacherForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="teacher_id" class="form-label">Teacher ID <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-id-card"></i>
                                </span>
                                <input type="text" class="form-control" id="teacher_id" name="teacher_id" placeholder="Enter teacher ID">
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
                            <select class="form-select" id="gender" name="gender">
                                <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                        <div class="col-md-6">
                            <label for="birthday" class="form-label">Birthday <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="birthday" name="birthday" required>
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
                            <label for="phone" class="form-label">Phone Number <small class="text-muted">(Optional)</small></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-phone"></i>
                                </span>
                                <input type="text" class="form-control" id="phone" name="phone" placeholder="e.g. 09123456789">
                        </div>
                            <small class="form-text text-muted">Must start with 09 followed by 9 digits</small>
                    </div>
                    </div>
                    
                    <h6 class="text-primary mb-3 border-bottom pb-2"><i class="fas fa-building me-2"></i>Department Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                            <select class="form-select" id="department" name="department">
                                <option value="">Select Department</option>
                                <option value="IT Department">IT Department</option>
                            </select>
                        </div>
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
                    <input type="hidden" name="status" value="Active">
                </form>
                </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="saveNewTeacher">
                    <i class="fas fa-save me-2"></i> Save Teacher
                </button>
                </div>
        </div>
    </div>
</div>

<!-- Edit Teacher Modal -->
<div class="modal fade" id="editTeacherModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-gradient-primary-to-secondary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-user-edit me-2"></i> Edit Teacher
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="editTeacherForm">
                    <input type="hidden" id="edit_id" name="id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_teacher_id" class="form-label">Teacher ID <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-id-card"></i>
                                </span>
                                <input type="text" class="form-control" id="edit_teacher_id" name="teacher_id" required>
                    </div>
                    </div>
                    </div>
                    
                    <h6 class="text-primary mb-3 border-bottom pb-2"><i class="fas fa-user me-2"></i>Personal Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                    </div>
                        <div class="col-md-4">
                            <label for="edit_middle_name" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="edit_middle_name" name="middle_name">
                    </div>
                        <div class="col-md-4">
                            <label for="edit_last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_gender" class="form-label">Gender <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_birthday" class="form-label">Birthday <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_birthday" name="birthday" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_department" class="form-label">Department</label>
                            <input type="text" class="form-control bg-light" id="edit_department" name="department" value="IT Department" readonly>
                        </div>
                    </div>
                    
                    <h6 class="text-primary mb-3 border-bottom pb-2"><i class="fas fa-address-card me-2"></i>Contact Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_email" class="form-label">Email Address <span class="text-danger">*</span></label>
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
                                <input type="tel" class="form-control" id="edit_phone" name="phone" 
                                       pattern="[0-9]{11}" placeholder="09XXXXXXXXX">
                            </div>
                        </div>
                </div>
            </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="saveEditTeacher">
                    <i class="fas fa-save me-2"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add this modal for success messages at the end of the page before the script tag -->
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
                    <p class="mb-2"><strong>Teacher account has been created successfully.</strong></p>
                    <p class="mb-0 small">The login credentials have been created based on the information provided.</p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteConfirmModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="mb-4">Are you sure you want to delete this teacher? This action cannot be undone.</p>
                <input type="hidden" id="deleteTeacherId" value="">
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-outline-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash me-2"></i>Delete Teacher
                </button>
            </div>
        </div>
    </div>
</div>

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
    
    .icon-circle.bg-primary,
    .icon-circle.bg-danger {
        background-color: var(--primary-color) !important;
    }
    
    .icon-circle.bg-success,
    .icon-circle.bg-warning {
        background-color: var(--secondary-color) !important;
        color: white !important;
    }
    
    .icon-circle.bg-info,
    .icon-circle.bg-light {
        background-color: rgba(2, 31, 63, 0.1) !important;
    }
    
    .text-primary,
    .text-danger {
        color: var(--primary-color) !important;
    }
    
    .text-success,
    .text-info,
    .text-warning {
        color: var(--secondary-color) !important;
    }
    
    .avatar-circle {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(2, 31, 63, 0.1);
    }
    
    .card {
        border-radius: 0.5rem;
        overflow: hidden;
    }
    
.clickable-row:hover {
        background-color: rgba(2, 31, 63, 0.05);
}

.teacher-info .info-group {
    margin-bottom: 1.5rem;
}

.teacher-info .info-group label {
    display: block;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.teacher-info .info-group p {
    font-size: 1rem;
    margin: 0;
    color: #333;
}

.subject-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    margin: 0.25rem;
        background-color: rgba(2, 31, 63, 0.1);
    border-radius: 20px;
    font-size: 0.875rem;
        border: 1px solid rgba(2, 31, 63, 0.2);
    }
    
    .btn-primary,
    .btn-danger {
        background-color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
    }
    
    .btn-primary:hover,
    .btn-primary:focus,
    .btn-primary:active,
    .btn-danger:hover,
    .btn-danger:focus,
    .btn-danger:active {
        background-color: var(--primary-hover) !important;
        border-color: var(--primary-hover) !important;
    }
    
    .btn-outline-secondary:hover,
    .btn-outline-secondary:focus,
    .btn-outline-secondary:active {
        background-color: var(--secondary-color) !important;
        border-color: var(--secondary-color) !important;
        color: white !important;
    }

    .status-select {
        border: none;
        font-weight: 500;
        padding: 6px 35px 6px 12px;  /* Increased right padding to create more space for the dropdown arrow */
        border-radius: 0.25rem;
        width: auto;
        min-width: 110px; /* Add minimum width to ensure "On Leave" fits properly */
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.5rem center;
        background-size: 16px 12px;
    }

    .status-select.bg-success {
        background-color: #198754 !important;
    }

    .status-select.bg-danger {
        background-color: #dc3545 !important;
    }

    .status-select.bg-warning {
        background-color: #ffc107 !important;
    }

    .status-select.text-dark {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23212529' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
        background-position: right 0.5rem center;
    }

    .status-select option {
        background-color: white;
        color: black;
            padding: 8px;
    }
    
    .modal-header.bg-gradient-primary-to-secondary,
    .card-header.bg-gradient-primary-to-secondary {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    }
    
    .modal-header.bg-primary,
    .modal-header.bg-danger {
        background-color: var(--primary-color) !important;
    }
    
    .badge.bg-success {
        background-color: #198754 !important;
    }
    
    .badge.bg-danger {
        background-color: #dc3545 !important;
    }
    
    .badge.bg-warning {
        background-color: #ffc107 !important;
        color: #212529 !important;
    }
    
    .alert-info {
        background-color: rgba(2, 31, 63, 0.1) !important;
        border-color: rgba(2, 31, 63, 0.2) !important;
        color: var(--primary-color) !important;
    }
    
    .alert-warning {
        background-color: rgba(200, 167, 126, 0.1) !important;
        border-color: rgba(200, 167, 126, 0.2) !important;
        color: var(--secondary-color) !important;
    }
    
    .bg-light {
        background-color: rgba(2, 31, 63, 0.05) !important;
    }
    
    #teachersTable th {
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 1rem;
    }
    
    #teachersTable td {
        font-size: 0.95rem;
        padding: 0.75rem 1rem;
        vertical-align: middle;
    }
    
    .pagination .page-link {
        min-width: 40px;
        text-align: center;
        color: var(--primary-color) !important;
    }
    
    .pagination .page-item.active .page-link {
        background-color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
        color: white !important;
    }
    
    .search-group .btn {
        border-color: #dee2e6;
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

#teachersTable th.sortable {
    background-color: rgba(2, 31, 63, 0.03);
    transition: background-color 0.2s, color 0.2s;
}

#teachersTable th.sortable:hover {
    background-color: rgba(2, 31, 63, 0.08);
}

#teachersTable th.sort-asc,
#teachersTable th.sort-desc {
    background-color: rgba(2, 31, 63, 0.15);
    color: var(--primary-color);
    font-weight: 700;
}

#teachersTable th.sort-asc .fa-sort::before {
    content: "\f0de"; /* fa-sort-up */
    color: var(--primary-color);
}

#teachersTable th.sort-desc .fa-sort::before {
    content: "\f0dd"; /* fa-sort-down */
    color: var(--primary-color);
}

.search-group .form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.25rem rgba(2, 31, 63, 0.25);
}

/* Style for search controls */
#clearSearch {
    border-left: none;
}

#searchButton {
    border-right: none;
}

.filter-controls .form-select {
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

.filter-controls .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.25rem rgba(2, 31, 63, 0.25);
}

.filter-controls .border-primary {
    border-color: var(--primary-color) !important;
    background-color: rgba(2, 31, 63, 0.05);
}

/* More visible sort indicators */
#teachersTable th.sortable .fa-sort {
    opacity: 0.5;
    transition: opacity 0.2s;
}

#teachersTable th.sortable:hover .fa-sort {
    opacity: 1;
}

#teachersTable th.sort-asc .fa-sort,
#teachersTable th.sort-desc .fa-sort {
    opacity: 1;
}

/* Override status colors to match student management */
.status-select.bg-success, 
.badge.bg-success {
    background-color: #198754 !important;
}

.status-select.bg-danger,
.badge.bg-danger {
    background-color: #dc3545 !important;
}

.status-select.bg-warning,
.badge.bg-warning {
    background-color: #ffc107 !important;
}

.status-select.bg-secondary,
.badge.bg-secondary {
    background-color: #6c757d !important;
}

/* Final overrides to ensure consistent status colors */
.status-select.bg-success, 
.badge.bg-success,
select.bg-success {
    background-color: #198754 !important;
    color: white !important;
}

.status-select.bg-danger, 
.badge.bg-danger,
select.bg-danger {
    background-color: #dc3545 !important;
    color: white !important;
}

.status-select.bg-warning, 
.badge.bg-warning,
select.bg-warning {
    background-color: #ffc107 !important;
    color: #212529 !important;
}

.status-select.bg-secondary, 
.badge.bg-secondary,
select.bg-secondary {
    background-color: #6c757d !important;
    color: white !important;
}

/* Assignments List Styling */
.assignments-list .list-group-item {
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.assignments-list .list-group-item:hover {
    border-left-color: var(--primary-color);
    background-color: rgba(2, 31, 63, 0.02);
}

.assignments-list .badge.bg-info {
    background-color: var(--secondary-color) !important;
}

#no_assignments_message i {
    color: var(--secondary-color);
    opacity: 0.7;
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
</style>

<script>
// Form submission for adding a teacher
$('#addTeacherForm').on('submit', function(e) {
    e.preventDefault();
    
    // Debug form data
    const formData = new FormData(this);
    console.log('Form data being submitted:');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    // Validate form
    if (!validateTeacherForm(this)) {
        return false;
    }
    
    // Submit form using AJAX
    $.ajax({
        url: 'add_teacher.php',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function(response) {
            console.log('Server response:', response);
            if (response.success) {
                // Show success modal instead of alert
                $('#successModalMessage').text('Teacher added successfully!');
                $('#credentialsInfo').show();
                
                // Close add teacher modal
                $('#addTeacherModal').modal('hide');
                
                // Show success modal
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
                
                // Add event listener for when success modal is hidden
                $('#successModal').on('hidden.bs.modal', function () {
                    location.reload();
                });
            } else {
                // Show error message
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            console.error('Response:', xhr.responseText);
            alert('An error occurred. Please try again.');
        }
    });
});

// Form validation function
function validateTeacherForm(form) {
    let isValid = true;
    
    // Clear previous error messages
    $('.error-message').remove();
    $('.is-invalid').removeClass('is-invalid');
    
    // Teacher ID validation
    const teacherId = form.querySelector('[name="teacher_id"]');
    if (!teacherId.value.trim()) {
        showError(teacherId, 'Please enter a Teacher ID.');
        isValid = false;
    } else if (!/^[a-zA-Z0-9]+$/.test(teacherId.value.trim())) {
        showError(teacherId, 'Please enter a valid Teacher ID. It should contain only alphanumeric characters and be at least 6 characters long.');
        isValid = false;
    } else if (teacherId.value.trim().length < 6) {
        showError(teacherId, 'Please enter a valid Teacher ID. It should contain only alphanumeric characters and be at least 6 characters long.');
        isValid = false;
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
    }
    
    // Middle name validation (optional)
    const middleName = form.querySelector('[name="middle_name"]');
    if (middleName.value.trim() && !/^[a-zA-Z\s]+$/.test(middleName.value.trim())) {
        showError(middleName, 'Middle name should only contain alphabetic characters.');
        isValid = false;
    } else if (middleName.value.trim() && middleName.value.trim().length < 2) {
        showError(middleName, 'If provided, middle name should be at least 2 characters long.');
        isValid = false;
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
    }
    
    // Gender validation
    const gender = form.querySelector('[name="gender"]');
    if (!gender.value) {
        showError(gender, 'Please select a gender.');
        isValid = false;
    }
    
    // Email validation
    const email = form.querySelector('[name="email"]');
    if (!email.value.trim()) {
        showError(email, 'Please enter an email address.');
        isValid = false;
    } else if (!/^[^\s@]+@[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*\.(com|net|org|edu|gov|mil|int|info|biz|name|pro|museum|coop|aero|xxx|idv|ac|edu)$/.test(email.value.trim())) {
        showError(email, 'Please enter a valid email address with a properly formatted domain name and common extension (.com, .net, .org, etc.).');
        isValid = false;
    } else if (/^[^\s@]+@[^\s@]+\.[^.\s@]+\.[^.\s@]+/.test(email.value.trim())) {
        showError(email, 'Invalid email domain. Multiple extensions are not allowed.');
        isValid = false;
    } else {
        // For form submission, we'll use a synchronous approach to check email
        let emailValid = true;
        $.ajax({
            url: 'check_teacher_email.php',
            type: 'POST',
            data: { email: email.value.trim() },
            dataType: 'json',
            async: false, // Make this synchronous for form submission
            success: function(response) {
                if (response.exists) {
                    showError(email, 'This email is already in use. Please use a different email address.');
                    emailValid = false;
                }
            }
        });
        
        if (!emailValid) {
            isValid = false;
        }
    }
    
    // Phone number validation (optional)
    const phone = form.querySelector('[name="phone"]');
    if (phone.value.trim() && !/^09\d{9}$/.test(phone.value.trim())) {
        showError(phone, 'Please enter a valid phone number starting with 09 followed by 9 digits.');
        isValid = false;
    }
    
    // Department validation
    const department = form.querySelector('[name="department"]');
    if (!department.value) {
        showError(department, 'Please select a department.');
        isValid = false;
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
    }
    
    // Birthday validation
    const birthday = form.querySelector('[name="birthday"]');
    if (!birthday.value) {
        showError(birthday, 'Please select your birthday.');
        isValid = false;
    } else if (!isValidBirthday(birthday.value)) {
        showError(birthday, 'Teacher must be between 18 and 65 years old.');
        isValid = false;
    }
    
    return isValid;
}

// Function to display error message
function showError(element, message) {
    element.classList.add('is-invalid');
    
    // Remove any existing error message for this element
    const existingErrors = document.querySelectorAll('.error-message');
    existingErrors.forEach(error => {
        const parent = error.parentElement;
        if (parent === element.parentElement || 
            parent === element.closest('.input-group')?.parentElement) {
            error.remove();
        }
    });
    
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

// Add live validation for inputs
$(document).ready(function() {
    // Make showError globally available
    window.showError = showError;
    
    // Track the latest email validation request
    let emailValidationXhr = null;
    
    // Add CSS styles for validation
    $('head').append(`
        <style>
            .is-invalid {
                border-color: #dc3545 !important;
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right calc(0.375em + 0.1875rem) center;
                background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
            }
            
            .is-valid {
                border-color: #198754 !important;
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right calc(0.375em + 0.1875rem) center;
                background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
            }
            
            .error-message {
                display: block;
                width: 100%;
                margin-top: 0.25rem;
                font-size: 0.875em;
                color: #dc3545;
            }
        </style>
    `);
    
    // Live validation for all form inputs
    $('#addTeacherForm input, #addTeacherForm select').on('input change blur', function() {
        // Reset the first error element tracking on new input
        window.firstErrorElement = null;
        
        const fieldName = this.name;
        const fieldValue = this.value.trim();
        
        // Remove existing error
        $(this).removeClass('is-invalid is-valid');
        $(this).siblings('.error-message').remove();
        if ($(this).closest('.input-group').length) {
            $(this).closest('.input-group').siblings('.error-message').remove();
        }
        
        // Skip validation for empty non-required fields
        if (!fieldValue && fieldName === 'middle_name' || fieldName === 'phone') {
            return;
        }
        
        // Validate field based on its name
        switch(fieldName) {
            case 'teacher_id':
                if (!fieldValue) {
                    showError(this, 'Please enter a Teacher ID.');
                } else if (!/^[a-zA-Z0-9]+$/.test(fieldValue)) {
                    showError(this, 'Please enter a valid Teacher ID. It should contain only alphanumeric characters and be at least 6 characters long.');
                } else if (fieldValue.length < 6) {
                    showError(this, 'Please enter a valid Teacher ID. It should contain only alphanumeric characters and be at least 6 characters long.');
                } else {
                    // Add check for existing teacher ID
                    const inputElement = this;
                    $.ajax({
                        url: 'check_teacher_id.php',
                        type: 'POST',
                        data: { teacher_id: fieldValue },
                        dataType: 'json',
                        success: function(response) {
                            if (response.exists) {
                                showError(inputElement, 'This Teacher ID already exists. Please use a different ID.');
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
            case 'department':
                if (!fieldValue) {
                    showError(this, 'Please select a ' + fieldName + '.');
                } else {
                    $(this).addClass('is-valid');
                }
                break;
                
            case 'email':
                if (!fieldValue) {
                    showError(this, 'Please enter an email address.');
                } else if (!/^[^\s@]+@[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*\.(com|net|org|edu|gov|mil|int|info|biz|name|pro|museum|coop|aero|xxx|idv|ac|edu)$/.test(fieldValue)) {
                    showError(this, 'Please enter a valid email address with a properly formatted domain name and common extension (.com, .net, .org, etc.).');
                } else if (/^[^\s@]+@[^\s@]+\.[^.\s@]+\.[^.\s@]+/.test(fieldValue)) {
                    showError(this, 'Invalid email domain. Multiple extensions are not allowed.');
                } else {
                    // Remove any existing error messages before AJAX call
                    $(this).removeClass('is-invalid').addClass('is-valid');
                    
                    // Check if email already exists using AJAX
                    const inputElement = this;
                    
                    // Abort any previous request
                    if (emailValidationXhr && emailValidationXhr.readyState !== 4) {
                        emailValidationXhr.abort();
                    }
                    
                    // Start new request
                    emailValidationXhr = $.ajax({
                        url: 'check_teacher_email.php',
                        type: 'POST',
                        data: { email: fieldValue },
                        dataType: 'json',
                        success: function(response) {
                            if (response.exists) {
                                showError(inputElement, 'This email is already in use. Please use a different email address.');
                            } else {
                                $(inputElement).addClass('is-valid');
                            }
                        },
                        error: function(xhr) {
                            // Only handle if not aborted
                            if (xhr.statusText !== 'abort') {
                                // If error occurs, we'll assume it's valid and let server-side validation catch any issues
                                $(inputElement).addClass('is-valid');
                            }
                        }
                    });
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
                    showError(this, 'Please enter a secure password. It should be at least 8 characters long, include at least one uppercase letter, one lowercase letter, one number, and one special character (e.g., !, @, #, $).');
                } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_\-+=])/.test(fieldValue)) {
                    showError(this, 'Please enter a secure password. It should be at least 8 characters long, include at least one uppercase letter, one lowercase letter, one number, and one special character (e.g., !, @, #, $).');
                } else {
                    $(this).addClass('is-valid');
                }
                break;
        }
    });
    
    // Reset the form validation on modal open
    $('#addTeacherModal').on('show.bs.modal', function() {
        $('#addTeacherForm input, #addTeacherForm select').removeClass('is-invalid is-valid');
        $('.error-message').remove();
        window.firstErrorElement = null;
    });
});

// Function to delete a teacher
function deleteTeacher(teacherId) {
    if (confirm('Are you sure you want to delete this teacher?')) {
        $.ajax({
            url: 'delete_teacher.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ teacher_id: teacherId }),
            success: function(response) {
                if (response.success) {
                    alert('Teacher deleted successfully');
            location.reload();
        } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            }
        });
    }
}

// Function to format date to a readable format
function formatDate(dateString) {
    if (!dateString) return 'Not provided';
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

// Function to view teacher details
function viewTeacher(teacherId) {
    // Load teacher data via AJAX
    $.ajax({
        url: 'get_teacher.php',
        type: 'GET',
        data: { id: teacherId },
        success: function(response) {
            if (response.success) {
                const teacher = response.teacher;
                
                // Populate modal with teacher data
                $('#view_teacher_name').text(teacher.first_name + ' ' + teacher.last_name);
                
                // Store the ID as a data attribute for the edit function
                const idElement = document.getElementById('view_teacher_id_display');
                idElement.textContent = 'Teacher ID: ' + teacher.teacher_id;
                idElement.setAttribute('data-id', teacher.id);
                
                $('#view_email').text(teacher.email || 'Not provided');
                $('#view_phone').text(teacher.phone || 'Not provided');
                $('#view_gender').text(teacher.gender || 'Not provided');
                $('#view_birthday').text(formatDate(teacher.birthday));
                $('#view_department').text(teacher.department || 'Not provided');
                
                // Set status badge
                let statusClass = 'bg-secondary';
                let textClass = 'text-white';
                if (teacher.status === 'Active') {
                    statusClass = 'bg-success';
                    textClass = 'text-white';
                } else if (teacher.status === 'Inactive') {
                    statusClass = 'bg-danger';
                    textClass = 'text-white';
                } else if (teacher.status === 'On Leave') {
                    statusClass = 'bg-warning';
                    textClass = 'text-dark';
                }
                
                $('#view_status').html('<span class="badge ' + statusClass + ' ' + textClass + '" style="font-size: 1rem; padding: 8px 15px;">' + teacher.status + '</span>');
                
                // Update teaching information with accurate counts
                $('#subject_count').text(teacher.subject_count || 0);
                $('#class_count').text(teacher.class_count || 0);
                $('#student_count').text(teacher.student_count || 0);
                
                // Populate assignments list
                if (teacher.assignments && teacher.assignments.length > 0) {
                    $('#no_assignments_message').hide();
                    const assignmentsList = $('#assignments_list');
                    assignmentsList.empty().show();
                    
                    // Create assignments HTML
                    let assignmentsHtml = '<div class="list-group">';
                    teacher.assignments.forEach(function(assignment) {
                        assignmentsHtml += `
                            <div class="list-group-item list-group-item-action p-3">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">
                                        <span class="badge bg-light text-primary border me-2">${assignment.subject_code}</span>
                                        ${assignment.subject_name}
                                    </h6>
                                    <span class="badge bg-info">${assignment.enrolled_students || 0} students</span>
                                </div>
                                <div class="d-flex flex-wrap text-muted small">
                                    <div class="me-3">
                                        <i class="fas fa-calendar-alt me-1"></i> ${assignment.formatted_schedule}
                                    </div>
                                    <div>
                                        <i class="fas fa-map-marker-alt me-1"></i> ${assignment.formatted_location}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    assignmentsHtml += '</div>';
                    assignmentsList.html(assignmentsHtml);
                } else {
                    $('#assignments_list').hide();
                    $('#no_assignments_message').show();
                }
                
                // Show modal
                $('#viewTeacherModal').modal('show');
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('An error occurred. Please try again.');
        }
    });
}

// Function to update teacher status
function updateStatus(id, newStatus) {
    fetch('update_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            teacher_id: id,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the page after successful status update
            location.reload();
        } else {
            alert(data.message || 'Error updating status');
            location.reload(); // Reload if update fails
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating status');
        location.reload();
    });
}

// Edit from View Modal
function editTeacherFromView(event) {
    // Prevent default behavior
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    // Get the teacher ID from the data attribute
    const teacherElement = document.getElementById('view_teacher_id_display');
    const teacherId = teacherElement.getAttribute('data-id');
    
    // Close view modal
    $('#viewTeacherModal').modal('hide');
    
    if (teacherId) {
        // Wait for modal to close then open edit modal
        setTimeout(() => {
            editTeacher(teacherId);
        }, 500);
    } else {
        console.error("Unable to find teacher ID for editing");
    }
}

// Save edited teacher with improved error handling
$('#saveEditTeacher').click(function() {
    // Validate form
    const form = document.getElementById('editTeacherForm');
    if (!validateEditTeacherForm(form)) {
        return;
    }
    
    // Show loading
    $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
    $(this).prop('disabled', true);
    
    // Get form data
    var formData = $('#editTeacherForm').serialize();
    
    // Submit via AJAX
    $.ajax({
        url: 'update_teacher.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Show success modal
                $('#successModalMessage').text('Teacher updated successfully!');
                $('#credentialsInfo').hide();
                
                // Close edit teacher modal
                $('#editTeacherModal').modal('hide');
                
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
                alert('Error updating teacher: ' + (response.message || error));
            } catch(e) {
                alert('Error updating teacher: ' + error + '\nCheck console for details');
                console.error('Error details:', xhr.responseText);
            }
        },
        complete: function() {
            // Reset button
            $('#saveEditTeacher').html('<i class="fas fa-save me-2"></i> Save Changes');
            $('#saveEditTeacher').prop('disabled', false);
        }
    });
});

// Form validation function for edit form
function validateEditTeacherForm(form) {
    let isValid = true;
    
    // Clear previous error messages
    $('.error-message').remove();
    $('.is-invalid').removeClass('is-invalid');
    $('.is-valid').removeClass('is-valid');
    window.firstErrorElement = null;
    
    // Teacher ID validation
    const teacherId = form.querySelector('[name="teacher_id"]');
    if (!teacherId.value.trim()) {
        window.showError(teacherId, 'Please enter a Teacher ID.');
        isValid = false;
    } else if (!/^[a-zA-Z0-9]+$/.test(teacherId.value.trim())) {
        window.showError(teacherId, 'Please enter a valid Teacher ID. It should contain only alphanumeric characters and be at least 6 characters long.');
        isValid = false;
    } else if (teacherId.value.trim().length < 6) {
        window.showError(teacherId, 'Please enter a valid Teacher ID. It should contain only alphanumeric characters and be at least 6 characters long.');
        isValid = false;
    } else {
        teacherId.classList.add('is-valid');
    }
    
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
    
    // Email validation
    const email = form.querySelector('[name="email"]');
    if (!email.value.trim()) {
        window.showError(email, 'Please enter an email address.');
        isValid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.(com|net|org|edu|gov|mil|int|info|biz|name|pro|museum|coop|aero|xxx|idv|ac|edu)$/.test(email.value.trim())) {
        window.showError(email, 'Please enter a valid email address with a common domain extension (.com, .net, .org, etc.).');
        isValid = false;
    } else if (/^[^\s@]+@[^\s@]+\.[^.\s@]+\.[^.\s@]+/.test(email.value.trim())) {
        window.showError(email, 'Invalid email domain. Multiple extensions are not allowed.');
        isValid = false;
    } else {
        // For edit form, we need to check if the email is used by another teacher
        let emailValid = true;
        const teacherId = form.querySelector('[name="id"]').value;
        
        $.ajax({
            url: 'check_teacher_email_edit.php',
            type: 'POST',
            data: { 
                email: email.value.trim(),
                teacher_id: teacherId 
            },
            dataType: 'json',
            async: false, // Make this synchronous for form submission
            success: function(response) {
                if (response.exists) {
                    window.showError(email, 'This email is already in use by another teacher. Please use a different email address.');
                    emailValid = false;
                } else {
                    email.classList.add('is-valid');
                }
            }
        });
        
        if (!emailValid) {
            isValid = false;
        }
    }
    
    // Phone number validation (optional)
    const phone = form.querySelector('[name="phone"]');
    if (phone.value.trim() && !/^09\d{9}$/.test(phone.value.trim())) {
        window.showError(phone, 'Please enter a valid phone number starting with 09 followed by 9 digits.');
        isValid = false;
    } else if (phone.value.trim()) {
        phone.classList.add('is-valid');
    }
    
    // Department validation
    const department = form.querySelector('[name="department"]');
    if (!department.value) {
        window.showError(department, 'Please select a department.');
        isValid = false;
    } else {
        department.classList.add('is-valid');
    }
    
    // Birthday validation
    const birthday = form.querySelector('[name="birthday"]');
    if (!birthday.value) {
        window.showError(birthday, 'Please select your birthday.');
        isValid = false;
    } else if (!isValidBirthday(birthday.value)) {
        window.showError(birthday, 'Teacher must be between 18 and 65 years old.');
        isValid = false;
    } else {
        birthday.classList.add('is-valid');
    }
    
    return isValid;
}

// Toggle password visibility and generate password
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
        const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_\-+=';
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
    });
    
    // Auto-generate username from teacher ID and name
    $('input[name="teacher_id"], input[name="first_name"], input[name="last_name"]').on('blur', function() {
        if ($('#username').val() === '') {
            const teacherId = $('input[name="teacher_id"]').val().trim();
            const firstName = $('input[name="first_name"]').val().trim().toLowerCase();
            const lastName = $('input[name="last_name"]').val().trim().toLowerCase();
            
            if (teacherId && firstName && lastName) {
                // Generate username from first letter of first name + last name + last 4 digits of teacher ID
                let username = firstName.charAt(0) + lastName;
                
                // Add the last 4 digits of teacher ID if it's long enough
                if (teacherId.length >= 4) {
                    username += teacherId.slice(-4);
                } else {
                    username += teacherId;
                }
                
                // Remove spaces and special characters
                username = username.replace(/[^a-z0-9]/g, '');
                
                $('#username').val(username);
                $('#username').trigger('input');
            } else if (teacherId) {
                // Fallback to just using teacher ID if names aren't provided
                $('#username').val('teacher' + teacherId.replace(/[^a-z0-9]/g, ''));
                $('#username').trigger('input');
            }
        }
    });

    // Save new teacher with improved error handling
    $('#saveNewTeacher').click(function() {
        // Validate form
        const form = document.getElementById('addTeacherForm');
        if (!validateTeacherForm(form)) {
            return;
        }
        
        // Show loading
        $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
        $(this).prop('disabled', true);
        
        // Get form data
        var formData = $('#addTeacherForm').serialize();
        
        // Submit via AJAX
        $.ajax({
            url: 'add_teacher.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success modal
                    $('#successModalMessage').text('Teacher added successfully!');
                    $('#credentialsInfo').show();
                    
                    // Reset the form
                    $('#addTeacherForm')[0].reset();
                    
                    // Close add teacher modal
                    $('#addTeacherModal').modal('hide');
                    
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
                    alert('Error adding teacher: ' + (response.message || error));
                } catch(e) {
                    alert('Error adding teacher: ' + error + '\nCheck console for details');
                }
            },
            complete: function() {
                // Reset button
                $('#saveNewTeacher').html('<i class="fas fa-save me-2"></i> Save Teacher');
                $('#saveNewTeacher').prop('disabled', false);
            }
        });
    });

    // Set up edit button click handlers
    $(document).on('click', '.edit-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const teacherId = $(this).data('id');
        console.log('Edit button clicked for teacher ID:', teacherId);
        
        if (!teacherId) {
            console.error('No teacher ID found on the edit button');
            return;
        }
        
        console.log('Making AJAX request to edit.php with ID:', teacherId);
        
        // Get the current URL path to ensure we have the right path
        const currentPath = window.location.pathname;
        const basePath = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
        const editUrl = basePath + 'edit.php';
        
        console.log('Using URL:', editUrl);
        
        // Fetch teacher data
        $.ajax({
            url: editUrl,
            type: 'GET',
            data: { id: teacherId },
            dataType: 'json',
            success: function(response) {
                console.log('Teacher data received:', response);
                if (response.success) {
                    const teacher = response.teacher;
                    console.log('Teacher data to populate:', teacher);
                    
                    // Populate edit form with all fields
                    $('#edit_id').val(teacher.id);
                    $('#edit_teacher_id').val(teacher.teacher_id);
                    $('#edit_first_name').val(teacher.first_name);
                    $('#edit_middle_name').val(teacher.middle_name || '');
                    $('#edit_last_name').val(teacher.last_name);
                    $('#edit_gender').val(teacher.gender);
                    $('#edit_birthday').val(teacher.birthday || '');
                    $('#edit_email').val(teacher.email);
                    $('#edit_phone').val(teacher.phone || '');
                    $('#edit_department').val('IT Department');
                    
                    console.log('Form populated with values:', {
                        id: $('#edit_id').val(),
                        teacher_id: $('#edit_teacher_id').val(),
                        first_name: $('#edit_first_name').val(),
                        middle_name: $('#edit_middle_name').val(),
                        last_name: $('#edit_last_name').val(),
                        gender: $('#edit_gender').val(),
                        birthday: $('#edit_birthday').val(),
                        email: $('#edit_email').val(),
                        phone: $('#edit_phone').val(),
                        department: $('#edit_department').val()
                    });
                } else {
                    alert('Error: ' + (response.message || 'Unable to fetch teacher data'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching teacher data:', error);
                console.error('Response:', xhr.responseText);
                alert('An error occurred while fetching teacher data.');
            }
        });
    });
});

// Function to edit teacher
function editTeacher(teacherId) {
    console.log('Edit teacher function called with ID:', teacherId);
    
    // Show loading state in the modal
    $('#editTeacherModal .modal-body').prepend(
        '<div id="loadingIndicator" class="text-center py-4">' +
        '<div class="spinner-border text-primary" role="status">' +
        '<span class="visually-hidden">Loading...</span>' +
        '</div>' +
        '<p class="mt-2 text-muted">Loading teacher information...</p>' +
        '</div>'
    );
    
    // Show the modal immediately with loading state
    $('#editTeacherModal').modal('show');
    
    // Fetch teacher data
    $.ajax({
        url: 'edit.php',
        type: 'GET',
        data: { id: teacherId },
        dataType: 'json',
        success: function(response) {
            // Remove loading indicator
            $('#loadingIndicator').remove();
            
            console.log('Teacher data received:', response);
            if (response.success) {
                const teacher = response.teacher;
                console.log('Teacher data to populate:', teacher);
                
                if (!teacher) {
                    alert('Error: Teacher data not found in response');
                    return;
                }
                
                // Populate edit form with all fields
                $('#edit_id').val(teacher.id);
                $('#edit_teacher_id').val(teacher.teacher_id);
                $('#edit_first_name').val(teacher.first_name);
                $('#edit_middle_name').val(teacher.middle_name || '');
                $('#edit_last_name').val(teacher.last_name);
                $('#edit_gender').val(teacher.gender);
                $('#edit_birthday').val(teacher.birthday || '');
                $('#edit_email').val(teacher.email);
                $('#edit_phone').val(teacher.phone || '');
                $('#edit_department').val('IT Department');
                
                console.log('Form populated with values:', {
                    id: $('#edit_id').val(),
                    teacher_id: $('#edit_teacher_id').val(),
                    first_name: $('#edit_first_name').val(),
                    middle_name: $('#edit_middle_name').val(),
                    last_name: $('#edit_last_name').val(),
                    gender: $('#edit_gender').val(),
                    birthday: $('#edit_birthday').val(),
                    email: $('#edit_email').val(),
                    phone: $('#edit_phone').val(),
                    department: $('#edit_department').val()
                });
            } else {
                alert('Error: ' + (response.message || 'Failed to fetch teacher data'));
                $('#editTeacherModal').modal('hide');
            }
        },
        error: function(xhr, status, error) {
            // Remove loading indicator
            $('#loadingIndicator').remove();
            
            console.error('Error fetching teacher data:', error);
            console.error('Response:', xhr.responseText);
            alert('An error occurred while fetching teacher data');
            $('#editTeacherModal').modal('hide');
        }
    });
}

// Save edited teacher data
$('#saveEditTeacher').click(function() {
    // Get form data
    const formData = $('#editTeacherForm').serialize();
    
    // Show loading state
    $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
    $(this).prop('disabled', true);
    
    // Submit via AJAX
    $.ajax({
        url: 'edit.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Show success message
                $('#successModalMessage').text('Teacher updated successfully!');
                
                // Close edit modal
                $('#editTeacherModal').modal('hide');
                
                // Show success modal
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
                
                // Reload page when success modal is closed
                $('#successModal').on('hidden.bs.modal', function () {
                    location.reload();
                });
            } else {
                alert('Error: ' + (response.message || 'Failed to update teacher'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Error updating teacher:', error);
            console.error('Response:', xhr.responseText);
            alert('An error occurred while updating the teacher');
        },
        complete: function() {
            // Reset button state
            $('#saveEditTeacher').html('<i class="fas fa-save me-2"></i> Save Changes');
            $('#saveEditTeacher').prop('disabled', false);
        }
    });
});

// ... rest of the existing code ...

// Function to validate birthday
function isValidBirthday(birthday) {
    if (!birthday) return false;
    
    const birthDate = new Date(birthday);
    const today = new Date();
    const minAge = 18;
    const maxAge = 65;
    
    // Calculate age
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    return age >= minAge && age <= maxAge;
}

// Add birthday validation to validateTeacherForm
function validateTeacherForm(form) {
    let isValid = true;
    
    // Clear previous error messages
    $('.error-message').remove();
    $('.is-invalid').removeClass('is-invalid');
    
    // ... existing validation code ...
    
    // Birthday validation
    const birthday = form.querySelector('[name="birthday"]');
    if (!birthday.value) {
        showError(birthday, 'Please select your birthday.');
        isValid = false;
    } else if (!isValidBirthday(birthday.value)) {
        showError(birthday, 'Teacher must be between 18 and 65 years old.');
        isValid = false;
    }
    
    // ... rest of the existing validation code ...
    
    return isValid;
}

// Function to format birthday as password (YYYYMMDD)
function formatBirthdayAsPassword(birthdayValue) {
    if (!birthdayValue) return '';
    return birthdayValue.replace(/-/g, '');
}

// Add event listener for birthday field to set default password
$(document).ready(function() {
    // Existing document.ready code...

    // Add birthday change handler
    $('#birthday').on('change', function() {
        const birthdayValue = $(this).val();
        if (birthdayValue) {
            const passwordField = $('#password');
            const formattedPassword = formatBirthdayAsPassword(birthdayValue);
            passwordField.val(formattedPassword);
            // Trigger password validation
            passwordField.trigger('input');
        }
    });

    // ... rest of the existing document.ready code ...
});

// ... existing code ...

// Add birthday validation to validateEditTeacherForm
function validateEditTeacherForm(form) {
    let isValid = true;
    
    // Clear previous error messages
    $('.error-message').remove();
    $('.is-invalid').removeClass('is-invalid');
    $('.is-valid').removeClass('is-valid');
    window.firstErrorElement = null;
    
    // ... existing validation code ...
    
    // Birthday validation
    const birthday = form.querySelector('[name="birthday"]');
    if (!birthday.value) {
        window.showError(birthday, 'Please select your birthday.');
        isValid = false;
    } else if (!isValidBirthday(birthday.value)) {
        window.showError(birthday, 'Teacher must be between 18 and 65 years old.');
        isValid = false;
    } else {
        birthday.classList.add('is-valid');
    }
    
    // ... rest of the existing validation code ...
    
    return isValid;
}

// Update editTeacher function to populate birthday field
function editTeacher(teacherId) {
    // ... existing code ...
    
    // Fetch teacher data
    $.ajax({
        url: editUrl,
        type: 'GET',
        data: { id: teacherId },
        dataType: 'json',
        success: function(response) {
            // Remove loading indicator
            $('#loadingIndicator').remove();
            
            console.log('Teacher data received:', response);
            if (response.success) {
                const teacher = response.teacher;
                console.log('Teacher data to populate:', teacher);
                
                if (!teacher) {
                    alert('Error: Teacher data not found in response');
                    return;
                }
                
                // Populate edit form with all fields
                $('#edit_id').val(teacher.id);
                $('#edit_teacher_id').val(teacher.teacher_id);
                $('#edit_first_name').val(teacher.first_name);
                $('#edit_middle_name').val(teacher.middle_name || '');
                $('#edit_last_name').val(teacher.last_name);
                $('#edit_gender').val(teacher.gender);
                $('#edit_birthday').val(teacher.birthday || '');
                $('#edit_email').val(teacher.email);
                $('#edit_phone').val(teacher.phone || '');
                $('#edit_department').val('IT Department');
                
                console.log('Form populated with values:', {
                    id: $('#edit_id').val(),
                    teacher_id: $('#edit_teacher_id').val(),
                    first_name: $('#edit_first_name').val(),
                    middle_name: $('#edit_middle_name').val(),
                    last_name: $('#edit_last_name').val(),
                    gender: $('#edit_gender').val(),
                    birthday: $('#edit_birthday').val(),
                    email: $('#edit_email').val(),
                    phone: $('#edit_phone').val(),
                    department: $('#edit_department').val()
                });
            } else {
                $('#editTeacherModal').modal('hide');
                alert('Error: ' + (response.message || 'Unable to fetch teacher data'));
            }
        },
        // ... rest of the existing code ...
    });
}

// Add live validation for birthday field
$('#birthday, #edit_birthday').on('input change blur', function() {
    // Remove existing error
    $(this).removeClass('is-invalid is-valid');
    $(this).siblings('.error-message').remove();
    
    const fieldValue = this.value;
    
    if (!fieldValue) {
        window.showError(this, 'Please select your birthday.');
    } else if (!isValidBirthday(fieldValue)) {
        window.showError(this, 'Teacher must be between 18 and 65 years old.');
                } else {
        $(this).addClass('is-valid');
    }
});

// Function to show delete confirmation modal
function showDeleteModal(teacherId) {
    event.stopPropagation(); // Prevent row click event
    $('#deleteTeacherId').val(teacherId);
    $('#deleteConfirmModal').modal('show');
}

// Handle delete confirmation
$('#confirmDeleteBtn').click(function() {
    const teacherId = $('#deleteTeacherId').val();
    
    // Show loading state
    $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...');
    $(this).prop('disabled', true);
    
    // Make AJAX call to delete teacher
    $.ajax({
        url: 'delete_teacher.php',
        type: 'POST',
        data: JSON.stringify({ id: teacherId }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Close delete confirmation modal
                $('#deleteConfirmModal').modal('hide');
                
                // Show success message
                $('#successModalMessage').text('Teacher deleted successfully!');
                $('#credentialsInfo').hide();
                
                // Show success modal
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
                
                // Reload page when success modal is closed
                $('#successModal').on('hidden.bs.modal', function () {
                    location.reload();
                });
            } else {
                alert('Error: ' + (response.message || 'Failed to delete teacher'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Error deleting teacher:', error);
            console.error('Response:', xhr.responseText);
            alert('An error occurred while deleting the teacher');
        },
        complete: function() {
            // Reset button state
            $('#confirmDeleteBtn').html('<i class="fas fa-trash me-2"></i>Delete Teacher');
            $('#confirmDeleteBtn').prop('disabled', false);
        }
    });
});

// ... existing code ...

// Function to format email as secure password
function formatEmailAsPassword(email) {
    if (!email) return '';
    // Capitalize first letter, add a number and special character
    return email.charAt(0).toUpperCase() + email.slice(1) + '#1';
}

// Add event listener for email field to set default password
$(document).ready(function() {
    // Existing document.ready code...

    // Add email change handler
    $('#email').on('change', function() {
        const emailValue = $(this).val();
        if (emailValue) {
            const passwordField = $('#password');
            const formattedPassword = formatEmailAsPassword(emailValue);
            passwordField.val(formattedPassword);
            // Trigger password validation
            passwordField.trigger('input');
        }
    });

    // ... rest of the existing document.ready code ...
});

// ... existing code ...

// Function to format email and birthday as secure password
function formatEmailAndBirthdayAsPassword(email, birthday) {
    if (!email || !birthday) return '';
    
    // Remove any spaces from email
    const cleanEmail = email.trim();
    
    // Format birthday as YYYYMMDD
    const cleanBirthday = birthday.replace(/-/g, '');
    
    // Combine email and birthday, capitalize first letter
    const password = cleanEmail.charAt(0).toUpperCase() + cleanEmail.slice(1) + cleanBirthday + '#';
    
    return password;
}

// Add event listeners for email and birthday fields to set default password
$(document).ready(function() {
    // Existing document.ready code...

    function updatePassword() {
        const emailValue = $('#email').val();
        const birthdayValue = $('#birthday').val();
        
        if (emailValue && birthdayValue) {
            const passwordField = $('#password');
            const formattedPassword = formatEmailAndBirthdayAsPassword(emailValue, birthdayValue);
            passwordField.val(formattedPassword);
            // Trigger password validation
            passwordField.trigger('input');
        }
    }

    // Add change handlers for both email and birthday
    $('#email, #birthday').on('change', function() {
        updatePassword();
    });

    // ... rest of the existing document.ready code ...
});

// ... existing code ...

// Search without losing focus on input
$(document).ready(function() {
    // Add existing document ready functions if needed

    // Search functionality
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
                        
                        // Also update pagination, but only the inner content of nav, not the entire nav element
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

    // Status filter functionality
    $('#statusFilter').on('change', function() {
        const statusValue = $(this).val();
        
        // Update URL without page reload
        const url = new URL(window.location);
        if (statusValue === 'all') {
            url.searchParams.delete('status');
        } else {
            url.searchParams.set('status', statusValue);
        }
        url.searchParams.set('page', '1'); // Reset to first page
        window.history.pushState({}, '', url);
        
        // Use AJAX to fetch filtered results
        $.ajax({
            url: 'index.php',
            type: 'GET',
            data: {
                status: statusValue,
                page: 1,
                ajax: 1
            },
            success: function(data) {
                try {
                    // Extract table body content
                    const tableBodyMatch = data.match(/<tbody>([\s\S]*?)<\/tbody>/);
                    if (tableBodyMatch && tableBodyMatch[1]) {
                        $('tbody').html(tableBodyMatch[1]);
                        
                        // Update pagination content, not the entire nav element
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
                console.error('Failed to fetch filtered results');
            }
        });
    });
});
</script>

<!-- Add this before the closing </body> tag -->
<script src="assets/js/edit.js"></script>

<?php if (!$is_ajax): ?>
<?php include '../includes/admin_footer.php'; ?>
<?php endif; ?>