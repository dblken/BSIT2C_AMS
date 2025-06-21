<?php
// Ensure no errors are displayed in the output
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/config/database.php';

// Check if token, email and role are provided
$token = isset($_GET['token']) ? $_GET['token'] : '';
$email = isset($_GET['email']) ? $_GET['email'] : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';

// Validate token
$valid_token = false;
$error_message = '';

try {
    if ($token && $email && $role) {
        // Check if token exists and is valid
        $stmt = $conn->prepare('SELECT * FROM password_resets WHERE email = ? AND token = ? AND role = ? AND expires_at > NOW()');
        $stmt->bind_param('sss', $email, $token, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $valid_token = true;
        } else {
            // Check if token exists but expired
            $stmt = $conn->prepare('SELECT * FROM password_resets WHERE email = ? AND token = ? AND role = ? AND expires_at <= NOW()');
            $stmt->bind_param('sss', $email, $token, $role);
            $stmt->execute();
            $expired_result = $stmt->get_result();
            
            if ($expired_result->num_rows > 0) {
                $error_message = 'This password reset link has expired. Please request a new one.';
            } else {
                $error_message = 'Invalid password reset link. Please request a new one.';
            }
        }
        $stmt->close();
    } else {
        $error_message = 'Invalid password reset link. Please request a new one.';
    }

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate password
        if (strlen($password) < 6) {
            $error_message = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirm_password) {
            $error_message = 'Passwords do not match.';
        } else {
            // Hash the new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update password based on role
            if ($role === 'student') {
                $stmt = $conn->prepare('UPDATE students SET password = ? WHERE email = ?');
                $stmt->bind_param('ss', $hashed_password, $email);
            } elseif ($role === 'teacher') {
                $stmt = $conn->prepare('UPDATE teachers SET password = ? WHERE email = ?');
                $stmt->bind_param('ss', $hashed_password, $email);
            } elseif ($role === 'admin') {
                $stmt = $conn->prepare('UPDATE admins SET password = ? WHERE email = ?');
                $stmt->bind_param('ss', $hashed_password, $email);
            }
            
            if ($stmt->execute()) {
                // Delete the token
                $delete_stmt = $conn->prepare('DELETE FROM password_resets WHERE email = ?');
                $delete_stmt->bind_param('s', $email);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                // Set success message
                $_SESSION['success'] = 'Your password has been reset successfully. You can now login with your new password.';
                
                // Determine the correct redirect URL based on role
                $redirect_url = 'index.php';
                if ($role === 'student') {
                    $redirect_url = 'student/login.php';
                } elseif ($role === 'teacher') {
                    $redirect_url = 'teacher/login.php';
                } elseif ($role === 'admin') {
                    $redirect_url = 'admin/index.php';
                }
                
                header("Location: $redirect_url");
                exit;
            } else {
                $error_message = 'Failed to update password. Database error: ' . $conn->error;
            }
            $stmt->close();
        }
    }
} catch (Exception $e) {
    error_log("Error in reset_password.php: " . $e->getMessage());
    $error_message = 'An error occurred while processing your request. Please try again later.';
    $valid_token = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 50px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #007bff;
            color: white;
            text-align: center;
            border-radius: 10px 10px 0 0 !important;
            padding: 20px;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            width: 100%;
        }
        .btn-primary:hover {
            background-color: #0069d9;
            border-color: #0062cc;
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Reset Password</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!$valid_token): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                            <div class="text-center mt-3">
                                <?php 
                                $login_url = 'index.php';
                                if ($role === 'student') {
                                    $login_url = 'student/login.php';
                                } elseif ($role === 'teacher') {
                                    $login_url = 'teacher/login.php';
                                } elseif ($role === 'admin') {
                                    $login_url = 'admin/index.php';
                                }
                                ?>
                                <a href="<?php echo htmlspecialchars($login_url); ?>" class="btn btn-primary">Back to Login</a>
                            </div>
                        <?php else: ?>
                            <?php if ($error_message): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo htmlspecialchars($error_message); ?>
                                </div>
                            <?php endif; ?>
                            <form method="post">
                                <div class="form-group">
                                    <label for="password">New Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="form-text text-muted">Password must be at least 6 characters long.</small>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">Confirm Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Reset Password</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html> 