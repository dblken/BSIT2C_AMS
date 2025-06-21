<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/session_protection.php';

// Use the verify_session function to check admin session
verify_session('admin');

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="Teacher_List_' . date('Y-m-d_H-i-s') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV header
fputcsv($output, [
    'Teacher ID',
    'First Name',
    'Middle Name',
    'Last Name',
    'Email',
    'Phone',
    'Department',
    'Status',
    'Date Registered'
]);

// Get all teachers with registration date
$query = "SELECT t.*, u.created_at as registration_date 
          FROM teachers t 
          LEFT JOIN users u ON t.user_id = u.id 
          ORDER BY t.last_name, t.first_name";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Format date
        $registration_date = date('M d, Y', strtotime($row['registration_date']));
        
        // Write teacher data to CSV
        fputcsv($output, [
            $row['teacher_id'],
            $row['first_name'],
            $row['middle_name'],
            $row['last_name'],
            $row['email'],
            $row['phone'] ?? 'N/A',
            $row['department'],
            $row['status'],
            $registration_date
        ]);
    }
}

// Close the output stream
fclose($output);
exit; 