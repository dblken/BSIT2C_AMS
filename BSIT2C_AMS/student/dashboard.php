<?php
session_start();
require_once '../config/database.php';

// Verify student role
if (!isset($_SESSION['student_id'])) {
    $_SESSION['error'] = "Access denied. Student privileges required.";
    header("Location: index.php");
    exit();
}

// Get student details
$sql = "SELECT s.*, u.username, u.last_login 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Get student's subjects
$sql = "SELECT s.*, ss.status as enrollment_status 
        FROM subjects s 
        JOIN student_subjects ss ON s.id = ss.subject_id 
        WHERE ss.student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$subjects = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard - BSIT 2C AMS</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f4f4f4; }
        .container { width: 95%; margin: 0 auto; padding: 20px; }
        .header { background: #333; color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .user-info { text-align: right; }
        .content { margin-top: 20px; }
        .student-info { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .subjects-list { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .subject-card { border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 4px; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        .logout-btn:hover { background: #c82333; }
        .modal-body { padding: 1.5rem; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Student Dashboard</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
            <br>
            <small>Last login: <?php echo $student['last_login']; ?></small>
            <br>
            <button class="logout-btn" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</button>
        </div>
    </div>

    <div class="container">
        <div class="content">
            <div class="student-info">
                <h2>Student Information</h2>
                <p>Student ID: <?php echo htmlspecialchars($student['student_id']); ?></p>
                <p>Program: <?php echo htmlspecialchars($student['program']); ?></p>
                <p>Year Level: <?php echo htmlspecialchars($student['year_level']); ?></p>
                <p>Section: <?php echo htmlspecialchars($student['section']); ?></p>
            </div>

            <div class="subjects-list">
                <h2>My Subjects</h2>
                <?php while ($subject = $subjects->fetch_assoc()): ?>
                    <div class="subject-card">
                        <h3><?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?></h3>
                        <p>Units: <?php echo $subject['units']; ?></p>
                        <p>Status: <?php echo $subject['enrollment_status']; ?></p>
                        <?php
                        // Get subject schedule
                        $sql = "SELECT * FROM timetable WHERE subject_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $subject['id']);
                        $stmt->execute();
                        $schedule = $stmt->get_result()->fetch_assoc();
                        if ($schedule) {
                            echo "<p>Schedule: " . 
                                 $schedule['day_of_week'] . ' ' . 
                                 date('h:i A', strtotime($schedule['start_time'])) . ' - ' . 
                                 date('h:i A', strtotime($schedule['end_time'])) . 
                                 ' (Room ' . $schedule['room'] . ')</p>';
                        }
                        ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    
    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #333; color: white;">
                    <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <p>Are you sure you want to logout from the Student Portal?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="/BSIT2C_AMS/logout.php" class="btn btn-danger">Yes, Logout</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>