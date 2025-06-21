<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    // Get the base URL for teachers
    $teacher_base = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - substr_count($_SERVER['SCRIPT_NAME'], '/teacher/') - 1) . 'teacher/';
    header('Location: ' . $teacher_base . 'login.php');
    exit;
}

// Get active page
$current_page = basename($_SERVER['PHP_SELF']);
$base_path = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2);

// Get teacher name for header
if (!isset($_SESSION['teacher_name'])) {
    require_once $base_path . 'config/database.php';
    $teacher_id = $_SESSION['teacher_id'];
    $stmt = $conn->prepare("SELECT first_name, last_name FROM teachers WHERE id = ?");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher = $result->fetch_assoc();
    $_SESSION['teacher_name'] = $teacher['first_name'] . ' ' . $teacher['last_name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - BSIT2C_AMS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: #021F3F;
            --secondary-color: #C8A77E;
            --primary-dark: #011327;
            --secondary-light: #d8b78e;
            --text-dark: #1f2937;
            --text-light: #6b7280;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
        }
        
        .dashboard-header {
            background-color: var(--primary-color) !important;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar-dark {
            background-color: var(--primary-color) !important;
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 600;
            color: white !important;
        }
        
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
            border-radius: 0.25rem;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .navbar-dark .navbar-nav .nav-link:hover {
            background-color: rgba(200, 167, 126, 0.2);
            color: white;
        }
        
        .navbar-dark .navbar-nav .nav-link.active {
            background-color: var(--secondary-color) !important;
            color: var(--primary-color) !important;
            font-weight: 600;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 0.5rem;
            padding: 0.5rem;
            margin-top: 0.5rem;
            background-color: white;
        }
        
        .dropdown-item {
            border-radius: 0.25rem;
            padding: 0.5rem 1rem;
            margin-bottom: 0.25rem;
            transition: all 0.2s;
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .dropdown-item:hover, .dropdown-item:focus {
            background-color: rgba(200, 167, 126, 0.2);
            color: var(--primary-color);
        }
        
        .dropdown-item.active, .dropdown-item:active {
            background-color: var(--secondary-color);
            color: var(--primary-color);
        }
        
        .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
            font-weight: 500;
        }
        
        .btn-outline-light:hover {
            background-color: var(--secondary-color) !important;
            color: var(--primary-color) !important;
            border-color: var(--secondary-color) !important;
        }
        
        .navbar-text {
            color: rgba(255, 255, 255, 0.85) !important;
            font-weight: 500;
        }
        
        .badge.bg-warning {
            background-color: var(--secondary-color) !important;
            color: var(--primary-dark) !important;
        }
    </style>
</head>
<body>
    <header class="dashboard-header">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-dark">
                <div class="container-fluid">
                    <a class="navbar-brand" href="<?= $base_path ?>teacher/dashboard.php">
                        <i class="bi bi-mortarboard me-2"></i>Teacher Portal
                    </a>
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
                                <a class="nav-link <?= $current_page === 'schedule.php' ? 'active' : '' ?>" href="<?= $base_path ?>teacher/schedule.php">
                                    <i class="bi bi-calendar3 me-1"></i> Schedule
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/subjects/') !== false ? 'active' : '' ?>" href="<?= $base_path ?>teacher/subjects/classes.php">
                                    <i class="bi bi-book me-1"></i> My Subjects
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $current_page === 'attendance.php' ? 'active' : '' ?>" href="<?= $base_path ?>teacher/attendance.php">
                                    <i class="bi bi-clipboard-check me-1"></i> Attendance
                                </a>
                            </li>
                        </ul>
                        <div class="d-flex align-items-center">
                            <span class="navbar-text me-3">
                                <i class="bi bi-person me-1"></i> 
                                <?= htmlspecialchars($_SESSION['teacher_name']) ?>
                            </span>
                            <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#logoutModal">
                                <i class="bi bi-box-arrow-right me-1"></i> Logout
                            </button>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </header>
    
    <div class="wrapper">
        <div class="main-content">
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: var(--primary-color); color: white;">
                    <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to logout from the Teacher Portal?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="<?= $base_path ?>logout.php" class="btn btn-danger">Yes, Logout</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 