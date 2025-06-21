<?php
// Start session
session_start();
require_once '../../config/database.php';
require_once '../../includes/session_protection.php';

// Use the verify_session function to check admin session
verify_session('admin');

// Get optional filters
$program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : null;
$department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;

// Create filename with filter info
$filename = 'faculty_list';
if ($program_id) $filename .= '_program_' . $program_id;
if ($department_id) $filename .= '_dept_' . $department_id;
if ($status) $filename .= '_status_' . $status;
$filename .= '_' . date('Y-m-d') . '.csv';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel encoding issues
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Report header
fputcsv($output, ['Report Type: Faculty List', date('Y-m-d h:i A')]);
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

if ($department_id) {
    $department_query = "SELECT department_name FROM departments WHERE id = ?";
    $stmt = $conn->prepare($department_query);
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $filter_data[] = ["Filter: Department", $row['department_name']];
    }
}

if ($status) {
    $filter_data[] = ["Filter: Status", $status];
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
        f.id,
        f.faculty_id as faculty_code,
        f.first_name,
        f.last_name,
        f.email,
        f.phone,
        f.status,
        d.department_name,
        (SELECT COUNT(DISTINCT c.id) FROM classes c WHERE c.faculty_id = f.id) as class_count,
        (SELECT COUNT(DISTINCT s.id) FROM subjects s 
         JOIN classes c ON s.id = c.subject_id 
         WHERE c.faculty_id = f.id) as subject_count,
        (SELECT SUM(s.units) FROM subjects s 
         JOIN classes c ON s.id = c.subject_id 
         WHERE c.faculty_id = f.id) as total_units,
        (SELECT GROUP_CONCAT(DISTINCT p.program_code SEPARATOR ', ') 
         FROM programs p 
         JOIN subjects s ON s.program_id = p.id 
         JOIN classes c ON c.subject_id = s.id 
         WHERE c.faculty_id = f.id) as programs
    FROM 
        faculty f
    LEFT JOIN 
        departments d ON f.department_id = d.id
    WHERE 
        1 = 1
";

$params = [];
$param_types = "";

if ($program_id) {
    $query .= " AND EXISTS (
        SELECT 1 FROM classes c 
        JOIN subjects s ON c.subject_id = s.id 
        WHERE c.faculty_id = f.id AND s.program_id = ?
    )";
    $params[] = $program_id;
    $param_types .= "i";
}

if ($department_id) {
    $query .= " AND f.department_id = ?";
    $params[] = $department_id;
    $param_types .= "i";
}

if ($status) {
    $query .= " AND f.status = ?";
    $params[] = $status;
    $param_types .= "s";
}

$query .= " ORDER BY f.last_name, f.first_name";

try {
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Output report headers
        fputcsv($output, ['FACULTY LIST']);
        fputcsv($output, ['Total Records:', $result->num_rows]);
        fputcsv($output, ['']);
        
        // Calculate statistics
        $department_counts = [];
        $status_counts = [];
        $program_counts = [];
        $total_classes = 0;
        $total_subjects = 0;
        $total_units = 0;
        
        // First pass to calculate statistics
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
            
            // Count departments
            $department = $row['department_name'] ?? 'Not Assigned';
            if (!isset($department_counts[$department])) {
                $department_counts[$department] = 0;
            }
            $department_counts[$department]++;
            
            // Count status
            $status = $row['status'] ?? 'Not Set';
            if (!isset($status_counts[$status])) {
                $status_counts[$status] = 0;
            }
            $status_counts[$status]++;
            
            // Count programs
            if (!empty($row['programs'])) {
                $faculty_programs = explode(', ', $row['programs']);
                foreach ($faculty_programs as $program) {
                    if (!isset($program_counts[$program])) {
                        $program_counts[$program] = 0;
                    }
                    $program_counts[$program]++;
                }
            }
            
            // Sum totals
            $total_classes += (int)($row['class_count'] ?? 0);
            $total_subjects += (int)($row['subject_count'] ?? 0);
            $total_units += (float)($row['total_units'] ?? 0);
        }
        
        // Output department distribution
        fputcsv($output, ['DEPARTMENT DISTRIBUTION']);
        ksort($department_counts);
        foreach ($department_counts as $department => $count) {
            $percentage = round(($count / $result->num_rows) * 100, 2);
            fputcsv($output, [$department, $count, $percentage . '%']);
        }
        fputcsv($output, ['']);
        
        // Output status distribution
        fputcsv($output, ['STATUS DISTRIBUTION']);
        ksort($status_counts);
        foreach ($status_counts as $status => $count) {
            $percentage = round(($count / $result->num_rows) * 100, 2);
            fputcsv($output, [$status, $count, $percentage . '%']);
        }
        fputcsv($output, ['']);
        
        // Output program distribution
        fputcsv($output, ['PROGRAM DISTRIBUTION']);
        ksort($program_counts);
        foreach ($program_counts as $program => $count) {
            $percentage = round(($count / $result->num_rows) * 100, 2);
            fputcsv($output, [$program, $count, $percentage . '%']);
        }
        fputcsv($output, ['']);
        
        // Output summary statistics
        fputcsv($output, ['SUMMARY STATISTICS']);
        fputcsv($output, ['Total Faculty:', $result->num_rows]);
        fputcsv($output, ['Total Classes:', $total_classes]);
        fputcsv($output, ['Total Subjects:', $total_subjects]);
        fputcsv($output, ['Total Units:', $total_units]);
        fputcsv($output, ['Average Classes Per Faculty:', round($total_classes / $result->num_rows, 2)]);
        fputcsv($output, ['Average Subjects Per Faculty:', round($total_subjects / $result->num_rows, 2)]);
        fputcsv($output, ['Average Units Per Faculty:', round($total_units / $result->num_rows, 2)]);
        fputcsv($output, ['']);
        
        // Output detailed faculty list
        fputcsv($output, ['DETAILED FACULTY LIST']);
        fputcsv($output, [
            'Faculty Code',
            'Name',
            'Email',
            'Phone',
            'Department',
            'Status',
            'Classes',
            'Subjects',
            'Units',
            'Programs'
        ]);
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['faculty_code'] ?? '',
                $row['last_name'] . ', ' . $row['first_name'],
                $row['email'] ?? '',
                $row['phone'] ?? '',
                $row['department_name'] ?? '',
                $row['status'] ?? '',
                $row['class_count'] ?? '0',
                $row['subject_count'] ?? '0',
                $row['total_units'] ?? '0',
                $row['programs'] ?? ''
            ]);
        }
        
        fputcsv($output, ['']);
        fputcsv($output, ['Report Generated On:', date('Y-m-d h:i A')]);
        
    } else {
        // No data found
        fputcsv($output, ['No faculty data found.']);
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