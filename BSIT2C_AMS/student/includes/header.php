<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get active page
$current_page = basename($_SERVER['PHP_SELF']);
$base_path = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2);

// Get student name for header
if (!isset($_SESSION['student_name'])) {
    require_once $base_path . 'config/database.php';
    $student_id = $_SESSION['student_id'];
    $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['student_name'] = $result->fetch_assoc()['name'];
    } else {
        $_SESSION['student_name'] = 'Student';
    }
}
?>

<header class="dashboard-header bg-dark text-white mb-4">
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="<?= $base_path ?>student/dashboard.php">Student Portal</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>" href="<?= $base_path ?>student/dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'subjects.php' ? 'active' : '' ?>" href="<?= $base_path ?>student/subjects.php">
                                <i class="bi bi-book me-1"></i> My Subjects
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'attendance.php' ? 'active' : '' ?>" href="<?= $base_path ?>student/attendance.php">
                                <i class="bi bi-clipboard-check me-1"></i> Attendance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'profile.php' ? 'active' : '' ?>" href="<?= $base_path ?>student/profile.php">
                                <i class="bi bi-person-circle me-1"></i> Profile
                            </a>
                        </li>
                    </ul>
                    <div class="d-flex">
                        <span class="navbar-text me-3">
                            <i class="bi bi-person me-1"></i> 
                            <?= htmlspecialchars($_SESSION['student_name']) ?>
                        </span>
                        <a href="<?= $base_path ?>student/logout.php" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-box-arrow-right me-1"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    </div>
</header> 