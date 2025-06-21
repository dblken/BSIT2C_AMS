<?php
// Get sample credentials from the database
require_once 'config/database.php';

$query = "SELECT u.username, u.password, t.first_name, t.last_name 
          FROM users u 
          JOIN teachers t ON u.id = t.user_id 
          LIMIT 1";

$result = $conn->query($query);
$teacher = null;

if ($result && $result->num_rows > 0) {
    $teacher = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teacher Login Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .login-info {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 4px;
        }
        .btn-test {
            background-color: #2196F3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Teacher Login Test</h1>
        
        <?php if ($teacher): ?>
        <div class="login-info">
            <h3>Sample Teacher Account</h3>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($teacher['username']); ?></p>
            <p><strong>Password:</strong> <?php echo htmlspecialchars($teacher['password']); ?></p>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></p>
        </div>
        
        <form action="teacher/login.php" method="post" id="testLoginForm">
            <input type="hidden" name="username" value="<?php echo htmlspecialchars($teacher['username']); ?>">
            <input type="hidden" name="password" value="<?php echo htmlspecialchars($teacher['password']); ?>">
            <button type="submit" class="btn btn-test">Test Teacher Login</button>
        </form>
        <?php else: ?>
        <div class="login-info">
            <p>No teacher accounts found in the database.</p>
        </div>
        <?php endif; ?>
        
        <p><a href="index.php" class="btn">Back to Home</a></p>
    </div>
</body>
</html> 