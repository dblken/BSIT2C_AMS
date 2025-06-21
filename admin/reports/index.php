<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/session_protection.php';

// Use the verify_session function to check admin session
verify_session('admin');

// Get semesters or terms if they exist in the database
$semesters = [];
$current_semester = '';

try {
    // Check if semester/term field exists in subjects table
    $check_semester_field = $conn->query("SHOW COLUMNS FROM subjects LIKE 'semester'");
    
    if ($check_semester_field->num_rows > 0) {
        // Get unique semesters
        $semester_query = "SELECT DISTINCT semester FROM subjects WHERE semester IS NOT NULL ORDER BY semester";
        $semester_result = $conn->query($semester_query);
        
        if ($semester_result && $semester_result->num_rows > 0) {
            while ($row = $semester_result->fetch_assoc()) {
                $semesters[] = $row['semester'];
            }
            // Set current semester as the first one
            $current_semester = $semesters[0] ?? '';
        }
    }
} catch (Exception $e) {
    // Ignore errors, will just not show semester filtering
}

// Get school years if they exist
$school_years = [];
$current_year = '';

try {
    // Check if school_year field exists in subjects table
    $check_year_field = $conn->query("SHOW COLUMNS FROM subjects LIKE 'school_year'");
    
    if ($check_year_field->num_rows > 0) {
        // Get unique school years
        $year_query = "SELECT DISTINCT school_year FROM subjects WHERE school_year IS NOT NULL ORDER BY school_year DESC";
        $year_result = $conn->query($year_query);
        
        if ($year_result && $year_result->num_rows > 0) {
            while ($row = $year_result->fetch_assoc()) {
                $school_years[] = $row['school_year'];
            }
            // Set current year as the first one
            $current_year = $school_years[0] ?? '';
        }
    }
} catch (Exception $e) {
    // Ignore errors, will just not show year filtering
}

// Get active subjects count
$subjects_count = $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];

// Include header
$page_title = "Reports & Exports";
include '../includes/admin_header.php';

// Function to generate teacher report
function generateTeacherReport($conn) {
    require '../../vendor/autoload.php';
    
    // Create new Spreadsheet object
    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('AMS Admin')
        ->setLastModifiedBy('AMS Admin')
        ->setTitle('Teacher Report')
        ->setSubject('Teacher Report')
        ->setDescription('List of all registered teachers');
    
    // Add header row
    $sheet->setCellValue('A1', 'Teacher ID');
    $sheet->setCellValue('B1', 'Full Name');
    $sheet->setCellValue('C1', 'Email');
    $sheet->setCellValue('D1', 'Phone');
    $sheet->setCellValue('E1', 'Department');
    $sheet->setCellValue('F1', 'Status');
    $sheet->setCellValue('G1', 'Date Registered');
    
    // Style the header row
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4472C4']
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
        ]
    ];
    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
    
    // Get all teachers
    $query = "SELECT t.*, u.created_at as registration_date 
              FROM teachers t 
              LEFT JOIN users u ON t.user_id = u.id 
              ORDER BY t.last_name, t.first_name";
    
    $result = mysqli_query($conn, $query);
    $row_number = 2;
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Format full name
        $full_name = $row['first_name'] . ' ' . 
                    (!empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '. ' : '') . 
                    $row['last_name'];
        
        // Format date
        $registration_date = date('M d, Y', strtotime($row['registration_date']));
        
        // Add data to sheet
        $sheet->setCellValue('A' . $row_number, $row['teacher_id']);
        $sheet->setCellValue('B' . $row_number, $full_name);
        $sheet->setCellValue('C' . $row_number, $row['email']);
        $sheet->setCellValue('D' . $row_number, $row['phone'] ?? 'N/A');
        $sheet->setCellValue('E' . $row_number, $row['department']);
        $sheet->setCellValue('F' . $row_number, $row['status']);
        $sheet->setCellValue('G' . $row_number, $registration_date);
        
        // Style the status cell
        $statusColor = $row['status'] === 'Active' ? '28a745' : 'dc3545';
        $sheet->getStyle('F' . $row_number)->applyFromArray([
            'font' => ['color' => ['rgb' => $statusColor]]
        ]);
        
        $row_number++;
    }
    
    // Auto-size columns
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Set print layout
    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
    
    // Add borders to all cells with data
    $lastRow = $sheet->getHighestRow();
    $sheet->getStyle('A1:G' . $lastRow)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            ]
        ]
    ]);
    
    // Center align specific columns
    $sheet->getStyle('A2:A' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D2:D' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('F2:G' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    // Create Excel file
    $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    // Set the filename with current date
    $filename = 'Teacher_Report_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Save file to PHP output
    $writer->save('php://output');
    exit;
}
?>

