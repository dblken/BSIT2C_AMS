<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check if an ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$attendance_id = intval($_GET['id']);

// Get attendance details
$query = "SELECT 
    a.id, 
    a.attendance_date, 
    a.assignment_id,
    t.id AS teacher_id,
    t.first_name AS teacher_first_name,
    t.last_name AS teacher_last_name,
    s.id AS subject_id,
    s.subject_name
FROM 
    attendance a
JOIN 
    teachers t ON a.teacher_id = t.id
JOIN 
    subjects s ON a.subject_id = s.id
WHERE 
    a.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $attendance_id);
$stmt->execute();
$result = $stmt->get_result();

// If attendance record not found, redirect
if ($result->num_rows === 0) {
    header("Location: index.php");
    exit();
}

$attendance = $result->fetch_assoc();

// Get attendance records for this attendance
$records_query = "SELECT 
    ar.id,
    ar.status,
    ar.remarks,
    st.id AS student_id,
    st.student_id AS student_number,
    st.first_name,
    st.middle_name,
    st.last_name
FROM 
    attendance_records ar
JOIN 
    students st ON ar.student_id = st.id
WHERE 
    ar.attendance_id = ?
ORDER BY 
    st.last_name, st.first_name";

$stmt = $conn->prepare($records_query);
$stmt->bind_param("i", $attendance_id);
$stmt->execute();
$records_result = $stmt->get_result();

// Calculate summary
$summary = [
    'total' => $records_result->num_rows,
    'present' => 0,
    'late' => 0,
    'absent' => 0,
    'excused' => 0
];

// Store records in array
$student_records = [];
while ($record = $records_result->fetch_assoc()) {
    $student_records[] = $record;
    
    // Count by status
    if (isset($record['status'])) {
        $status = strtolower($record['status']);
        if (isset($summary[$status])) {
            $summary[$status]++;
        }
    }
}

