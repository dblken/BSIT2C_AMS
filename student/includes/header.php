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

// Get student name and status for header
if (isset($_SESSION['student_logged_in']) && $_SESSION['student_logged_in'] === true) {
    $id = $_SESSION['student_id'] ?? 0;

    if (!isset($_SESSION['name']) || !isset($_SESSION['status'])) {
        require_once $base_path . 'config/database.php';
        $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, status FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
            $_SESSION['name'] = $student['name'];
            $_SESSION['status'] = $student['status'];
            // Default profile picture
            $_SESSION['profile_picture'] = 'uploads/profile/default.png';
        }
        $stmt->close();
    } else {
        // Always refresh name on each page load to ensure it's current
        require_once $base_path . 'config/database.php';
        $refresh_stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM students WHERE id = ?");
        $refresh_stmt->bind_param("i", $id);
        $refresh_stmt->execute();
        $refresh_result = $refresh_stmt->get_result();
        
        if ($refresh_result->num_rows > 0) {
            $refresh_student = $refresh_result->fetch_assoc();
            $_SESSION['name'] = $refresh_student['name'];
        }
        $refresh_stmt->close();
    }

    $name = $_SESSION['name'] ?? 'Student';
    $status = $_SESSION['status'] ?? 'Active';
    $profile_picture = $_SESSION['profile_picture'] ?? 'uploads/profile/default.png';
} else {
    header("Location: login.php");
    exit();
}

// Check if student is active, if not redirect to login with error
if ($status !== 'Active') {
    // Destroy the session
    session_unset();
    session_destroy();
    
    // Redirect with error message
    session_start();
    $_SESSION['error'] = "Your account is inactive. Please contact the administrator.";
    header("Location: " . $base_path . "index.php");
    exit();
}

// Get profile picture path
$profile_pic = '../uploads/profile/default.png';
if (!empty($_SESSION['profile_picture']) && file_exists('../uploads/profile/' . $_SESSION['profile_picture'])) {
    $profile_pic = '../uploads/profile/' . $_SESSION['profile_picture'];
}
?>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="<?= $base_path ?>student/dashboard.php">
            <i class="bi bi-calendar-check"></i> BSIT 2C AMS
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav mx-auto">
                <a class="nav-item nav-link <?= $current_page === 'dashboard.php' ? 'active fw-bold' : '' ?>" href="<?= $base_path ?>student/dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a class="nav-item nav-link <?= $current_page === 'attendance.php' ? 'active fw-bold' : '' ?>" href="<?= $base_path ?>student/attendance.php">
                    <i class="bi bi-clipboard-check"></i> Attendance Records
                </a>
                <a class="nav-item nav-link <?= $current_page === 'timetable.php' ? 'active fw-bold' : '' ?>" href="<?= $base_path ?>student/timetable.php">
                    <i class="bi bi-calendar-week"></i> Timetable
                </a>
            </div>
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                    <div class="profile-img-small me-2">
                        <img src="<?= $profile_pic ?>" alt="Profile" class="rounded-circle" width="32" height="32">
                    </div>
                    <?php echo htmlspecialchars($name); ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= $base_path ?>student/profile.php"><i class="bi bi-person"></i> Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #021F3F; color: white;">
                <h5 class="modal-title" id="logoutModalLabel"><i class="bi bi-box-arrow-right me-2"></i>Confirm Logout</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="bi bi-exclamation-circle text-warning" style="font-size: 3rem;"></i>
                </div>
                <p class="text-center">Are you sure you want to logout from the Student Portal?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1"></i>Cancel</button>
                <a href="<?= $base_path ?>logout.php" class="btn btn-danger"><i class="bi bi-box-arrow-right me-1"></i>Yes, Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- Welcome Header -->
