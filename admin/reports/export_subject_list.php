<?php
// Start session
session_start();
require_once '../../config/database.php';
require_once '../../includes/session_protection.php';

// Use the verify_session function to check admin session
verify_session('admin');

// Get filters if provided
$program = isset($_GET['program']) ? $_GET['program'] : '';
$year_level = isset($_GET['year_level']) ? $_GET['year_level'] : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : '';
$school_year = isset($_GET['school_year']) ? $_GET['school_year'] : '';

// Create filename with filter info
$filename = 'subject_list';
if (!empty($program) || !empty($year_level) || !empty($semester) || !empty($school_year)) {
    $filename .= '_';
    $filters = [];
    if (!empty($program)) $filters[] = $program;
    if (!empty($year_level)) $filters[] = 'Y' . $year_level;
    if (!empty($semester)) $filters[] = 'SEM' . $semester;
    if (!empty($school_year)) $filters[] = $school_year;
    $filename .= implode('_', $filters);
}
$filename .= '_' . date('Y-m-d') . '.csv';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel encoding issues
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Report header
fputcsv($output, ['Report Type: Subject List', date('Y-m-d h:i A')]);
fputcsv($output, ['']);

// Add filter information if any filters are applied
if (!empty($program) || !empty($year_level) || !empty($semester) || !empty($school_year)) {
    fputcsv($output, ['FILTER INFORMATION']);
    if (!empty($program)) {
        fputcsv($output, ['Program:', $program]);
    }
    if (!empty($year_level)) {
        fputcsv($output, ['Year Level:', $year_level]);
    }
    if (!empty($semester)) {
        fputcsv($output, ['Semester:', $semester]);
    }
    if (!empty($school_year)) {
        fputcsv($output, ['School Year:', $school_year]);
    }
    fputcsv($output, ['']);
}

// Build SQL query with filters
$query = "
    SELECT 
        s.subject_code,
        s.subject_name,
        s.description,
        s.units,
        s.lab_units,
        s.semester,
        s.year_level,
        p.program_code,
        p.program_name,
        COUNT(DISTINCT e.student_id) as enrolled_students,
        s.created_at
    FROM 
        subjects s
    LEFT JOIN 
        programs p ON s.program_id = p.id
    LEFT JOIN 
        enrollments e ON e.subject_id = s.id AND e.status = 'Enrolled'
    WHERE 1=1
";

// Add filters if provided
$whereConditions = [];
$params = [];
$types = "";

if (!empty($program)) {
    $whereConditions[] = "p.program_code = ?";
    $params[] = $program;
    $types .= "s";
}

if (!empty($year_level)) {
    $whereConditions[] = "s.year_level = ?";
    $params[] = $year_level;
    $types .= "s";
}

if (!empty($semester)) {
    $whereConditions[] = "s.semester = ?";
    $params[] = $semester;
    $types .= "s";
}

if (!empty($school_year)) {
    $whereConditions[] = "s.school_year = ?";
    $params[] = $school_year;
    $types .= "s";
}

if (!empty($whereConditions)) {
    $query .= " AND " . implode(" AND ", $whereConditions);
}

$query .= " GROUP BY s.id, s.subject_code, s.subject_name, s.description, s.units, s.lab_units, s.semester, s.year_level, p.program_code, p.program_name, s.created_at";
$query .= " ORDER BY p.program_code, s.year_level, s.semester, s.subject_code";

