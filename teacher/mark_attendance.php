<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Get teacher's classes
$classes_query = "SELECT c.* FROM classes c WHERE c.teacher_id = ?";
$stmt = $conn->prepare($classes_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$classes = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_id = $_POST['class_id'];
    $date = $_POST['date'];
    $attendance_data = $_POST['attendance'];

    foreach ($attendance_data as $student_id => $status) {
        $sql = "INSERT INTO attendance (student_id, class_id, date, status, marked_by) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissi", $student_id, $class_id, $date, $status, $teacher_id);
        $stmt->execute();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mark Attendance</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h2>Mark Attendance</h2>
        
        <form method="POST">
            <select name="class_id" required>
                <?php while ($class = $classes->fetch_assoc()): ?>
                    <option value="<?php echo $class['id']; ?>">
                        <?php echo $class['class_name'] . ' - ' . $class['section']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
            
            <input type="date" name="date" required>
            
            <table>
                <tr>
                    <th>Student Name</th>
                    <th>Attendance</th>
                </tr>
                <?php
                if (isset($_POST['class_id'])) {
                    $students_query = "SELECT u.id, u.full_name 
                                     FROM users u 
                                     JOIN students_classes sc ON u.id = sc.student_id 
                                     WHERE sc.class_id = ?";
                    $stmt = $conn->prepare($students_query);
                    $stmt->bind_param("i", $_POST['class_id']);
                    $stmt->execute();
                    $students = $stmt->get_result();
                    
                    while ($student = $students->fetch_assoc()):
                ?>
                    <tr>
                        <td><?php echo $student['full_name']; ?></td>
                        <td>
                            <select name="attendance[<?php echo $student['id']; ?>]">
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="late">Late</option>
                            </select>
                        </td>
                    </tr>
                <?php 
                    endwhile;
                }
                ?>
            </table>
            
            <button type="submit">Submit Attendance</button>
        </form>
    </div>
</body>
</html> 