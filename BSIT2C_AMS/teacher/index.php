<?php
session_start();
if (isset($_SESSION['teacher_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Count all classes for today that don't have attendance records
$missed_classes_count_query = "
    SELECT 
        COUNT(*) as count
    FROM 
        assignments a
    WHERE 
        a.teacher_id = ? AND
        a.preferred_day = ? AND
        NOT EXISTS (
            SELECT 1 FROM attendance att 
            WHERE att.teacher_id = a.teacher_id 
            AND att.subject_id = a.subject_id 
            AND att.assignment_id = a.id 
            AND att.attendance_date = ?
        )";

$stmt = mysqli_prepare($conn, $missed_classes_count_query);
mysqli_stmt_bind_param($stmt, "iis", $teacher_id, $day_of_week, $today);
mysqli_stmt_execute($stmt);
$missed_count_result = mysqli_stmt_get_result($stmt);
$missed_classes_count = $missed_count_result->fetch_assoc()['count'];
mysqli_stmt_close($stmt);

// If there are missed classes, show a notification
$show_missed_notification = ($missed_classes_count > 0);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teacher Login - BSIT 2C AMS</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .login-container { max-width: 400px; margin: 50px auto; padding: 20px; background: white; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #45a049; }
        .error { color: red; margin-bottom: 10px; }
        .links { text-align: center; margin-top: 15px; }
        .links a { color: #4CAF50; text-decoration: none; margin: 0 10px; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Teacher Login</h2>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if ($show_missed_notification): ?>
            <div class="alert alert-warning alert-dismissible fade show mb-4">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="bi bi-exclamation-triangle-fill display-5"></i>
                    </div>
                    <div>
                        <h4 class="alert-heading">Attendance Needed!</h4>
                        <p class="mb-0">You have <strong><?= $missed_classes_count ?> <?= $missed_classes_count == 1 ? 'class' : 'classes' ?></strong> scheduled today that <?= $missed_classes_count == 1 ? 'needs' : 'need' ?> attendance to be recorded.</p>
                        <a href="attendance/pending.php" class="btn btn-dark mt-2">
                            <i class="bi bi-arrow-right-circle"></i> Manage Class Attendance
                        </a>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <form action="login.php" method="post">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <div class="links">
            <a href="../admin/">Admin Login</a>
            <a href="../student/">Student Login</a>
        </div>
    </div>
</body>
</html> 