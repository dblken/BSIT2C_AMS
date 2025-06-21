<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $teacher_id = (int)$_POST['teacher_id'];
        $subject_id = (int)$_POST['subject_id'];

        // Get teacher and subject names for the success message
        $teacher_query = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM teachers WHERE id = ?");
        $teacher_query->bind_param("i", $teacher_id);
        $teacher_query->execute();
        $teacher_name = $teacher_query->get_result()->fetch_assoc()['name'];

        $subject_query = $conn->prepare("SELECT subject_name FROM subjects WHERE id = ?");
        $subject_query->bind_param("i", $subject_id);
        $subject_query->execute();
        $subject_name = $subject_query->get_result()->fetch_assoc()['subject_name'];

        // Update subject with teacher assignment
        $sql = "UPDATE subjects SET teacher_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $teacher_id, $subject_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Successfully assigned $teacher_name to teach $subject_name";
        } else {
            throw new Exception("Failed to assign teacher to subject");
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }

    header("Location: dashboard.php");
    exit();
}

// Get all active teachers
$teachers = $conn->query("SELECT id, first_name, last_name FROM teachers WHERE status = 'Active' ORDER BY last_name, first_name");

// Get all subjects without teachers
$subjects = $conn->query("SELECT id, subject_name, subject_code FROM subjects WHERE teacher_id IS NULL ORDER BY subject_name");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Assign Teacher to Subject</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f4f4f4; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #45a049; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Assign Teacher to Subject</h2>
        
        <form method="POST">
            <div class="form-group">
                <label>Select Teacher:</label>
                <select name="teacher_id" required>
                    <option value="">-- Select Teacher --</option>
                    <?php while ($teacher = $teachers->fetch_assoc()): ?>
                        <option value="<?php echo $teacher['id']; ?>">
                            <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Select Subject:</label>
                <select name="subject_id" required>
                    <option value="">-- Select Subject --</option>
                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                        <option value="<?php echo $subject['id']; ?>">
                            <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <button type="submit" class="btn">Assign Teacher</button>
        </form>
    </div>
</body>
</html> 