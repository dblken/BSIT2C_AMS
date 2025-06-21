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
$filename = 'students_per_subject_summary';
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
fputcsv($output, ['Report Type: Students Per Subject (Summary)', date('Y-m-d h:i A')]);
fputcsv($output, ['']);

// Build the query with optional filters
$query = "
    SELECT 
        s.subject_code,
        s.subject_name,
        CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
        COUNT(DISTINCT e.student_id) as student_count
    FROM 
        subjects s
    LEFT JOIN 
        assignments a ON s.id = a.subject_id
    LEFT JOIN 
        teachers t ON a.teacher_id = t.id
    LEFT JOIN 
        timetable tt ON s.id = tt.subject_id
    LEFT JOIN 
        enrollments e ON tt.id = e.schedule_id
    WHERE 1=1
";

// Add filter conditions
$params = [];
$param_types = '';

// Add semester filter if provided
if (!empty($semester)) {
    // Check if semester column exists
    $check_semester = $conn->query("SHOW COLUMNS FROM subjects LIKE 'semester'");
    if ($check_semester->num_rows > 0) {
        $query .= " AND s.semester = ?";
        $params[] = $semester;
        $param_types .= 's';
    }
}

// Add school year filter if provided
if (!empty($school_year)) {
    // Check if school_year column exists
    $check_year = $conn->query("SHOW COLUMNS FROM subjects LIKE 'school_year'");
    if ($check_year->num_rows > 0) {
        $query .= " AND s.school_year = ?";
        $params[] = $school_year;
        $param_types .= 's';
    }
}

// Only include subjects with students if include_unassigned is false
if (!$include_unassigned) {
    $query .= " HAVING COUNT(DISTINCT e.student_id) > 0";
}

// Complete the query with grouping and ordering
$query .= "
    GROUP BY 
        s.id, s.subject_code, s.subject_name, teacher_name
    ORDER BY 
        s.subject_code
";

try {
    $stmt = $conn->prepare($query);
    
    // Bind parameters if any
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Write filter information if any filters are applied
        if (!empty($semester) || !empty($school_year)) {
            $filter_info = ['Filters Applied:'];
            if (!empty($semester)) $filter_info[] = "Semester: " . $semester;
            if (!empty($school_year)) $filter_info[] = "School Year: " . $school_year;
            fputcsv($output, $filter_info);
            fputcsv($output, ['']);
        }
        
        // Write table header
        fputcsv($output, ['ENROLLMENT SUMMARY']);
        fputcsv($output, ['']);
        fputcsv($output, ['#', 'Subject Code', 'Subject Name', 'Teacher', 'Number of Students']);
        
        $subject_number = 1;
        $total_students = 0;
        $total_subjects = 0;
        
        while ($row = $result->fetch_assoc()) {
            // Check if teacher name is empty and replace with "Unassigned"
            if (empty($row['teacher_name']) || $row['teacher_name'] == ' ') {
                $row['teacher_name'] = 'Unassigned';
            }
            
            // Write data row to CSV
            fputcsv($output, [
                $subject_number,
                $row['subject_code'],
                $row['subject_name'],
                $row['teacher_name'],
                $row['student_count']
            ]);
            
            $subject_number++;
            $total_students += $row['student_count'];
            $total_subjects++;
        }
        
        // Write footer with totals
        fputcsv($output, ['']);
        fputcsv($output, ['--------------------------------------------------']);
        fputcsv($output, ['']);
        fputcsv($output, ['SUMMARY']);
        fputcsv($output, ['Total Subjects:', $total_subjects]);
        fputcsv($output, ['Total Enrollments:', $total_students]);
        fputcsv($output, ['Average Students Per Subject:', $total_subjects > 0 ? round($total_students / $total_subjects, 2) : 0]);
        fputcsv($output, ['Report Generated On:', date('Y-m-d h:i A')]);
        
    } else {
        // No data found - write a message
        $message = 'No data found';
        if (!empty($semester)) {
            $message .= ' for semester: ' . $semester;
        }
        if (!empty($school_year)) {
            $message .= ' for school year: ' . $school_year;
        }
        fputcsv($output, [$message]);
        fputcsv($output, ['Report Generated On:', date('Y-m-d h:i A')]);
    }
} catch (Exception $e) {
    // Write error to CSV
    fputcsv($output, ['Error generating report: ' . $e->getMessage()]);
    fputcsv($output, ['Report Generated On:', date('Y-m-d h:i A')]);
}

// Close the output stream
fclose($output);
exit;
?> 