try {
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Output report headers
        fputcsv($output, ['SUBJECT LIST']);
        fputcsv($output, ['Total Records:', $result->num_rows]);
        fputcsv($output, ['']);
        
        // Calculate statistics
        $program_counts = [];
        $year_level_counts = [];
        $semester_counts = [];
        $units_total = 0;
        $lab_units_total = 0;
        $total_enrolled = 0;
        
        // First pass to calculate statistics
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
            
            // Count programs
            $program = $row['program_code'] ?? 'Not Set';
            if (!isset($program_counts[$program])) {
                $program_counts[$program] = [
                    'count' => 0,
                    'name' => $row['program_name'] ?? 'Unknown',
                    'subjects' => []
                ];
            }
            $program_counts[$program]['count']++;
            $program_counts[$program]['subjects'][] = $row['subject_code'];
            
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
            
            // Count units
            $units_total += (float)($row['units'] ?? 0);
            $lab_units_total += (float)($row['lab_units'] ?? 0);
            
            // Count total enrolled
            $total_enrolled += (int)($row['enrolled_students'] ?? 0);
        }
        
        // Output program statistics
        fputcsv($output, ['PROGRAM DISTRIBUTION']);
        foreach ($program_counts as $program => $info) {
            $percentage = round(($info['count'] / $result->num_rows) * 100, 2);
            fputcsv($output, [$program, $info['name'], $info['count'], $percentage . '%']);
        }
        fputcsv($output, ['']);
        
        // Output year level statistics
        fputcsv($output, ['YEAR LEVEL DISTRIBUTION']);
        foreach ($year_level_counts as $year_level => $count) {
            $percentage = round(($count / $result->num_rows) * 100, 2);
            fputcsv($output, ['Year ' . $year_level, $count, $percentage . '%']);
        }
        fputcsv($output, ['']);
        
        // Output semester statistics
        fputcsv($output, ['SEMESTER DISTRIBUTION']);
        foreach ($semester_counts as $semester => $count) {
            $percentage = round(($count / $result->num_rows) * 100, 2);
            fputcsv($output, ['Semester ' . $semester, $count, $percentage . '%']);
        }
        fputcsv($output, ['']);
        
        // Output summary statistics
        fputcsv($output, ['SUMMARY STATISTICS']);
        fputcsv($output, ['Total Subjects:', $result->num_rows]);
        fputcsv($output, ['Total Lecture Units:', $units_total]);
        fputcsv($output, ['Total Lab Units:', $lab_units_total]);
        fputcsv($output, ['Total Units:', $units_total + $lab_units_total]);
        fputcsv($output, ['Average Units per Subject:', round($units_total / $result->num_rows, 2)]);
        fputcsv($output, ['Average Lab Units per Subject:', round($lab_units_total / $result->num_rows, 2)]);
        fputcsv($output, ['Total Enrolled Students:', $total_enrolled]);
        fputcsv($output, ['Average Enrolled per Subject:', round($total_enrolled / $result->num_rows, 2)]);
        fputcsv($output, ['']);
        
        // Output program subject details
        fputcsv($output, ['SUBJECTS BY PROGRAM']);
        foreach ($program_counts as $program => $info) {
            fputcsv($output, [$program, $info['name'], 'Subject Count: ' . $info['count']]);
            $subject_list = implode(', ', $info['subjects']);
            fputcsv($output, ['Subjects:', $subject_list]);
            fputcsv($output, ['']);
        }
        
        // Output detailed subject list
        fputcsv($output, ['DETAILED SUBJECT LIST']);
        fputcsv($output, [
            'Subject Code', 
            'Subject Name', 
            'Description', 
            'Lecture Units', 
            'Lab Units', 
            'Total Units',
            'Semester',
            'Year Level',
            'Program',
            'Enrolled Students'
        ]);
        
        foreach ($data as $row) {
            $total_units = (float)($row['units'] ?? 0) + (float)($row['lab_units'] ?? 0);
            
            fputcsv($output, [
                $row['subject_code'] ?? '',
                $row['subject_name'] ?? '',
                $row['description'] ?? '',
                $row['units'] ?? '0',
                $row['lab_units'] ?? '0',
                $total_units,
                $row['semester'] ?? '',
                $row['year_level'] ?? '',
                $row['program_code'] ?? '',
                $row['enrolled_students'] ?? '0'
            ]);
        }
        
        fputcsv($output, ['']);
        fputcsv($output, ['Report Generated On:', date('Y-m-d h:i A')]);
        
    } else {
        // No data found
        fputcsv($output, ['No subject data found for the given criteria.']);
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