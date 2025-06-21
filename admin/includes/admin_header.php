<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Determine the base path for links
$current_path = $_SERVER['PHP_SELF'];
$path_parts = explode('/', $current_path);
$admin_pos = array_search('admin', $path_parts);
$depth = count($path_parts) - $admin_pos - 1;
$base_path = str_repeat('../', $depth > 1 ? $depth - 1 : 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom Color Scheme -->
    <style>
        :root {
            --primary-color: #021F3F;
            --secondary-color: #C8A77E;
            --primary-hover: #042b59;
            --secondary-hover: #b39268;
        }

        .bg-gradient-primary-to-secondary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
        
        .icon-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .icon-circle.bg-primary,
        .icon-circle.bg-danger {
            background-color: var(--primary-color) !important;
        }
        
        .icon-circle.bg-success,
        .icon-circle.bg-warning {
            background-color: var(--secondary-color) !important;
            color: white !important;
        }
        
        .icon-circle.bg-info,
        .icon-circle.bg-light {
            background-color: rgba(2, 31, 63, 0.1) !important;
        }
        
        .text-primary,
        .text-danger {
            color: var(--primary-color) !important;
        }
        
        .text-success,
        .text-info,
        .text-warning {
            color: var(--secondary-color) !important;
        }
        
        .btn-primary,
        .btn-danger {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }
        
        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active,
        .btn-danger:hover,
        .btn-danger:focus,
        .btn-danger:active {
            background-color: var(--primary-hover) !important;
            border-color: var(--primary-hover) !important;
        }
        
        .btn-success,
        .btn-info,
        .btn-warning {
            background-color: var(--secondary-color) !important;
            border-color: var(--secondary-color) !important;
            color: white !important;
        }
        
        .btn-success:hover,
        .btn-info:hover,
        .btn-warning:hover,
        .btn-success:focus,
        .btn-info:focus,
        .btn-warning:focus,
        .btn-success:active,
        .btn-info:active,
        .btn-warning:active {
            background-color: var(--secondary-hover) !important;
            border-color: var(--secondary-hover) !important;
        }
        
        .btn-outline-primary {
            color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }
        
        .btn-outline-primary:hover,
        .btn-outline-primary:focus,
        .btn-outline-primary:active {
            background-color: var(--primary-color) !important;
            color: white !important;
        }
        
        .btn-outline-secondary:hover,
        .btn-outline-secondary:focus,
        .btn-outline-secondary:active {
            background-color: var(--secondary-color) !important;
            border-color: var(--secondary-color) !important;
            color: white !important;
        }
        
        .bg-primary,
        .bg-danger {
            background-color: var(--primary-color) !important;
        }
        
        .bg-success,
        .bg-info,
        .bg-warning {
            background-color: var(--secondary-color) !important;
        }
        
        .badge.bg-primary,
        .badge.bg-danger {
            background-color: var(--primary-color) !important;
        }
        
        .badge.bg-success,
        .badge.bg-info,
        .badge.bg-warning {
            background-color: var(--secondary-color) !important;
            color: white !important;
        }
        
        .pagination .page-link {
            color: var(--primary-color) !important;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            color: white !important;
        }
        
        .pagination .page-link:hover {
            color: var(--secondary-color) !important;
        }
        
        .modal-header.bg-primary,
        .modal-header.bg-danger,
        .modal-header.bg-info {
            background-color: var(--primary-color) !important;
        }
        
        .alert-info {
            background-color: rgba(2, 31, 63, 0.1) !important;
            border-color: rgba(2, 31, 63, 0.2) !important;
            color: var(--primary-color) !important;
        }
        
        .alert-warning {
            background-color: rgba(200, 167, 126, 0.1) !important;
            border-color: rgba(200, 167, 126, 0.2) !important;
            color: var(--secondary-color) !important;
        }
        
        .navbar {
            background-color: var(--primary-color) !important;
        }
        
        .navbar-vertical.bg-white {
            background-color: white !important;
            border-right: 1px solid rgba(2, 31, 63, 0.1);
        }
        
        .nav-link.active,
        .nav-link:hover {
            color: var(--primary-color) !important;
        }
        
        .dropdown-item:hover,
        .dropdown-item:focus {
            background-color: rgba(2, 31, 63, 0.05) !important;
            color: var(--primary-color) !important;
        }

        /* For form elements */
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 0.25rem rgba(2, 31, 63, 0.25) !important;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }

        /* Navbar styles */
        .top-navbar {
            background: var(--primary-color) !important;
            padding: 0.5rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            color: white !important;
            font-weight: 600;
            font-size: 1.5rem;
            padding: 0 1rem;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            padding: 1rem 1.5rem !important;
            position: relative;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: var(--secondary-color) !important;
            background: rgba(200, 167, 126, 0.1);
        }

        .nav-link.active {
            color: var(--secondary-color) !important;
            background: rgba(2, 31, 63, 0.4);
        }
        
        .nav-link i {
            margin-right: 8px;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            color: white;
            padding: 0 1rem;
        }
        
        .user-profile img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid var(--secondary-color);
        }

        .user-info {
            line-height: 1.2;
        }
        
        .user-name {
            font-size: 0.9rem;
            margin: 0;
        }
        
        .user-role {
            font-size: 0.8rem;
            color: var(--secondary-color);
            margin: 0;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .dropdown-item {
            padding: 0.7rem 1.5rem;
        }
        
        .dropdown-item i {
            margin-right: 8px;
            width: 20px;
            color: var(--primary-color);
        }

        .content-wrapper {
            padding: 20px;
            margin-top: 20px;
        }

        @media (max-width: 991px) {
            .navbar-collapse {
                background: var(--primary-color);
                padding: 1rem;
                margin-top: 0.5rem;
                border-radius: 8px;
            }
        }

        .profile-image {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid var(--secondary-color);
            object-fit: cover;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg top-navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="/BSIT2C_AMS/admin/dashboard.php">
            <i class="fas fa-graduation-cap"></i> Admin
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                       href="/BSIT2C_AMS/admin/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/subjects/') ? 'active' : ''; ?>" 
                       href="/BSIT2C_AMS/admin/subjects/index.php">
                        <i class="fas fa-book"></i> Subjects
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/teachers/') ? 'active' : ''; ?>" 
                       href="/BSIT2C_AMS/admin/teachers/index.php">
                        <i class="fas fa-chalkboard-teacher"></i> Teachers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/assignments/') ? 'active' : ''; ?>" 
                       href="/BSIT2C_AMS/admin/assignments/index.php">
                        <i class="fas fa-tasks"></i> Assignments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/students/') ? 'active' : ''; ?>" 
                       href="/BSIT2C_AMS/admin/students/index.php">
                        <i class="fas fa-user-graduate"></i> Students
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/enrollment/') ? 'active' : ''; ?>" 
                       href="/BSIT2C_AMS/admin/enrollment/index.php">
                        <i class="fas fa-user-plus"></i> Enrollment
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/reports/') ? 'active' : ''; ?>" 
                       href="/BSIT2C_AMS/admin/reports/index.php">
                        <i class="fas fa-file-alt"></i> Reports
                    </a>
                </li>
            </ul>
            
            <div class="dropdown">
                <div class="user-profile" data-bs-toggle="dropdown">
                    <?php
                    // Get admin data if not already set
                    if (!isset($admin) && isset($_SESSION['admin_id'])) {
                        $admin_query = "SELECT a.*, u.username, u.last_login 
                                       FROM admins a 
                                       JOIN users u ON a.user_id = u.id 
                                       WHERE a.id = ?";
                        $admin_stmt = $conn->prepare($admin_query);
                        $admin_stmt->bind_param("i", $_SESSION['admin_id']);
                        $admin_stmt->execute();
                        $admin_result = $admin_stmt->get_result();
                        $admin = $admin_result->fetch_assoc();
                    }
                    
                    // Get admin profile image if available, otherwise use placeholder
                    $admin_image = "../assets/img/default-profile.png"; // Default image
                    
                    if (isset($admin) && isset($admin['profile_image']) && !empty($admin['profile_image'])) {
                        // Check if we need to add proper path prefixes
                        if (strpos($admin['profile_image'], '/') === 0) {
                            // Already an absolute path
                            $image_path = $admin['profile_image'];
                        } else {
                            // Relative path, make it absolute from site root
                            $image_path = '/BSIT2C_AMS/' . $admin['profile_image'];
                        }
                        
                        // Debug info
                        echo "<!-- Debug: header profile_image DB path: " . htmlspecialchars($admin['profile_image']) . " -->";
                        echo "<!-- Debug: header profile_image final path: " . htmlspecialchars($image_path) . " -->";
                        
                        $admin_image = $image_path;
                    }
                    ?>
                    <img src="<?php echo $admin_image; ?>" alt="Admin" class="profile-image" 
                         onerror="this.onerror=null; this.src='/BSIT2C_AMS/assets/img/default-profile.png';">
                    <div class="user-info">
                        <p class="user-name">
                            <?php 
                            if (isset($admin) && isset($admin['first_name']) && isset($admin['last_name'])) {
                                echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']);
                            } else {
                                echo 'Administrator';
                            }
                            ?>
                        </p>
                        <p class="user-role">Administrator</p>
                    </div>
                    <i class="fas fa-chevron-down ms-2"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="/BSIT2C_AMS/admin/profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a href="#" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#logoutModal">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="logoutModalLabel">
                    <i class="fas fa-sign-out-alt me-2"></i> Confirm Logout
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-0">Are you sure you want to log out of your account? Any unsaved changes will be lost.</p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="/BSIT2C_AMS/logout.php" method="POST" class="d-inline">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="content-wrapper">
</body>
</html>