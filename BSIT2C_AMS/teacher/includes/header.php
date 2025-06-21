<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get active page
$current_page = basename($_SERVER['PHP_SELF']);
$base_path = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2);

// Get teacher name for header
if (!isset($_SESSION['teacher_name'])) {
    require_once $base_path . 'config/database.php';
    $teacher_id = $_SESSION['teacher_id'];
    $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM teachers WHERE id = ?");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['teacher_name'] = $result->fetch_assoc()['name'];
    } else {
        $_SESSION['teacher_name'] = 'Teacher';
    }
}
?>

<header class="dashboard-header bg-dark text-white mb-4">
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="<?= $base_path ?>teacher/dashboard.php">Teacher Portal</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>" href="<?= $base_path ?>teacher/dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'classes.php' ? 'active' : '' ?>" href="<?= $base_path ?>teacher/classes.php">
                                <i class="bi bi-book me-1"></i> My Classes
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?= strpos($current_page, 'attendance') !== false ? 'active' : '' ?>" href="#" id="attendanceDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-clipboard-check me-1"></i> Attendance
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="attendanceDropdown">
                                <li><a class="dropdown-item" href="<?= $base_path ?>teacher/attendance.php">Dashboard</a></li>
                                <li><a class="dropdown-item" href="<?= $base_path ?>teacher/attendance/index.php">Take Attendance</a></li>
                                <li><a class="dropdown-item" href="<?= $base_path ?>teacher/attendance/history.php">History</a></li>
                                <li><a class="dropdown-item" href="<?= $base_path ?>teacher/attendance/pending.php">
                                    Pending Records
                                    <?php
                                    // Count pending records
                                    $base_path_db = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2);
                                    require_once $base_path_db . 'config/database.php';
                                    $pending_count_query = "SELECT COUNT(DISTINCT id) as count FROM attendance WHERE teacher_id = ? AND is_pending = 1";
                                    $stmt = $conn->prepare($pending_count_query);
                                    $stmt->bind_param("i", $_SESSION['teacher_id']);
                                    $stmt->execute();
                                    $pending_count = $stmt->get_result()->fetch_assoc()['count'];
                                    
                                    if ($pending_count > 0): 
                                    ?>
                                    <span class="badge bg-warning text-dark ms-2"><?= $pending_count ?></span>
                                    <?php endif; ?>
                                </a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'students.php' ? 'active' : '' ?>" href="<?= $base_path ?>teacher/students.php">
                                <i class="bi bi-people me-1"></i> Students
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'profile.php' ? 'active' : '' ?>" href="<?= $base_path ?>teacher/profile.php">
                                <i class="bi bi-person-circle me-1"></i> Profile
                            </a>
                        </li>
                    </ul>
                    <div class="d-flex">
                        <span class="navbar-text me-3">
                            <i class="bi bi-person me-1"></i> 
                            <?= htmlspecialchars($_SESSION['teacher_name']) ?>
                        </span>
                        <a href="<?= $base_path ?>teacher/logout.php" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-box-arrow-right me-1"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    </div>
</header> 