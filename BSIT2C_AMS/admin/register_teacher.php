<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$success_msg = $error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    $teacher_id = $conn->real_escape_string($_POST['teacher_id']);
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $middle_name = $conn->real_escape_string($_POST['middle_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $dob = $_POST['date_of_birth'];
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $department = $conn->real_escape_string($_POST['department']);
    $designation = $conn->real_escape_string($_POST['designation']);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert into users table with teacher role
        $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, 'teacher')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $user_id = $conn->insert_id;

        // Insert into teachers table
        $sql = "INSERT INTO teachers (user_id, teacher_id, first_name, middle_name, last_name, 
                gender, date_of_birth, email, phone_number, department, designation) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssssss", $user_id, $teacher_id, $first_name, $middle_name, 
                         $last_name, $gender, $dob, $email, $phone, $department, $designation);
        $stmt->execute();

        $conn->commit();
        $success_msg = "Teacher registered successfully! They can now login at the teacher portal.";
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register Teacher - BSIT 2C AMS</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f4f4f4; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #45a049; }
        .success { color: green; margin-bottom: 10px; }
        .error { color: red; margin-bottom: 10px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #666; text-decoration: none; }
        .back-link:hover { color: #333; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        <h2>Register New Teacher</h2>
        
        <?php if ($success_msg): ?>
            <div class="success"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        
        <?php if ($error_msg): ?>
            <div class="error"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" required>
            </div>

            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>

            <div class="form-group">
                <label>Teacher ID:</label>
                <input type="text" name="teacher_id" required>
            </div>

            <div class="form-group">
                <label>First Name:</label>
                <input type="text" name="first_name" required>
            </div>

            <div class="form-group">
                <label>Middle Name:</label>
                <input type="text" name="middle_name">
            </div>

            <div class="form-group">
                <label>Last Name:</label>
                <input type="text" name="last_name" required>
            </div>

            <div class="form-group">
                <label>Gender:</label>
                <select name="gender" required>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>Date of Birth:</label>
                <input type="date" name="date_of_birth" required>
            </div>

            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>Phone Number:</label>
                <input type="text" name="phone" required>
            </div>

            <div class="form-group">
                <label>Department:</label>
                <input type="text" name="department" required>
            </div>

            <div class="form-group">
                <label>Designation:</label>
                <select name="designation" required>
                    <option value="Full-time">Full-time</option>
                    <option value="Part-time">Part-time</option>
                </select>
            </div>

            <button type="submit" class="btn">Register Teacher</button>
        </form>
    </div>
</body>
</html> 