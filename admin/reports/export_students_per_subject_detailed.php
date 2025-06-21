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
$filename = 'students_per_subject_detailed';
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

// Column headers - for the vertical layout
fputcsv($output, ['Report Type: Students Per Subject (Detailed)', date('Y-m-d h:i A')]);
fputcsv($output, ['']);

// Build the query for subjects with basic info
$query = "
    SELECT 
        s.id as subject_id,
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

// Complete the query with grouping and ordering
$query .= "
    GROUP BY 
        s.id, s.subject_code, s.subject_name, teacher_name
";

// Only include subjects with students if include_unassigned is false
if (!$include_unassigned) {
    $query .= " HAVING COUNT(DISTINCT e.student_id) > 0";
}

$query .= "
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
        
        // Counter for subjects
        $subject_count = 0;
        
        while ($row = $result->fetch_assoc()) {
            $subject_count++;
            
            // Check if teacher name is empty and replace with "Unassigned"
            if (empty($row['teacher_name']) || $row['teacher_name'] == ' ') {
                $row['teacher_name'] = 'Unassigned';
            }
            
            // Add a separator line if this is not the first subject
            if ($subject_count > 1) {
                fputcsv($output, ['']);
                fputcsv($output, ['--------------------------------------------------']);
                fputcsv($output, ['']);
            }
            
            // Write subject header information
            fputcsv($output, ['SUBJECT INFORMATION']);
            fputcsv($output, ['Subject Code:', $row['subject_code']]);
            fputcsv($output, ['Subject Name:', $row['subject_name']]);
            fputcsv($output, ['Teacher:', $row['teacher_name']]);
            fputcsv($output, ['Total Students:', $row['student_count']]);
            fputcsv($output, ['']);
            
            // Get the list of students for this subject
            if ($row['student_count'] > 0) {
                $students_query = "
                    SELECT 
                        s.student_id as student_number,
                        s.last_name,
                        s.first_name,
                        COALESCE(s.middle_name, '') as middle_name
                    FROM 
                        students s
                    JOIN 
                        enrollments e ON s.id = e.student_id
                    JOIN 
                        timetable tt ON e.schedule_id = tt.id
                    WHERE 
                        tt.subject_id = ?
                    ORDER BY 
                        s.last_name, s.first_name
                ";
                
                $students_stmt = $conn->prepare($students_query);
                $students_stmt->bind_param("i", $row['subject_id']);
                $students_stmt->execute();
                $students_result = $students_stmt->get_result();
                
                if ($students_result->num_rows > 0) {
                    // Write student list header
                    fputcsv($output, ['STUDENT LIST']);
                    fputcsv($output, ['#', 'Student ID', 'Last Name', 'First Name', 'Middle Name']);
                    
                    $student_number = 1;
                    while ($student = $students_result->fetch_assoc()) {
                        fputcsv($output, [
                            $student_number,
                            $student['student_number'],
                            $student['last_name'],
                            $student['first_name'],
                            $student['middle_name']
                        ]);
                        $student_number++;
                    }
                } else {
                    fputcsv($output, ['No students enrolled in this subject.']);
                }
            } else {
                fputcsv($output, ['STUDENT LIST']);
                fputcsv($output, ['No students enrolled in this subject.']);
            }
        }
        
        // Write summary at the end
        fputcsv($output, ['']);
        fputcsv($output, ['--------------------------------------------------']);
        fputcsv($output, ['']);
        fputcsv($output, ['SUMMARY']);
        fputcsv($output, ['Total Subjects:', $subject_count]);
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