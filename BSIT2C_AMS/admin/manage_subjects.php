<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Add new subject
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_subject'])) {
    $subject_code = $_POST['subject_code'];
    $subject_name = $_POST['subject_name'];
    $teacher_id = $_POST['teacher_id'];

    $sql = "INSERT INTO subjects (subject_code, subject_name, teacher_id) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $subject_code, $subject_name, $teacher_id);
    $stmt->execute();
}

// Get all teachers
$teachers_query = "SELECT id, full_name FROM users WHERE role = 'teacher'";
$teachers = $conn->query($teachers_query);

// Get all subjects with teacher names
$subjects_query = "SELECT s.*, u.full_name as teacher_name 
                  FROM subjects s 
                  LEFT JOIN users u ON s.teacher_id = u.id";
$subjects = $conn->query($subjects_query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Subjects - BSIT 2C</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h2>Manage Subjects - BSIT 2C</h2>
        
        <!-- Add Subject Form -->
        <div class="form-section">
            <h3>Add New Subject</h3>
            <form method="POST">
                <input type="text" name="subject_code" placeholder="Subject Code" required>
                <input type="text" name="subject_name" placeholder="Subject Name" required>
                <select name="teacher_id" required>
                    <option value="">Select Teacher</option>
                    <?php while ($teacher = $teachers->fetch_assoc()): ?>
                        <option value="<?php echo $teacher['id']; ?>">
                            <?php echo $teacher['full_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" name="add_subject">Add Subject</button>
            </form>
        </div>

        <!-- Subjects List -->
        <div class="list-section">
            <h3>Current Subjects</h3>
            <table>
                <tr>
                    <th>Subject Code</th>
                    <th>Subject Name</th>
                    <th>Assigned Teacher</th>
                    <th>Actions</th>
                </tr>
                <?php while ($subject = $subjects->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $subject['subject_code']; ?></td>
                        <td><?php echo $subject['subject_name']; ?></td>
                        <td><?php echo $subject['teacher_name'] ?? 'Not Assigned'; ?></td>
                        <td>
                            <a href="edit_subject.php?id=<?php echo $subject['id']; ?>">Edit</a>
                            <a href="delete_subject.php?id=<?php echo $subject['id']; ?>" 
                               onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html> 