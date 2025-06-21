<?php
session_start();
require_once '../../config/database.php';

// For debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Log received data
        error_log("Received POST data: " . json_encode($_POST));
        
        // Get form data with proper validation
        $student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
        $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
        $middle_name = isset($_POST['middle_name']) && $_POST['middle_name'] !== '' ? trim($_POST['middle_name']) : null;
        $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
        $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
        $dob = isset($_POST['dob']) ? trim($_POST['dob']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) && $_POST['phone'] !== '' ? trim($_POST['phone']) : null;
        $address = isset($_POST['address']) && $_POST['address'] !== '' ? trim($_POST['address']) : null;
        $current_date = date('Y-m-d H:i:s');
        $status = 'Active'; 

        // Validate required fields
        if (empty($student_id)) throw new Exception("Student ID is required");
        if (empty($first_name)) throw new Exception("First Name is required");
        if (empty($last_name)) throw new Exception("Last Name is required");
        if (empty($gender)) throw new Exception("Gender is required");
        if (empty($dob)) throw new Exception("Date of Birth is required");
        if (empty($email)) throw new Exception("Email is required");
        
        // Check database connection
        if ($conn->connect_error) {
            throw new Exception("Database connection failed: " . $conn->connect_error);
        }
        
        // Check if student_id is already taken
        $check_id = "SELECT id FROM students WHERE student_id = ?";
        $stmt = $conn->prepare($check_id);
        if (!$stmt) {
            throw new Exception("Prepare failed on student_id check: " . $conn->error);
        }
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Student ID already exists");
        }
        
        // Check if email is already taken
        $check_email = "SELECT id FROM students WHERE email = ?";
        $stmt = $conn->prepare($check_email);
        if (!$stmt) {
            throw new Exception("Prepare failed on email check: " . $conn->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Email already exists");
        }
        
        // Get table structure to verify column names
        $table_structure = $conn->query("DESCRIBE students");
        $columns = [];
        while ($row = $table_structure->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        error_log("Table columns: " . json_encode($columns));
        
        // Look for the phone column name
        $phone_column = "phone_number"; // Default
        if (in_array("phone", $columns)) {
            $phone_column = "phone";
        }
        
        // Build the SQL query dynamically based on the actual column names
        $fields = [
            "student_id", "first_name", "middle_name", "last_name", 
            "gender", "date_of_birth", "email", $phone_column, "address", 
            "status", "created_at", "updated_at"
        ];
        
        $sql = "INSERT INTO students (" . implode(", ", $fields) . ") VALUES (";
        $sql .= str_repeat("?, ", count($fields) - 1) . "?)";

        error_log("SQL Query: " . $sql);
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed for INSERT: " . $conn->error);
        }
        
        $stmt->bind_param(
            "ssssssssssss", 
            $student_id, 
            $first_name, 
            $middle_name, 
            $last_name, 
            $gender, 
            $dob, 
            $email, 
            $phone, 
            $address, 
            $status, 
            $current_date, 
            $current_date
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Student successfully added'
        ]);
        
    } catch (Exception $e) {
        error_log("Add student error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?> 