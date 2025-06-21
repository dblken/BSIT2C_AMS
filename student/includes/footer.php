<!-- Footer -->
<footer class="bg-white shadow-sm py-4 mt-auto border-top">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h5 class="text-primary mb-3">BSIT 2C Attendance Management System</h5>
                <p class="text-muted small mb-0">
                    Our attendance management system makes it easy to track your class attendance, 
                    view detailed records, and manage your student profile.
                </p>
            </div>
            <div class="col-md-3">
                <h6 class="mb-3">Quick Links</h6>
                <ul class="nav flex-column small">
                    <li class="nav-item">
                        <a class="nav-link text-muted ps-0" href="dashboard.php">
                            <i class="bi bi-house me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-muted ps-0" href="attendance.php">
                            <i class="bi bi-calendar-check me-2"></i>Attendance Records
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-muted ps-0" href="profile.php">
                            <i class="bi bi-person me-2"></i>Profile
                        </a>
                    </li>
                </ul>
            </div>
            <div class="col-md-3">
                <h6 class="mb-3">Help & Support</h6>
                <ul class="nav flex-column small">
                    <li class="nav-item">
                        <a class="nav-link text-muted ps-0" href="#">
                            <i class="bi bi-question-circle me-2"></i>FAQ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-muted ps-0" href="#">
                            <i class="bi bi-chat-left-text me-2"></i>Contact Support
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <hr class="my-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <small class="text-muted">
                    &copy; <?php echo date('Y'); ?> BSIT 2C Attendance Management System. All rights reserved.
                </small>
            </div>
            <div class="col-md-6 text-md-end">
                <small class="text-muted">
                    Version 1.0.0 | Last updated: <?php echo date('F Y'); ?>
                </small>
            </div>
        </div>
    </div>
</footer>

<!-- Back to top button -->
<a href="#" class="btn btn-primary btn-sm back-to-top position-fixed rounded-circle" role="button">
    <i class="bi bi-arrow-up"></i>
</a>

<style>
    .back-to-top {
        bottom: 20px;
        right: 20px;
        display: none;
        width: 40px;
        height: 40px;
        line-height: 40px;
        padding: 0;
        z-index: 1000;
    }
    
    .back-to-top i {
        line-height: 0;
    }
</style>

<script>
    // Back to top button functionality
    window.addEventListener('scroll', function() {
        var backToTopButton = document.querySelector('.back-to-top');
        if (window.pageYOffset > 300) {
            backToTopButton.style.display = 'block';
        } else {
            backToTopButton.style.display = 'none';
        }
    });
    
    document.querySelector('.back-to-top').addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({top: 0, behavior: 'smooth'});
    });
</script> 