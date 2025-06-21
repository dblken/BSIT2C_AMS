<!-- Add this to your navigation menu -->
<li class="nav-item">
    <a class="nav-link" href="<?php echo BASE_URL; ?>admin/enrollment/">
        <i class="fas fa-user-plus"></i>
        <span>Student Enrollment</span>
    </a>
</li>
<ul class="navbar-nav ms-auto">
    <li class="nav-item">
        <a class="nav-link" href="<?php echo '/BSIT2C_AMS/logout.php'; ?>">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </li>
</ul>

<!-- Add these if they're not already in your header -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        .btn-sm { margin: 2px; }
        .table td { vertical-align: middle; }
    </style>
</head>
<body>