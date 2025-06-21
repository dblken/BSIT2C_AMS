<?php
// Start session
session_start();
require_once '../../config/database.php';
require_once '../../includes/session_protection.php';

// Use the verify_session function to check admin session
verify_session('admin');

// Get optional filters
$program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : null;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : null;
$faculty_id = isset($_GET['faculty_id']) ? intval($_GET['faculty_id']) : null;
$year_level = isset($_GET['year_level']) ? $_GET['year_level'] : null;
$semester = isset($_GET['semester']) ? $_GET['semester'] : null;

// Create filename with filter info
$filename = 'classes_list';
if ($program_id) $filename .= '_program_' . $program_id;
if ($subject_id) $filename .= '_subject_' . $subject_id;
if ($faculty_id) $filename .= '_faculty_' . $faculty_id;
if ($year_level) $filename .= '_yearlevel_' . $year_level;
if ($semester) $filename .= '_semester_' . $semester;
$filename .= '_' . date('Y-m-d') . '.csv';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel encoding issues
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Report header
fputcsv($output, ['Report Type: Classes List', date('Y-m-d h:i A')]);
fputcsv($output, ['']);

// Add filter information if applicable
$filter_data = [];
if ($program_id) {
    $program_query = "SELECT program_code, program_name FROM programs WHERE id = ?";
    $stmt = $conn->prepare($program_query);
    $stmt->bind_param("i", $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $filter_data[] = ["Filter: Program", $row['program_code'] . ' - ' . $row['program_name']];
    }
}

if ($subject_id) {
    $subject_query = "SELECT subject_code, subject_name FROM subjects WHERE id = ?";
    $stmt = $conn->prepare($subject_query);
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $filter_data[] = ["Filter: Subject", $row['subject_code'] . ' - ' . $row['subject_name']];
    }
}

if ($faculty_id) {
    $faculty_query = "SELECT CONCAT(first_name, ' ', last_name) as faculty_name FROM faculty WHERE id = ?";
    $stmt = $conn->prepare($faculty_query);
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $filter_data[] = ["Filter: Faculty", $row['faculty_name']];
    }
}

if ($year_level) {
    $filter_data[] = ["Filter: Year Level", $year_level];
}

if ($semester) {
    $filter_data[] = ["Filter: Semester", $semester];
}

// Output filter information
foreach ($filter_data as $filter) {
    fputcsv($output, $filter);
}
if (count($filter_data) > 0) {
    fputcsv($output, ['']);
}

// Build SQL query with optional filters
$query = "
    SELECT 
        c.id,
        c.class_code,
        c.section,
        s.subject_code,
        s.subject_name,
        s.units,
        CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
        p.program_code,
        p.program_name,
        s.year_level,
        s.semester,
        c.schedule,
        c.room,
        (SELECT COUNT(e.id) FROM enrollments e WHERE e.class_id = c.id) as student_count
    FROM 
        classes c
    JOIN 
        subjects s ON c.subject_id = s.id
    JOIN 
        programs p ON s.program_id = p.id
    LEFT JOIN 
        faculty f ON c.faculty_id = f.id
    WHERE 
        1 = 1
";

$params = [];
$param_types = "";

if ($program_id) {
    $query .= " AND s.program_id = ?";
    $params[] = $program_id;
    $param_types .= "i";
}

if ($subject_id) {
    $query .= " AND c.subject_id = ?";
    $params[] = $subject_id;
    $param_types .= "i";
}

if ($faculty_id) {
    $query .= " AND c.faculty_id = ?";
    $params[] = $faculty_id;
    $param_types .= "i";
}

if ($year_level) {
    $query .= " AND s.year_level = ?";
    $params[] = $year_level;
    $param_types .= "s";
}

if ($semester) {
    $query .= " AND s.semester = ?";
    $params[] = $semester;
    $param_types .= "s";
}

$query .= " ORDER BY p.program_code, s.year_level, s.semester, s.subject_code, c.section";

