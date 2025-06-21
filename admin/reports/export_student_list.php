<?php
// Start session
session_start();
require_once '../../config/database.php';
require_once '../../includes/session_protection.php';

// Use the verify_session function to check admin session
verify_session('admin');

// Set filename
$filename = 'students_list_' . date('Y-m-d') . '.csv';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel encoding issues
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Report header
fputcsv($output, ['Registered Students List', date('Y-m-d h:i A')]);
fputcsv($output, ['']);

// Very basic query to get all students
$query = "SELECT student_id, first_name, middle_name, last_name, email FROM students";

try {
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        // Count how many students we found
        fputcsv($output, ['Total Students Found:', $result->num_rows]);
        fputcsv($output, ['']);
        
        // Custom headers for better readability
        $headers = ['Student ID', 'First Name', 'Middle Name', 'Last Name', 'Email'];
        
        // Output column headers
        fputcsv($output, $headers);
        
        // Reset result pointer
        $result->data_seek(0);
        
        // Output all rows
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
        
    } else {
        // Write diagnostic information
        fputcsv($output, ['No students found in the database.']);
        fputcsv($output, ['Query:', $query]);
        
        // Check if table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'students'");
        if ($table_check->num_rows == 0) {
            fputcsv($output, ['ERROR:', 'The students table does not exist in the database.']);
        } else {
            fputcsv($output, ['Table exists but contains no records.']);
        }
        
        // Check for any MySQL errors
        if ($conn->error) {
            fputcsv($output, ['MySQL Error:', $conn->error]);
        }
    }
} catch (Exception $e) {
    // Write error to CSV
    fputcsv($output, ['Error:', $e->getMessage()]);
}

// End of report
fputcsv($output, ['']);
fputcsv($output, ['Report Generated On:', date('Y-m-d h:i A')]);

// Close the output stream
fclose($output);
exit;
?> 