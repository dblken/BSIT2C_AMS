<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    // Query to get all subjects with assignment status
    $query = "
        SELECT s.*, 
               (SELECT EXISTS(SELECT 1 FROM assignments a WHERE a.subject_id = s.id)) as is_assigned
        FROM subjects s
        WHERE s.status = 'Active'
        ORDER BY s.subject_code
    ";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        throw new Exception("Database query error: " . mysqli_error($conn));
    }
    
    $subjects = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Convert is_assigned to boolean
        $row['is_assigned'] = (bool)$row['is_assigned'];
        $subjects[] = $row;
    }
    
    echo json_encode($subjects);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 