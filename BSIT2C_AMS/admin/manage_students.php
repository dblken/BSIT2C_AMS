<?php
require_once '../includes/admin_header.php';
require_once '../config/database.php';

// Handle student registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_student'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $student_id = $_POST['student_id'];
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $gender = $_POST['gender'];
    $dob = $_POST['date_of_birth'];
    $year_level = $_POST['year_level'];

    try {
        $conn->begin_transaction();

        // Insert into users table
        $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, 'student')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $user_id = $conn->insert_id;

        // Insert into students table
        $sql = "INSERT INTO students (user_id, student_id, first_name, middle_name, last_name, 
                gender, date_of_birth, email, phone_number, year_level) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssssi", $user_id, $student_id, $first_name, $middle_name, 
                         $last_name, $gender, $dob, $email, $phone, $year_level);
        $stmt->execute();

        $conn->commit();
        $success_message = "Student registered successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get all students
$students = $conn->query("
    SELECT s.*, u.username, u.is_active
    FROM students s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.last_name, s.first_name
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Students - BSIT 2C</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php if (isset($success_message)): ?>
            <div class="alert success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="alert error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="admin-sections">
            <!-- Register New Student Form -->
            <div class="section">
                <h2>Register New Student</h2>
                <form method="POST" class="admin-form">
                    <div class="form-group">
                        <label>Student ID:</label>
                        <input type="text" name="student_id" required>
                    </div>
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Password:</label>
                        <input type="password" name="password" required>
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
                        <label>Year Level:</label>
                        <input type="number" name="year_level" min="1" max="4" required>
                    </div>
                    <button type="submit" name="register_student">Register Student</button>
                </form>
            </div>

            <!-- Students List -->
            <div class="section">
                <h2>Students List</h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Year Level</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($student = $students->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $student['student_id']; ?></td>
                                <td><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                                <td><?php echo $student['email']; ?></td>
                                <td><?php echo $student['year_level']; ?></td>
                                <td><?php echo $student['status']; ?></td>
                                <td>
                                    <a href="edit_student.php?id=<?php echo $student['id']; ?>" 
                                       class="btn-edit">Edit</a>
                                    <a href="view_student.php?id=<?php echo $student['id']; ?>" 
                                       class="btn-view">View</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html> 