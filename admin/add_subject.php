<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $subject_name = $conn->real_escape_string($_POST['subject_name']);
        $subject_code = $conn->real_escape_string($_POST['subject_code']);
        $description = $conn->real_escape_string($_POST['description']);

        // Check for duplicate subject name
        $stmt = $conn->prepare("SELECT id FROM subjects WHERE subject_name = ? OR (subject_code = ? AND ? != '')");
        $stmt->bind_param("sss", $subject_name, $subject_code, $subject_code);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Subject name or code already exists!");
        }

        // Insert new subject
        $sql = "INSERT INTO subjects (subject_name, subject_code, description, units, semester, school_year) 
                VALUES (?, ?, ?, 3, 'First', '2023-2024')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $subject_name, $subject_code, $description);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Subject '$subject_name' created successfully!";
            header("Location: dashboard.php");
            exit();
        } else {
            throw new Exception("Error creating subject");
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add New Subject - BSIT 2C AMS</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f4f4f4; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #45a049; }
        .success { color: green; padding: 10px; margin-bottom: 10px; }
        .error { color: red; padding: 10px; margin-bottom: 10px; }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add New Subject</h2>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Subject Name: *</label>
                <input type="text" name="subject_name" required>
            </div>

            <div class="form-group">
                <label>Subject Code:</label>
                <input type="text" name="subject_code">
            </div>

            <div class="form-group">
                <label>Description:</label>
                <textarea name="description" rows="4"></textarea>
            </div>

            <button type="submit" class="btn">Add Subject</button>
            <a href="dashboard.php" class="btn" style="background: #6c757d;">Back to Dashboard</a>
        </form>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
    <script>
        alert("<?php echo $_SESSION['success_message']; ?>");
        window.location.href = 'dashboard.php';
    </script>
    <?php endif; ?>
</body>
</html> 