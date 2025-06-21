<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $subject_ids = $_POST['subjects'];

    // First, remove all existing enrollments for this student
    $delete_sql = "DELETE FROM student_subjects WHERE student_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // Add new enrollments
    if (!empty($subject_ids)) {
        $insert_sql = "INSERT INTO student_subjects (student_id, subject_id) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_sql);
        foreach ($subject_ids as $subject_id) {
            $stmt->bind_param("ii", $student_id, $subject_id);
            $stmt->execute();
        }
    }
}

// Get all students
$students_query = "SELECT id, full_name FROM users WHERE role = 'student'";
$students = $conn->query($students_query);

// Get all subjects
$subjects_query = "SELECT id, subject_code, subject_name FROM subjects";
$subjects = $conn->query($subjects_query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Enroll Students - BSIT 2C</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h2>Enroll Students in Subjects - BSIT 2C</h2>
        
        <form method="POST">
            <select name="student_id" required>
                <option value="">Select Student</option>
                <?php while ($student = $students->fetch_assoc()): ?>
                    <option value="<?php echo $student['id']; ?>">
                        <?php echo $student['full_name']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <h3>Select Subjects</h3>
            <?php 
            $subjects->data_seek(0); // Reset subjects result pointer
            while ($subject = $subjects->fetch_assoc()): 
            ?>
                <label>
                    <input type="checkbox" name="subjects[]" value="<?php echo $subject['id']; ?>">
                    <?php echo $subject['subject_code'] . ' - ' . $subject['subject_name']; ?>
                </label>
            <?php endwhile; ?>

            <button type="submit">Enroll Student</button>
        </form>

        <!-- Display Current Enrollments -->
        <div class="enrollments-section">
            <h3>Current Enrollments</h3>
            <?php
            $enrollments_query = "SELECT u.full_name, s.subject_code, s.subject_name 
                                FROM users u 
                                JOIN student_subjects ss ON u.id = ss.student_id 
                                JOIN subjects s ON ss.subject_id = s.id 
                                WHERE u.role = 'student' 
                                ORDER BY u.full_name, s.subject_code";
            $enrollments = $conn->query($enrollments_query);
            ?>
            <table>
                <tr>
                    <th>Student Name</th>
                    <th>Subject Code</th>
                    <th>Subject Name</th>
                </tr>
                <?php while ($enrollment = $enrollments->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $enrollment['full_name']; ?></td>
                        <td><?php echo $enrollment['subject_code']; ?></td>
                        <td><?php echo $enrollment['subject_name']; ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html> 