<div class="container mt-4">
    <div class="welcome-card card p-4 mb-4 text-white">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1">Welcome, <?php echo htmlspecialchars($name); ?>!</h4>
                <p class="text-white-50 mb-0">
                    <?php
                    switch($current_page) {
                        case 'dashboard.php':
                            echo "Here's an overview of your attendance and schedule.";
                            break;
                        case 'attendance.php':
                            echo "View and track your attendance records by subject.";
                            break;
                        case 'timetable.php':
                            echo "View your weekly class schedule.";
                            break;
                        case 'profile.php':
                            echo "View and update your profile information.";
                            break;
                        default:
                            echo "Welcome to your student portal.";
                    }
                    ?>
                </p>
            </div>
            <div class="digital-clock">
                <div id="time">00:00:00</div>
                <div class="date"><?php echo date('l, F j, Y'); ?></div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Import Poppins font */
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    /* Apply font to everything */
    body, .navbar, .dropdown-menu, .card, .btn {
        font-family: 'Poppins', sans-serif;
    }
    
    /* Header and navigation styles */
    .navbar { 
        background-color: #021F3F;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2); 
        padding: 0.75rem 0;
    }
    .navbar-brand { 
        font-weight: 600; 
        color: #fff; 
        font-size: 1.25rem;
    }
    .navbar .navbar-nav.mx-auto {
        gap: 1rem;
    }
    .welcome-card { 
        background: linear-gradient(135deg, #021F3F, #021F3F);
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        border: none;
    }
    
    /* Clock styles */
    .digital-clock {
        background: rgba(0,0,0,0.2);
        color: #ecf0f1;
        padding: 10px 15px;
        border-radius: 5px;
        font-weight: 500;
        font-size: 1.2rem;
        text-align: center;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .digital-clock .date {
        font-size: 0.85rem;
        opacity: 0.8;
    }

    /* Make sure profile images are properly styled */
    .profile-img-small img {
        object-fit: cover;
        border: 2px solid #C8A77E;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    /* Centered menu styles */
    .navbar-nav .nav-link {
        padding: 0.75rem 1.25rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border-radius: 5px;
        color: rgba(255, 255, 255, 0.85);
    }
    
    .navbar-nav .nav-link:hover {
        color: #C8A77E;
        background-color: rgba(200, 167, 126, 0.1);
    }
    
    .navbar-nav .nav-link.active {
        color: #C8A77E;
        background-color: rgba(200, 167, 126, 0.15);
        font-weight: 600;
    }
    
    /* Profile dropdown styles */
    .nav-item.dropdown {
        margin-left: 1.5rem;
        padding-left: 1.5rem;
    }
    
    .nav-link.dropdown-toggle {
        color: #FFFFFF !important;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        font-weight: 500;
    }
    .dropdown-menu {
        background-color: #fff;
        border: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .dropdown-item {
        padding: 0.7rem 1.2rem;
        font-weight: 500;
    }
    
    .dropdown-item:hover, .dropdown-item:focus {
        background-color: rgba(200, 167, 126, 0.1);
        color: #C8A77E;
    }
    
    /* Mobile responsive styles */
    @media (max-width: 991.98px) {
        .navbar-collapse {
            padding: 1rem 0;
        }
        .navbar-nav.mx-auto {
            margin: 1rem 0;
            text-align: center;
        }
        .nav-item.dropdown {
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            border-left: none;
            text-align: center;
        }
        .nav-link.dropdown-toggle {
            justify-content: center;
        }
    }
</style>

<script>
    // Function to update the digital clock
    function updateClock() {
        const now = new Date();
        let hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        
        // Convert to 12-hour format
        hours = hours % 12;
        hours = hours ? hours : 12; // the hour '0' should be '12'
        hours = hours.toString().padStart(2, '0');
        
        const timeElement = document.getElementById('time');
        if (timeElement) {
            timeElement.textContent = `${hours}:${minutes}:${seconds} ${ampm}`;
            
            // Update every second
            setTimeout(updateClock, 1000);
        }
    }
    
    // Start the clock when page loads
    document.addEventListener('DOMContentLoaded', updateClock);
</script>