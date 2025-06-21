<?php
// Start session
session_start();
require_once '../../config/database.php';
require_once '../../includes/session_protection.php';

// Use the verify_session function to check admin session
verify_session('admin');

// Get filter parameters
$semester = isset($_GET['semester']) ? $_GET['semester'] : '';
$school_year = isset($_GET['school_year']) ? $_GET['school_year'] : '';
$include_unassigned = isset($_GET['include_unassigned']) ? (bool)$_GET['include_unassigned'] : true;

// Set filename with optional filter info
$filename = 'enrollment_summary';
if (!empty($semester)) {
    $filename .= '_' . preg_replace('/[^a-zA-Z0-9]/', '', $semester);
}
if (!empty($school_year)) {
    $filename .= '_' . preg_replace('/[^a-zA-Z0-9]/', '', $school_year);
}
$filename .= '_' . date('Y-m-d') . '.csv';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel encoding issues
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Report header
fputcsv($output, ['Report Type: Enrollment Summary', date('Y-m-d h:i A')]);
fputcsv($output, ['']);

try {
    // Check if any filters are applied and display them
    if (!empty($semester) || !empty($school_year)) {
        $filter_info = ['Filters Applied:'];
        if (!empty($semester)) $filter_info[] = "Semester: " . $semester;
        if (!empty($school_year)) $filter_info[] = "School Year: " . $school_year;
        fputcsv($output, $filter_info);
        fputcsv($output, ['']);
    }

    // 1. Get overall enrollment count
    $total_query = "SELECT COUNT(*) as total FROM student_subjects";
    $total_params = [];
    $total_types = "";
    
    if (!empty($semester) || !empty($school_year)) {
        $total_query = "
            SELECT COUNT(*) as total 
            FROM student_subjects ss
            JOIN subjects s ON ss.subject_id = s.id
            WHERE 1=1
        ";
        
        if (!empty($semester)) {
            $total_query .= " AND s.semester = ?";
            $total_params[] = $semester;
            $total_types .= "s";
        }
        
        if (!empty($school_year)) {
            $total_query .= " AND s.school_year = ?";
            $total_params[] = $school_year;
            $total_types .= "s";
        }
    }
    
    if (!empty($total_params)) {
        $stmt = $conn->prepare($total_query);
        $stmt->bind_param($total_types, ...$total_params);
        $stmt->execute();
        $total_result = $stmt->get_result();
    } else {
        $total_result = $conn->query($total_query);
    }
    
    $total_enrollment = $total_result->fetch_assoc()['total'];
    
    // 2. Get enrollment per program
    $program_query = "
        SELECT 
            s.program, 
            COUNT(ss.student_id) as enrollment_count
        FROM 
            student_subjects ss
        JOIN 
            students s ON ss.student_id = s.id
    ";
    
    $program_params = [];
    $program_types = "";
    $program_where = [];
    
    if (!empty($semester) || !empty($school_year)) {
        $program_query = "
            SELECT 
                s.program, 
                COUNT(ss.student_id) as enrollment_count
            FROM 
                student_subjects ss
            JOIN 
                students s ON ss.student_id = s.id
            JOIN 
                subjects sub ON ss.subject_id = sub.id
            WHERE 1=1
        ";
        
    if (!empty($semester)) {
            $program_where[] = "sub.semester = ?";
            $program_params[] = $semester;
            $program_types .= "s";
        }
        
        if (!empty($school_year)) {
            $program_where[] = "sub.school_year = ?";
            $program_params[] = $school_year;
            $program_types .= "s";
        }
    }
    
    if (!empty($program_where)) {
        $program_query .= " AND " . implode(" AND ", $program_where);
    }
    
    $program_query .= " GROUP BY s.program ORDER BY enrollment_count DESC";
    
    if (!empty($program_params)) {
        $stmt = $conn->prepare($program_query);
        $stmt->bind_param($program_types, ...$program_params);
        $stmt->execute();
        $program_result = $stmt->get_result();
    } else {
        $program_result = $conn->query($program_query);
    }
    
    // 3. Get enrollment per year level
    $year_query = "
    SELECT 
        s.year_level,
            COUNT(ss.student_id) as enrollment_count
        FROM 
            student_subjects ss
        JOIN 
            students s ON ss.student_id = s.id
    ";
    
    $year_params = [];
    $year_types = "";
    $year_where = [];
    
    if (!empty($semester) || !empty($school_year)) {
        $year_query = "
            SELECT 
                s.year_level, 
                COUNT(ss.student_id) as enrollment_count
            FROM 
                student_subjects ss
            JOIN 
                students s ON ss.student_id = s.id
            JOIN 
                subjects sub ON ss.subject_id = sub.id
            WHERE 1=1
        ";
        
        if (!empty($semester)) {
            $year_where[] = "sub.semester = ?";
            $year_params[] = $semester;
            $year_types .= "s";
        }
        
        if (!empty($school_year)) {
            $year_where[] = "sub.school_year = ?";
            $year_params[] = $school_year;
            $year_types .= "s";
        }
    }
    
    if (!empty($year_where)) {
        $year_query .= " AND " . implode(" AND ", $year_where);
    }
    
    $year_query .= " GROUP BY s.year_level ORDER BY s.year_level";
    
    if (!empty($year_params)) {
        $stmt = $conn->prepare($year_query);
        $stmt->bind_param($year_types, ...$year_params);
        $stmt->execute();
        $year_result = $stmt->get_result();
    } else {
        $year_result = $conn->query($year_query);
    }
    
    // 4. Get enrollment per status
    $status_query = "
        SELECT 
            ss.status, 
            COUNT(ss.student_id) as enrollment_count
        FROM 
            student_subjects ss
    ";
    
    $status_params = [];
    $status_types = "";
    $status_where = [];
    
    if (!empty($semester) || !empty($school_year)) {
        $status_query = "
            SELECT 
                ss.status, 
                COUNT(ss.student_id) as enrollment_count
            FROM 
                student_subjects ss
            JOIN 
                subjects sub ON ss.subject_id = sub.id
            WHERE 1=1
        ";
        
        if (!empty($semester)) {
            $status_where[] = "sub.semester = ?";
            $status_params[] = $semester;
            $status_types .= "s";
}

if (!empty($school_year)) {
            $status_where[] = "sub.school_year = ?";
            $status_params[] = $school_year;
            $status_types .= "s";
        }
    }
    
    if (!empty($status_where)) {
        $status_query .= " AND " . implode(" AND ", $status_where);
    }
    
    $status_query .= " GROUP BY ss.status";
    
    if (!empty($status_params)) {
        $stmt = $conn->prepare($status_query);
        $stmt->bind_param($status_types, ...$status_params);
        $stmt->execute();
        $status_result = $stmt->get_result();
    } else {
        $status_result = $conn->query($status_query);
    }
    
    // 5. Get Top 10 subjects by enrollment
    $subjects_query = "
        SELECT 
            s.subject_code,
            s.subject_name,
            COUNT(ss.student_id) as enrollment_count
        FROM 
            student_subjects ss
        JOIN 
            subjects s ON ss.subject_id = s.id
    ";
    
    $subjects_params = [];
    $subjects_types = "";
    $subjects_where = [];
    
    if (!empty($semester)) {
        $subjects_where[] = "s.semester = ?";
        $subjects_params[] = $semester;
        $subjects_types .= "s";
    }
    
    if (!empty($school_year)) {
        $subjects_where[] = "s.school_year = ?";
        $subjects_params[] = $school_year;
        $subjects_types .= "s";
    }
    
    if (!empty($subjects_where)) {
        $subjects_query .= " WHERE " . implode(" AND ", $subjects_where);
    }
    
    $subjects_query .= " GROUP BY s.id, s.subject_code, s.subject_name ORDER BY enrollment_count DESC LIMIT 10";
    
    if (!empty($subjects_params)) {
        $stmt = $conn->prepare($subjects_query);
        $stmt->bind_param($subjects_types, ...$subjects_params);
    $stmt->execute();
        $subjects_result = $stmt->get_result();
    } else {
        $subjects_result = $conn->query($subjects_query);
    }
    
    // Output the results
    
    // Overall statistics
    fputcsv($output, ['ENROLLMENT SUMMARY']);
    fputcsv($output, ['']);
    fputcsv($output, ['Total Enrollments:', $total_enrollment]);
        fputcsv($output, ['']);
        
    // Program-wise enrollment
    if ($program_result && $program_result->num_rows > 0) {
        fputcsv($output, ['ENROLLMENT BY PROGRAM']);
        fputcsv($output, ['Program', 'Number of Enrollments', 'Percentage']);
        
        while ($row = $program_result->fetch_assoc()) {
            $percentage = ($total_enrollment > 0) ? round(($row['enrollment_count'] / $total_enrollment) * 100, 2) : 0;
            fputcsv($output, [
                $row['program'],
                $row['enrollment_count'],
                $percentage . '%'
            ]);
        }
                fputcsv($output, ['']);
            }
            
    // Year-level wise enrollment
    if ($year_result && $year_result->num_rows > 0) {
        fputcsv($output, ['ENROLLMENT BY YEAR LEVEL']);
        fputcsv($output, ['Year Level', 'Number of Enrollments', 'Percentage']);
        
        while ($row = $year_result->fetch_assoc()) {
            $percentage = ($total_enrollment > 0) ? round(($row['enrollment_count'] / $total_enrollment) * 100, 2) : 0;
            fputcsv($output, [
                $row['year_level'],
                $row['enrollment_count'],
                $percentage . '%'
            ]);
        }
            fputcsv($output, ['']);
    }
    
    // Status-wise enrollment
    if ($status_result && $status_result->num_rows > 0) {
        fputcsv($output, ['ENROLLMENT BY STATUS']);
        fputcsv($output, ['Status', 'Number of Enrollments', 'Percentage']);
        
        while ($row = $status_result->fetch_assoc()) {
            $percentage = ($total_enrollment > 0) ? round(($row['enrollment_count'] / $total_enrollment) * 100, 2) : 0;
                fputcsv($output, [
                $row['status'],
                $row['enrollment_count'],
                $percentage . '%'
            ]);
        }
        fputcsv($output, ['']);
    }
    
    // Top subjects
    if ($subjects_result && $subjects_result->num_rows > 0) {
        fputcsv($output, ['TOP 10 SUBJECTS BY ENROLLMENT']);
        fputcsv($output, ['#', 'Subject Code', 'Subject Name', 'Enrollment Count']);
        
        $count = 1;
        while ($row = $subjects_result->fetch_assoc()) {
            fputcsv($output, [
                $count,
                $row['subject_code'],
                $row['subject_name'],
                $row['enrollment_count']
            ]);
            $count++;
        }
        fputcsv($output, ['']);
    }
    
    // Report footer
        fputcsv($output, ['Report Generated On:', date('Y-m-d h:i A')]);
        
} catch (Exception $e) {
    // Write error to CSV
    fputcsv($output, ['Error generating report: ' . $e->getMessage()]);
    fputcsv($output, ['Report Generated On:', date('Y-m-d h:i A')]);
}

// Close the output stream
fclose($output);
exit;
?> 