// Include header
include '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-10">
            <!-- Page Header -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="fw-bold text-primary mb-2">
                                <i class="fas fa-clipboard-check me-2"></i> Attendance Details
                            </h2>
                            <p class="text-muted mb-0">
                                <span class="fw-bold">Date:</span> <?php echo date('F d, Y (l)', strtotime($attendance['attendance_date'])); ?>
                            </p>
                        </div>
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i> Back to Attendance Records
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light p-3">
                            <h5 class="card-title fw-bold mb-0">
                                <i class="fas fa-info-circle me-2"></i>Class Information
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="icon-circle text-white bg-primary me-3">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div>
                                        <label class="fw-bold text-primary mb-0">Date:</label>
                                        <div class="fs-5">
                                            <?php echo date('F d, Y', strtotime($attendance['attendance_date'])); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?php echo date('l', strtotime($attendance['attendance_date'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold text-muted">Subject:</label>
                                <div class="fs-5">
                                    <?php echo htmlspecialchars($attendance['subject_name']); ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold text-muted">Teacher:</label>
                                <div class="fs-5">
                                    <?php echo htmlspecialchars($attendance['teacher_first_name'] . ' ' . $attendance['teacher_last_name']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title fw-bold mb-3">Attendance Summary</h5>
                            <div class="row g-2">
                                <div class="col-md-6 col-lg-3">
                                    <div class="card bg-light mb-3">
                                        <div class="card-body text-center py-3">
                                            <h2 class="card-title mb-0"><?php echo $summary['total']; ?></h2>
                                            <p class="card-text text-muted mb-0">Total</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="card bg-success text-white mb-3">
                                        <div class="card-body text-center py-3">
                                            <h2 class="card-title mb-0"><?php echo $summary['present']; ?></h2>
                                            <p class="card-text mb-0">Present</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="card bg-warning mb-3">
                                        <div class="card-body text-center py-3">
                                            <h2 class="card-title mb-0"><?php echo $summary['late']; ?></h2>
                                            <p class="card-text mb-0">Late</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="card bg-danger text-white mb-3">
                                        <div class="card-body text-center py-3">
                                            <h2 class="card-title mb-0"><?php echo $summary['absent']; ?></h2>
                                            <p class="card-text mb-0">Absent</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-gradient-primary-to-secondary p-4 text-white">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-users me-2"></i> Student Attendance Records
                        <span class="badge bg-light text-primary ms-2"><?php echo date('F d, Y', strtotime($attendance['attendance_date'])); ?></span>
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-info mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle me-3 fa-lg"></i>
                            <div>
                                <strong>Attendance Date:</strong> <?php echo date('F d, Y (l)', strtotime($attendance['attendance_date'])); ?><br>
                                <small>Showing attendance records for <?php echo htmlspecialchars($attendance['subject_name']); ?> class taught by <?php echo htmlspecialchars($attendance['teacher_first_name'] . ' ' . $attendance['teacher_last_name']); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="attendanceTable">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Student ID</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($student_records) > 0): ?>
                                    <?php foreach ($student_records as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['student_number']); ?></td>
                                            <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                            <td>
                                                <?php 
                                                    $status_class = 'bg-secondary';
                                                    switch (strtolower($record['status'])) {
                                                        case 'present':
                                                            $status_class = 'bg-success';
                                                            break;
                                                        case 'late':
                                                            $status_class = 'bg-warning';
                                                            break;
                                                        case 'absent':
                                                            $status_class = 'bg-danger';
                                                            break;
                                                        case 'excused':
                                                            $status_class = 'bg-info';
                                                            break;
                                                        default:
                                                            $status_class = 'bg-secondary';
                                                    }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($record['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo !empty($record['remarks']) ? htmlspecialchars($record['remarks']) : '<small class="text-muted">No remarks</small>'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle me-2"></i> No student attendance records found.
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Print and Export Options -->
                    <div class="mt-4 d-flex justify-content-end">
                        <button type="button" class="btn btn-outline-secondary me-2" onclick="printAttendance()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="exportToCSV()">
                            <i class="fas fa-download me-2"></i>Download CSV
                        </button>
                    </div>
                    
                    <script>
                        function printAttendance() {
                            // Prepare print view
                            let printContents = document.createElement('div');
                            printContents.innerHTML = `
                                <h2 style="text-align: center; margin-bottom: 20px;">Attendance Record</h2>
                                <p style="text-align: center; margin-bottom: 20px;">
                                    <strong>Date:</strong> <?php echo date('F d, Y (l)', strtotime($attendance['attendance_date'])); ?><br>
                                    <strong>Subject:</strong> <?php echo htmlspecialchars($attendance['subject_name']); ?><br>
                                    <strong>Teacher:</strong> <?php echo htmlspecialchars($attendance['teacher_first_name'] . ' ' . $attendance['teacher_last_name']); ?>
                                </p>
                            `;
                            
                            // Clone the table
                            let tableClone = document.getElementById('attendanceTable').cloneNode(true);
                            tableClone.style.width = '100%';
                            tableClone.style.borderCollapse = 'collapse';
                            
                            // Add styles to the table
                            let styleElement = document.createElement('style');
                            styleElement.innerHTML = `
                                table { border-collapse: collapse; width: 100%; }
                                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                                th { background-color: #f2f2f2; }
                            `;
                            printContents.appendChild(styleElement);
                            printContents.appendChild(tableClone);
                            
                            // Print the content
                            let originalContents = document.body.innerHTML;
                            document.body.innerHTML = printContents.innerHTML;
                            window.print();
                            document.body.innerHTML = originalContents;
                        }
                        
                        function exportToCSV() {
                            // Get table data
                            let table = document.getElementById('attendanceTable');
                            let rows = table.querySelectorAll('tr');
                            let csvContent = "data:text/csv;charset=utf-8,";
                            
                            // Add header with information
                            csvContent += "Attendance Record\n";
                            csvContent += "Date,<?php echo date('F d Y', strtotime($attendance['attendance_date'])); ?>\n";
                            csvContent += "Subject,<?php echo addslashes($attendance['subject_name']); ?>\n";
                            csvContent += "Teacher,<?php echo addslashes($attendance['teacher_first_name'] . ' ' . $attendance['teacher_last_name']); ?>\n\n";
                            
                            // Add table headers and rows
                            rows.forEach(function(row) {
                                let rowData = [];
                                let cells = row.querySelectorAll('th, td');
                                cells.forEach(function(cell) {
                                    // Remove HTML tags and prepare cell text
                                    let cellText = cell.textContent.trim().replace(/\n/g, ' ');
                                    rowData.push('"' + cellText.replace(/"/g, '""') + '"');
                                });
                                csvContent += rowData.join(',') + '\n';
                            });
                            
                            // Create download link
                            let encodedUri = encodeURI(csvContent);
                            let link = document.createElement("a");
                            link.setAttribute("href", encodedUri);
                            link.setAttribute("download", "attendance_<?php echo date('Y-m-d', strtotime($attendance['attendance_date'])); ?>.csv");
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        }
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?> 