try {
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Output report headers
        fputcsv($output, ['CLASSES LIST']);
        fputcsv($output, ['Total Records:', $result->num_rows]);
        fputcsv($output, ['']);
        
        // Calculate statistics
        $program_counts = [];
        $year_level_counts = [];
        $semester_counts = [];
        $faculty_counts = [];
        $total_students = 0;
        
        // First pass to calculate statistics
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
            
            // Count programs
            $program = $row['program_code'];
            if (!isset($program_counts[$program])) {
                $program_counts[$program] = 0;
            }
            $program_counts[$program]++;
            
            // Count year levels
            $year_level = $row['year_level'] ?? 'Not Set';
            if (!isset($year_level_counts[$year_level])) {
                $year_level_counts[$year_level] = 0;
            }
            $year_level_counts[$year_level]++;
            
            // Count semesters
            $semester = $row['semester'] ?? 'Not Set';
            if (!isset($semester_counts[$semester])) {
                $semester_counts[$semester] = 0;
            }
            $semester_counts[$semester]++;
            
            // Count faculty
            $faculty = $row['faculty_name'] ?? 'Not Assigned';
            if (!isset($faculty_counts[$faculty])) {
                $faculty_counts[$faculty] = 0;
            }
            $faculty_counts[$faculty]++;
            
            // Sum totals
            $total_students += (int)($row['student_count'] ?? 0);
        }
        
        // Output program distribution
        fputcsv($output, ['PROGRAM DISTRIBUTION']);
        ksort($program_counts);
        foreach ($program_counts as $program => $count) {
            $percentage = round(($count / $result->num_rows) * 100, 2);
            fputcsv($output, [$program, $count, $percentage . '%']);
        }
        fputcsv($output, ['']);
        
        // Output year level distribution
        fputcsv($output, ['YEAR LEVEL DISTRIBUTION']);
        ksort($year_level_counts);
        foreach ($year_level_counts as $year_level => $count) {
            $percentage = round(($count / $result->num_rows) * 100, 2);
            fputcsv($output, [$year_level, $count, $percentage . '%']);
        }
        fputcsv($output, ['']);
        
        // Output semester distribution
        fputcsv($output, ['SEMESTER DISTRIBUTION']);
        ksort($semester_counts);
        foreach ($semester_counts as $semester => $count) {
            $percentage = round(($count / $result->num_rows) * 100, 2);
            fputcsv($output, [$semester, $count, $percentage . '%']);
        }
        fputcsv($output, ['']);
        
        // Output faculty distribution (top 10)
        fputcsv($output, ['FACULTY DISTRIBUTION (TOP 10)']);
        arsort($faculty_counts);
        $i = 0;
        foreach ($faculty_counts as $faculty => $count) {
            if ($i++ >= 10) break;
            $percentage = round(($count / $result->num_rows) * 100, 2);
            fputcsv($output, [$faculty, $count, $percentage . '%']);
        }
        fputcsv($output, ['']);
        
        // Output summary statistics
        fputcsv($output, ['SUMMARY STATISTICS']);
        fputcsv($output, ['Total Classes:', $result->num_rows]);
        fputcsv($output, ['Total Students Enrolled:', $total_students]);
        fputcsv($output, ['Average Students Per Class:', round($total_students / $result->num_rows, 2)]);
        fputcsv($output, ['Total Programs:', count($program_counts)]);
        fputcsv($output, ['Total Faculty:', count($faculty_counts)]);
        fputcsv($output, ['Average Classes Per Faculty:', round($result->num_rows / count($faculty_counts), 2)]);
        fputcsv($output, ['']);
        
        // Output detailed classes list
        fputcsv($output, ['DETAILED CLASSES LIST']);
        fputcsv($output, [
            'Class Code',
            'Section',
            'Subject Code',
            'Subject Name',
            'Units',
            'Faculty',
            'Program',
            'Year Level',
            'Semester',
            'Schedule',
            'Room',
            'Students'
        ]);
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['class_code'] ?? '',
                $row['section'] ?? '',
                $row['subject_code'] ?? '',
                $row['subject_name'] ?? '',
                $row['units'] ?? '',
                $row['faculty_name'] ?? '',
                $row['program_code'] ?? '',
                $row['year_level'] ?? '',
                $row['semester'] ?? '',
                $row['schedule'] ?? '',
                $row['room'] ?? '',
                $row['student_count'] ?? '0'
            ]);
        }
        
        fputcsv($output, ['']);
        fputcsv($output, ['Report Generated On:', date('Y-m-d h:i A')]);
        
    } else {
        // No data found
        fputcsv($output, ['No classes data found.']);
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