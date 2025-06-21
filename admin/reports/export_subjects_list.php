<?php
// Start session
session_start();
require_once '../../config/database.php';
require_once '../../includes/session_protection.php';

// Use the verify_session function to check admin session
verify_session('admin');

// Get optional filters
$program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : null;
$year_level = isset($_GET['year_level']) ? $_GET['year_level'] : null;
$semester = isset($_GET['semester']) ? $_GET['semester'] : null;

// Create filename with filter info
$filename = 'subjects_list';
if ($program_id) $filename .= '_program_' . $program_id;
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
fputcsv($output, ['Report Type: Subjects List', date('Y-m-d h:i A')]);
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
        s.id,
        s.subject_code,
        s.subject_name,
        s.description,
        s.units,
        p.program_code,
        p.program_name,
        s.year_level,
        s.semester,
        s.prerequisites,
        (SELECT COUNT(c.id) FROM classes c WHERE c.subject_id = s.id) as class_count,
        (SELECT COUNT(DISTINCT c.faculty_id) FROM classes c WHERE c.subject_id = s.id) as faculty_count,
        (SELECT COUNT(e.id) FROM enrollments e JOIN classes c ON e.class_id = c.id WHERE c.subject_id = s.id) as student_count
    FROM 
        subjects s
    JOIN 
        programs p ON s.program_id = p.id
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

$query .= " ORDER BY p.program_code, s.year_level, s.semester, s.subject_code";

try {
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Output report headers
        fputcsv($output, ['SUBJECTS LIST']);
        fputcsv($output, ['Total Records:', $result->num_rows]);
        fputcsv($output, ['']);
        
        // Calculate statistics
        $program_counts = [];
        $year_level_counts = [];
        $semester_counts = [];
        $units_distribution = [];
        $total_units = 0;
        $total_classes = 0;
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
            
            // Count units distribution
            $units = (int)($row['units'] ?? 0);
            if (!isset($units_distribution[$units])) {
                $units_distribution[$units] = 0;
            }
            $units_distribution[$units]++;
            
            // Sum totals
            $total_units += $units;
            $total_classes += (int)($row['class_count'] ?? 0);
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
        
        // Output units distribution
        fputcsv($output, ['UNITS DISTRIBUTION']);
        ksort($units_distribution);
        foreach ($units_distribution as $units => $count) {
            $percentage = round(($count / $result->num_rows) * 100, 2);
            fputcsv($output, [$units . ' units', $count, $percentage . '%']);
        }
        fputcsv($output, ['']);
        
        // Output summary statistics
        fputcsv($output, ['SUMMARY STATISTICS']);
        fputcsv($output, ['Total Subjects:', $result->num_rows]);
        fputcsv($output, ['Total Units (all subjects):', $total_units]);
        fputcsv($output, ['Average Units Per Subject:', round($total_units / $result->num_rows, 2)]);
        fputcsv($output, ['Total Classes Created:', $total_classes]);
        fputcsv($output, ['Average Classes Per Subject:', round($total_classes / $result->num_rows, 2)]);
        fputcsv($output, ['Total Student Enrollments:', $total_students]);
        fputcsv($output, ['Average Students Per Subject:', round($total_students / $result->num_rows, 2)]);
        fputcsv($output, ['']);
        
        // Output detailed subjects list
        fputcsv($output, ['DETAILED SUBJECTS LIST']);
        fputcsv($output, [
            'Subject Code',
            'Subject Name',
            'Description',
            'Units',
            'Program',
            'Year Level',
            'Semester',
            'Prerequisites',
            'Classes',
            'Faculty',
            'Students'
        ]);
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['subject_code'] ?? '',
                $row['subject_name'] ?? '',
                $row['description'] ?? '',
                $row['units'] ?? '',
                $row['program_code'] ?? '',
                $row['year_level'] ?? '',
                $row['semester'] ?? '',
                $row['prerequisites'] ?? '',
                $row['class_count'] ?? '0',
                $row['faculty_count'] ?? '0',
                $row['student_count'] ?? '0'
            ]);
        }
        
        fputcsv($output, ['']);
        fputcsv($output, ['Report Generated On:', date('Y-m-d h:i A')]);
        
    } else {
        // No data found
        fputcsv($output, ['No subjects data found.']);
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