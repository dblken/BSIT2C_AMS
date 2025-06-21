<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - BSIT 2C</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .logout {
            background: #ff4444;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome, <?php echo $_SESSION['name']; ?></h1>
        <a href="logout.php" class="logout">Logout</a>
    </div>
    <div class="content">
        <!-- Add your dashboard content here -->
    </div>
</body>
</html> 