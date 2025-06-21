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
        $date_of_birth = isset($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) && $_POST['phone'] !== '' ? trim($_POST['phone']) : null;
        $address = isset($_POST['address']) && $_POST['address'] !== '' ? trim($_POST['address']) : null;
        $current_date = date('Y-m-d H:i:s');
        $status = 'Active'; 
        
        // Get username and password data
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        // Validate required fields
        if (empty($student_id)) throw new Exception("Student ID is required");
        if (empty($first_name)) throw new Exception("First Name is required");
        if (empty($last_name)) throw new Exception("Last Name is required");
        if (empty($gender)) throw new Exception("Gender is required");
        if (empty($date_of_birth)) throw new Exception("Date of Birth is required");
        if (empty($email)) throw new Exception("Email is required");
        if (empty($username)) throw new Exception("Username is required");
        if (empty($password)) throw new Exception("Password is required");
        if (strlen($password) < 6) throw new Exception("Password must be at least 6 characters long");
        
        // Validate password for special characters only if it contains them
        // This allows simple passwords but warns if there are unusual special characters
        if (preg_match('/[^a-zA-Z0-9\!\@\#\$\%\^\&\*\(\)\_\-\+\=\.]/', $password)) {
            throw new Exception("Password contains invalid special characters. Please use only letters, numbers, and basic special characters (!@#$%^&*()_-+=.)");
        }
        
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
        
        // Check if username is already taken
        $check_username = "SELECT id FROM users WHERE username = ?";
        $stmt = $conn->prepare($check_username);
        if (!$stmt) {
            throw new Exception("Prepare failed on username check: " . $conn->error);
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Username already exists");
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        // Check if role column exists in users table
        $check_role_column = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
        $role_column_exists = $check_role_column->num_rows > 0;
        
        // If role column doesn't exist, add it (this is important for system compatibility)
        if (!$role_column_exists) {
            try {
                $alter_table = "ALTER TABLE users ADD COLUMN role ENUM('admin', 'teacher', 'student') DEFAULT NULL";
                $conn->query($alter_table);
                error_log("Added 'role' column to users table for system compatibility");
                $role_column_exists = true; // Now we can use it
            } catch (Exception $e) {
                error_log("Failed to add role column: " . $e->getMessage());
                // Continue without role column if alter table fails
            }
        }
        
        // Try to use password_hash first
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // Fallback to MD5 with salt if the hashed password is too long
        if (strlen($hashed_password) > 255) {
            $salt = bin2hex(random_bytes(8)); // 16 characters
            $hashed_password = md5($salt . $password); // 32 characters
            $hashed_password = $salt . ':' . $hashed_password; // Total: 49 characters
        }
        
        // Prepare SQL statement based on whether role column exists
        if ($role_column_exists) {
            $user_sql = "INSERT INTO users (username, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?)";
            $user_stmt = $conn->prepare($user_sql);
            if (!$user_stmt) {
                throw new Exception("Prepare failed for users INSERT: " . $conn->error);
            }
            $user_role = 'student';
            $user_stmt->bind_param("sssss", $username, $hashed_password, $user_role, $current_date, $current_date);
        } else {
            $user_sql = "INSERT INTO users (username, password, created_at, updated_at) VALUES (?, ?, ?, ?)";
            $user_stmt = $conn->prepare($user_sql);
            if (!$user_stmt) {
                throw new Exception("Prepare failed for users INSERT: " . $conn->error);
            }
            $user_stmt->bind_param("ssss", $username, $hashed_password, $current_date, $current_date);
        }
        
        if (!$user_stmt->execute()) {
            throw new Exception("Execute failed for users INSERT: " . $user_stmt->error);
        }
        
        $user_id = $conn->insert_id;
        
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
        
        // Build dynamic field list based on what exists in the table
        $available_fields = [];
        $params = [];
        $param_types = '';
        
        // Always include user_id
        $available_fields[] = "user_id";
        $params[] = $user_id;
        $param_types .= 'i'; // integer
        
        // Include other fields only if they exist in the table
        $field_mapping = [
            "student_id" => ['value' => $student_id, 'type' => 's'],
            "first_name" => ['value' => $first_name, 'type' => 's'],
            "middle_name" => ['value' => $middle_name, 'type' => 's'],
            "last_name" => ['value' => $last_name, 'type' => 's'],
            "gender" => ['value' => $gender, 'type' => 's'],
            "date_of_birth" => ['value' => $date_of_birth, 'type' => 's'],
            "email" => ['value' => $email, 'type' => 's'],
            $phone_column => ['value' => $phone, 'type' => 's'],
            "address" => ['value' => $address, 'type' => 's'],
            "status" => ['value' => $status, 'type' => 's'],
            "created_at" => ['value' => $current_date, 'type' => 's'],
            "updated_at" => ['value' => $current_date, 'type' => 's']
        ];
        
        foreach ($field_mapping as $field => $data) {
            if (in_array($field, $columns)) {
                $available_fields[] = $field;
                $params[] = $data['value'];
                $param_types .= $data['type'];
            }
        }
        
        $sql = "INSERT INTO students (" . implode(", ", $available_fields) . ") VALUES (";
        $sql .= str_repeat("?, ", count($available_fields) - 1) . "?)";

        error_log("SQL Query: " . $sql);
        error_log("Params: " . json_encode($params));
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed for students INSERT: " . $conn->error);
        }
        
        // Dynamically bind parameters
        $bind_params = array_merge([$param_types], $params);
        $ref_params = [];
        foreach ($bind_params as $key => $value) {
            $ref_params[$key] = &$bind_params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $ref_params);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for students INSERT: " . $stmt->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Student successfully added with user account'
        ]);
        
    } catch (Exception $e) {
        // Roll back transaction on error
        if ($conn->connect_error) {
            $conn->rollback();
        }
        
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