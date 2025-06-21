<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get the subject ID
        $id = isset($_POST['id']) ? mysqli_real_escape_string($conn, $_POST['id']) : null;
        
        if (!$id) {
            throw new Exception('Subject ID is required');
        }

        // Build update query dynamically based on provided fields
        $updates = array();
        
        // Only include fields that are provided
        if (isset($_POST['subject_code']) && !empty($_POST['subject_code'])) {
            $subject_code = mysqli_real_escape_string($conn, $_POST['subject_code']);
            $updates[] = "subject_code = '$subject_code'";
        }
        
        if (isset($_POST['subject_name']) && !empty($_POST['subject_name'])) {
            $subject_name = mysqli_real_escape_string($conn, $_POST['subject_name']);
            $updates[] = "subject_name = '$subject_name'";
        }
        
        if (isset($_POST['description'])) {
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $updates[] = "description = '$description'";
        }
        
        if (isset($_POST['units']) && !empty($_POST['units'])) {
            $units = (int)$_POST['units'];
            if ($units >= 1 && $units <= 5) {
                $updates[] = "units = $units";
            }
        }
        
        if (empty($updates)) {
            throw new Exception('No fields to update');
        }
        
        // Construct and execute the update query
        $update_query = "UPDATE subjects SET " . implode(', ', $updates) . " WHERE id = '$id'";
        
        if (mysqli_query($conn, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'Subject updated successfully']);
        } else {
            throw new Exception(mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error updating subject: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?> 