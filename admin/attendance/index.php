<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Get filter parameters
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-1 month'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

// Get subjects for filter
$subjects_query = "SELECT * FROM subjects ORDER BY subject_name";
$subjects_result = $conn->query($subjects_query);

// Get teachers for filter
$teachers_query = "SELECT * FROM teachers ORDER BY last_name, first_name";
$teachers_result = $conn->query($teachers_query);

// Build the query for attendance records
$query = "SELECT 
    a.id, 
    a.attendance_date, 
    t.first_name AS teacher_first_name,
    t.last_name AS teacher_last_name,
    s.subject_name,
    COUNT(ar.id) AS student_count
FROM 
    attendance a
JOIN 
    teachers t ON a.teacher_id = t.id
JOIN 
    subjects s ON a.subject_id = s.id
LEFT JOIN 
    attendance_records ar ON a.id = ar.attendance_id
WHERE 
    1=1";

// Add filters
if ($subject_id > 0) {
    $query .= " AND a.subject_id = $subject_id";
}
if ($teacher_id > 0) {
    $query .= " AND a.teacher_id = $teacher_id";
}
$query .= " AND a.attendance_date BETWEEN '$start_date' AND '$end_date'";

// Group by and order
$query .= " GROUP BY a.id, a.attendance_date, t.first_name, t.last_name, s.subject_name
           ORDER BY a.attendance_date DESC, s.subject_name";

// Count total records for pagination
$count_query = "SELECT COUNT(DISTINCT a.id) as total FROM attendance a 
                WHERE 1=1";
if ($subject_id > 0) {
    $count_query .= " AND a.subject_id = $subject_id";
}
if ($teacher_id > 0) {
    $count_query .= " AND a.teacher_id = $teacher_id";
}
$count_query .= " AND a.attendance_date BETWEEN '$start_date' AND '$end_date'";

$count_result = $conn->query($count_query);
$total_records = $count_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_records / $records_per_page);

// Get paginated results
$query .= " LIMIT $offset, $records_per_page";
$result = $conn->query($query);

// Include header
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
                            <i class="fas fa-clipboard-check me-2"></i> Attendance Records
                        </h2>
                        <p class="text-muted mb-0">View and manage attendance records across all classes</p>
                    </div>
                </div>
            </div>
            
            <!-- Stats Row -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle text-white bg-primary me-3">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Total Records</div>
                                    <?php 
                                    $total_query = "SELECT COUNT(*) as total FROM attendance";
                                    $total_result = $conn->query($total_query);
                                    $total_data = $total_result->fetch_assoc();
                                    echo "<h4 class='mb-0'>{$total_data['total']}</h4>";
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle text-white bg-success me-3">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Recent Activity</div>
                                    <?php 
                                    $recent_query = "SELECT COUNT(*) as recent FROM attendance 
                                                    WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                                    $recent_result = $conn->query($recent_query);
                                    $recent_data = $recent_result->fetch_assoc();
                                    echo "<h4 class='mb-0'>{$recent_data['recent']} <span class='small text-muted'>past week</span></h4>";
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle text-white bg-info me-3">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">School Year</div>
                                    <h4 class="mb-0">2025-2026</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Records Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-gradient-primary-to-secondary p-4 text-white">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-list me-2"></i> Attendance List
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="GET" action="" class="mb-4" id="filterForm">
                        <!-- Hidden page input that will be reset on filter change -->
                        <input type="hidden" name="page" id="page_input" value="<?php echo $page; ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="subject_id" class="form-label">Subject</label>
                                <select name="subject_id" id="subject_id" class="form-select" onchange="resetPageAndSubmit()">
                                    <option value="0">All Subjects</option>
                                    <?php while ($subject = $subjects_result->fetch_assoc()): ?>
                                        <option value="<?php echo $subject['id']; ?>" <?php echo ($subject_id == $subject['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="teacher_id" class="form-label">Teacher</label>
                                <select name="teacher_id" id="teacher_id" class="form-select" onchange="resetPageAndSubmit()">
                                    <option value="0">All Teachers</option>
                                    <?php while ($teacher = $teachers_result->fetch_assoc()): ?>
                                        <option value="<?php echo $teacher['id']; ?>" <?php echo ($teacher_id == $teacher['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $start_date; ?>" onchange="resetPageAndSubmit()">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo $end_date; ?>" onchange="resetPageAndSubmit()">
                            </div>
                        </div>
                    </form>

                    <script>
                        // Form submission handling functions
                        function resetPageAndSubmit() {
                            // Reset to page 1 when filters change
                            document.getElementById('page_input').value = '1';
                            showLoading();
                            document.getElementById('filterForm').submit();
                        }
                        
                        function navigateToPage(pageNum) {
                            document.getElementById('page_input').value = pageNum;
                            showLoading();
                            document.getElementById('filterForm').submit();
                        }
                        
                        function showLoading() {
                            // Create and show loading overlay
                            const overlay = document.createElement('div');
                            overlay.id = 'loadingOverlay';
                            overlay.style.position = 'fixed';
                            overlay.style.top = '0';
                            overlay.style.left = '0';
                            overlay.style.width = '100%';
                            overlay.style.height = '100%';
                            overlay.style.backgroundColor = 'rgba(255, 255, 255, 0.7)';
                            overlay.style.display = 'flex';
                            overlay.style.justifyContent = 'center';
                            overlay.style.alignItems = 'center';
                            overlay.style.zIndex = '9999';
                            
                            const spinner = document.createElement('div');
                            spinner.className = 'spinner-border text-primary';
                            spinner.setAttribute('role', 'status');
                            
                            const span = document.createElement('span');
                            span.className = 'visually-hidden';
                            span.textContent = 'Loading...';
                            
                            spinner.appendChild(span);
                            overlay.appendChild(spinner);
                            document.body.appendChild(overlay);
                        }
                    </script>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Subject</th>
                                    <th scope="col">Teacher</th>
                                    <th scope="col">Students</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($record = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?php echo date('M d, Y', strtotime($record['attendance_date'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['subject_name']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($record['teacher_last_name'] . ', ' . $record['teacher_first_name']); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-success rounded-pill">
                                                    <?php echo $record['student_count']; ?> students
                                                </span>
                                            </td>
                                            <td>
                                                <a href="view.php?id=<?php echo $record['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i> View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle me-2"></i> No attendance records found for the selected filters.
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="javascript:void(0);" onclick="navigateToPage(<?php echo ($page - 1); ?>)" <?php echo ($page <= 1) ? 'tabindex="-1"' : ''; ?>>
                                        Previous
                                    </a>
                                </li>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="javascript:void(0);" onclick="navigateToPage(<?php echo $i; ?>)">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="javascript:void(0);" onclick="navigateToPage(<?php echo ($page + 1); ?>)" <?php echo ($page >= $total_pages) ? 'tabindex="-1"' : ''; ?>>
                                        Next
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?> 