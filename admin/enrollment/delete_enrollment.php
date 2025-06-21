<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if the enrollment_id is provided
if (!isset($_POST['enrollment_id']) || empty($_POST['enrollment_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Enrollment ID is required'
    ]);
    exit;
}

// Get the enrollment ID
$enrollment_id = intval($_POST['enrollment_id']);

try {
    // Start a transaction
    $conn->begin_transaction();

    // First check if enrollment exists
    $check_query = "SELECT id FROM enrollments WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $enrollment_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        // Enrollment not found
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Enrollment not found'
        ]);
        exit;
    }
    
    // Get enrollment details before deletion (if possible)
    $query = "SELECT e.*, 
              s.subject_code, 
              s.subject_name, 
              CONCAT(st.first_name, ' ', st.last_name) AS student_name
              FROM enrollments e
              LEFT JOIN subjects s ON e.subject_id = s.id
              LEFT JOIN students st ON e.student_id = st.id
              WHERE e.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $enrollment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $enrollment = $result->fetch_assoc();
    
    // Delete the enrollment
    $delete_query = "DELETE FROM enrollments WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("i", $enrollment_id);
    $delete_result = $delete_stmt->execute();
    
    if (!$delete_result) {
        // Failed to delete
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete enrollment: ' . $conn->error
        ]);
        exit;
    }
    
    // Commit the transaction
    $conn->commit();
    
    // Create the subject info string if available
    $subject_info = "";
    if (!empty($enrollment['subject_code']) && !empty($enrollment['subject_name'])) {
        $subject_info = $enrollment['subject_code'] . ' - ' . $enrollment['subject_name'];
    } else {
        $subject_info = "selected subject";
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Enrollment successfully deleted',
        'subject' => $subject_info
    ]);
    
} catch (Exception $e) {
    // Rollback the transaction in case of an error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>