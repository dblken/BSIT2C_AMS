<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="index.php" class="brand-link">
        <span class="brand-text font-weight-light">Attendance System</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="info">
                <a href="#" class="d-block">
                    <?php echo $_SESSION['name']; ?>
                    <small>(Teacher)</small>
                </a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                <li class="nav-item">
                    <a href="../teacher/dashboard.php" class="nav-link">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../teacher/schedule.php" class="nav-link">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <p>My Schedule</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../teacher/attendance.php" class="nav-link">
                        <i class="nav-icon fas fa-user-check"></i>
                        <p>Manage Attendance</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/BSIT2C_AMS/logout.php" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>Logout</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>