<div class="container-fluid py-4">
    <div class="row mb-4 justify-content-center">
        <div class="col-12 col-xxl-10">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <h2 class="fw-bold text-primary mb-2">
                            <i class="fas fa-file-export me-2"></i> Reports & Exports
                        </h2>
                        <p class="text-muted mb-0">Generate reports for students, subjects, and monitor enrollment statistics</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Categories -->
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-10">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <ul class="nav nav-tabs" id="reportTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="student-tab" data-bs-toggle="tab" data-bs-target="#student-reports" type="button" role="tab" aria-controls="student-reports" aria-selected="true">
                                <i class="fas fa-user-graduate me-1"></i> Student Reports
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="teacher-tab" data-bs-toggle="tab" data-bs-target="#teacher-reports" type="button" role="tab" aria-controls="teacher-reports" aria-selected="false">
                                <i class="fas fa-chalkboard-teacher me-1"></i> Teacher Reports
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content mt-4" id="reportTabsContent">
                        <!-- Student Reports -->
                        <div class="tab-pane fade show active" id="student-reports" role="tabpanel" aria-labelledby="student-tab">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body position-relative p-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h5 class="fw-bold text-primary mb-0">
                                                    <i class="fas fa-list fa-fw me-2"></i>
                                                    Students Per Subject (Detailed)
                                                </h5>
                                                <div class="icon-circle bg-primary text-white">
                                                    <i class="fas fa-file-csv"></i>
                                                </div>
                                            </div>
                                            <p class="text-muted mb-3">
                                                This report provides a detailed list of students enrolled in each subject.
                                                Each record includes the subject information, assigned teacher, and a complete
                                                list of all enrolled students with their student IDs.
                                            </p>
                                            <a href="export_students_per_subject_detailed.php" class="btn btn-primary export-btn" data-report="students_per_subject_detailed">
                                                <i class="fas fa-download me-1"></i> Export CSV
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body position-relative p-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h5 class="fw-bold text-primary mb-0">
                                                    <i class="fas fa-file-csv fa-fw me-2"></i>
                                                    Students Per Subject (Summary)
                                                </h5>
                                                <div class="icon-circle bg-primary text-white">
                                                    <i class="fas fa-chart-bar"></i>
                                                </div>
                                            </div>
                                            <p class="text-muted mb-3">
                                                This summary report provides a count of student enrollment for each subject.
                                                The report includes subject code, subject name, assigned teacher, and the total 
                                                number of enrolled students.
                                            </p>
                                            <a href="export_students_per_subject.php" class="btn btn-primary export-btn" data-report="students_per_subject">
                                                <i class="fas fa-download me-1"></i> Export CSV
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body position-relative p-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h5 class="fw-bold text-primary mb-0">
                                                    <i class="fas fa-users fa-fw me-2"></i>
                                                    All Registered Students
                                                </h5>
                                                <div class="icon-circle bg-primary text-white">
                                                    <i class="fas fa-user-graduate"></i>
                                                </div>
                                            </div>
                                            <p class="text-muted mb-3">
                                                This report provides a comprehensive list of all registered students in the system.
                                                Each record includes student ID, full name, program, year level, and contact information.
                                            </p>
                                            <a href="export_student_list.php" class="btn btn-primary export-btn" data-report="student_list">
                                                <i class="fas fa-download me-1"></i> Export CSV
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body position-relative p-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h5 class="fw-bold text-primary mb-0">
                                                    <i class="fas fa-user-plus fa-fw me-2"></i>
                                                    Enrollment Summary
                                                </h5>
                                                <div class="icon-circle bg-primary text-white">
                                                    <i class="fas fa-chart-pie"></i>
                                                </div>
                                            </div>
                                            <p class="text-muted mb-3">
                                                This report provides a summary of all student enrollments across programs and subjects.
                                                Get statistics on enrollment trends, program distribution, and enrollment status.
                                            </p>
                                            <a href="export_enrollment_summary.php" class="btn btn-primary export-btn" data-report="enrollment_summary">
                                                <i class="fas fa-download me-1"></i> Export CSV
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Teacher Reports -->
                        <div class="tab-pane fade" id="teacher-reports" role="tabpanel" aria-labelledby="teacher-tab">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body position-relative p-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h5 class="fw-bold text-primary mb-0">
                                                    <i class="fas fa-chalkboard-teacher fa-fw me-2"></i>
                                                    Teacher Assignments
                                                </h5>
                                                <div class="icon-circle bg-primary text-white">
                                                    <i class="fas fa-tasks"></i>
                                                </div>
                                            </div>
                                            <p class="text-muted mb-3">
                                                This report shows all subject assignments for teachers, including subject details,
                                                schedules, and the number of enrolled students per class.
                                            </p>
                                            <a href="export_teacher_assignments.php" class="btn btn-primary export-btn" data-report="teacher_assignments">
                                                <i class="fas fa-download me-1"></i> Export CSV
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body position-relative p-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h5 class="fw-bold text-primary mb-0">
                                                    <i class="fas fa-users fa-fw me-2"></i>
                                                    All Registered Teachers
                                                </h5>
                                                <div class="icon-circle bg-primary text-white">
                                                    <i class="fas fa-user-tie"></i>
                                                </div>
                                            </div>
                                            <p class="text-muted mb-3">
                                                This report provides a comprehensive list of all registered teachers in the system.
                                                Each record includes teacher ID, full name, department, specialization, and contact information.
                                            </p>
                                            <a href="export_teacher_list.php" class="btn btn-primary export-btn" data-report="teacher_list">
                                                <i class="fas fa-download me-1"></i> Export CSV
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Attendance Reports -->
                        <div class="tab-pane fade pt-4" id="attendance-reports" role="tabpanel" aria-labelledby="attendance-tab">
                            <div class="row">
                                <!-- Attendance reports removed as requested -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Export buttons should go directly to the export pages
    const exportButtons = document.querySelectorAll('.export-btn');
    exportButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Allow the normal link behavior without modification
            return true;
        });
    });
    
    // Show active tab based on report type
    const urlParams = new URLSearchParams(window.location.search);
    const reportType = urlParams.get('report_type');
    
    if (reportType) {
        if (reportType.includes('teacher')) {
            document.getElementById('teacher-tab').click();
        } else {
            document.getElementById('student-tab').click();
        }
    }
});
</script>

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
    
    /* Restored original tab styles */
    .nav-tabs {
        border-bottom: 1px solid #dee2e6;
        margin-bottom: 1rem;
    }
    
    .nav-tabs .nav-link {
        margin-bottom: -1px;
        background-color: #e6eeff;
        border: 1px solid #ccdeff;
        border-top-left-radius: 0.25rem;
        border-top-right-radius: 0.25rem;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        transition: all 0.2s ease;
        color: #021F3F;
    }
    
    .nav-tabs .nav-link.active {
        color: #fff;
        background-color: #021F3F;
        border-color: #021F3F;
        font-weight: 600;
        box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1);
        position: relative;
        z-index: 2;
    }
    
    .nav-tabs .nav-link:not(.active) {
        color: #021F3F !important;
        background-color: #e6eeff;
        font-weight: 500;
    }
    
    .nav-tabs .nav-link:hover:not(.active) {
        color: #021F3F;
        background-color: #d1e0ff;
        border-color: #b8ceff;
        font-weight: 600;
    }
    
    .card {
        transition: transform 0.2s ease-in-out;
        overflow: hidden;
        border-radius: 0.5rem;
    }
    
    .card:hover {
        transform: translateY(-5px);
    }
    
    @media (max-width: 768px) {
        .nav-tabs {
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .nav-tabs .nav-link {
            white-space: nowrap;
        }
    }
</style>

<?php include '../includes/admin_footer.php'; ?> 