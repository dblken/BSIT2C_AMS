<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}
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
    <style>
        .top-navbar {
            background: linear-gradient(135deg, #0d6efd 0%, #0a4da3 100%);
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
            color: white !important;
            background: rgba(255,255,255,0.1);
        }

        .nav-link.active {
            color: white !important;
            background: rgba(255,255,255,0.2);
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
            border: 2px solid rgba(255,255,255,0.3);
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
            opacity: 0.8;
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
        }

        .content-wrapper {
            padding: 20px;
            margin-top: 20px;
        }

        @media (max-width: 991px) {
            .navbar-collapse {
                background: #0d6efd;
                padding: 1rem;
                margin-top: 0.5rem;
                border-radius: 8px;
            }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg top-navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fas fa-graduation-cap"></i> AMS
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                       href="../dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/subjects/') ? 'active' : ''; ?>" 
                       href="../subjects/">
                        <i class="fas fa-book"></i> Subjects
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/teachers/') ? 'active' : ''; ?>" 
                       href="../teachers/">
                        <i class="fas fa-chalkboard-teacher"></i> Teachers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/students/') ? 'active' : ''; ?>" 
                       href="../students/">
                        <i class="fas fa-user-graduate"></i> Students
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/attendance/') ? 'active' : ''; ?>" 
                       href="../attendance/">
                        <i class="fas fa-clipboard-check"></i> Attendance
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/enrollment/') ? 'active' : ''; ?>" 
                       href="../enrollment/">
                        <i class="fas fa-user-plus"></i> Enrollment
                    </a>
                </li>
            </ul>
            
            <div class="dropdown">
                <div class="user-profile" data-bs-toggle="dropdown">
                    <img src="https://via.placeholder.com/150" alt="Admin">
                    <div class="user-info">
                        <p class="user-name">Admin User</p>
                        <p class="user-role">Administrator</p>
                    </div>
                    <i class="fas fa-chevron-down ms-2"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="../admin/profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="../admin/settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="/BSIT2C_AMS/logout.php" method="POST" class="d-inline">
                            <button type="submit" class="dropdown-item text-danger" 
                                    onclick="return confirm('Are you sure you want to logout?');">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="content-wrapper">
</body>
</html>