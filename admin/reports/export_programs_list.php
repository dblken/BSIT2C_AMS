<?php
// Start session
session_start();
require_once '../../config/database.php';
require_once '../../includes/session_protection.php';

// Use the verify_session function to check admin session
verify_session('admin');

// Create filename
$filename = 'programs_list_' . date('Y-m-d') . '.csv';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel encoding issues
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Report header
fputcsv($output, ['Report Type: Programs List', date('Y-m-d h:i A')]);
fputcsv($output, ['']);

// Build SQL query
$query = "
    SELECT 
        p.id,
        p.program_code,
        p.program_name,
        p.description,
        p.years_to_complete,
        (SELECT COUNT(s.id) FROM subjects s WHERE s.program_id = p.id) AS subject_count,
        (SELECT COUNT(DISTINCT c.id) FROM classes c 
         JOIN subjects s ON c.subject_id = s.id 
         WHERE s.program_id = p.id) AS class_count,
        (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e 
         JOIN classes c ON e.class_id = c.id 
         JOIN subjects s ON c.subject_id = s.id 
         WHERE s.program_id = p.id) AS student_count
    FROM 
        programs p
    ORDER BY p.program_code
";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Output report headers
        fputcsv($output, ['PROGRAMS LIST']);
        fputcsv($output, ['Total Records:', $result->num_rows]);
        fputcsv($output, ['']);
        
        // Calculate statistics
        $years_to_complete_counts = [];
        $total_subjects = 0;
        $total_classes = 0;
        $total_students = 0;
        
        // First pass to calculate statistics
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
            
            // Count years to complete
            $years = $row['years_to_complete'] ?? 'Not Set';
            if (!isset($years_to_complete_counts[$years])) {
                $years_to_complete_counts[$years] = 0;
            }
            $years_to_complete_counts[$years]++;
            
            // Calculate totals
            $total_subjects += (int)($row['subject_count'] ?? 0);
            $total_classes += (int)($row['class_count'] ?? 0);
            $total_students += (int)($row['student_count'] ?? 0);
        }
        
        // Output years to complete statistics
        fputcsv($output, ['YEARS TO COMPLETE DISTRIBUTION']);
        ksort($years_to_complete_counts); // Sort by years in ascending order
        foreach ($years_to_complete_counts as $years => $count) {
            $percentage = round(($count / $result->num_rows) * 100, 2);
            fputcsv($output, [$years . ' year(s)', $count, $percentage . '%']);
        }
        fputcsv($output, ['']);
        
        // Output summary statistics
        fputcsv($output, ['SUMMARY STATISTICS']);
        fputcsv($output, ['Total Programs:', $result->num_rows]);
        fputcsv($output, ['Average Years to Complete:', round(array_sum(array_map(function($y, $c) { return $y * $c; }, array_keys($years_to_complete_counts), array_values($years_to_complete_counts))) / $result->num_rows, 2)]);
        fputcsv($output, ['Total Subjects Across All Programs:', $total_subjects]);
        fputcsv($output, ['Average Subjects Per Program:', round($total_subjects / $result->num_rows, 2)]);
        fputcsv($output, ['Total Classes:', $total_classes]);
        fputcsv($output, ['Average Classes Per Program:', round($total_classes / $result->num_rows, 2)]);
        fputcsv($output, ['Total Students Enrolled:', $total_students]);
        fputcsv($output, ['Average Students Per Program:', round($total_students / $result->num_rows, 2)]);
        fputcsv($output, ['']);
        
        // Output detailed programs list
        fputcsv($output, ['DETAILED PROGRAMS LIST']);
        fputcsv($output, [
            'Program Code',
            'Program Name',
            'Description',
            'Years to Complete',
            'Subject Count',
            'Class Count',
            'Student Count'
        ]);
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['program_code'] ?? '',
                $row['program_name'] ?? '',
                $row['description'] ?? '',
                $row['years_to_complete'] ?? '',
                $row['subject_count'] ?? '0',
                $row['class_count'] ?? '0',
                $row['student_count'] ?? '0'
            ]);
        }
        
        fputcsv($output, ['']);
        fputcsv($output, ['Report Generated On:', date('Y-m-d h:i A')]);
        
    } else {
        // No data found
        fputcsv($output, ['No programs data found.']);
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