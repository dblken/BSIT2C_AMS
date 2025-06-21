<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Get admin details
$sql = "SELECT a.*, u.username, u.last_login 
        FROM admins a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// Get statistics
$stats = [
    'students' => $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'],
    'teachers' => $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'],
    'subjects' => $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count']
];

// Get recent subjects
$recent_subjects = $conn->query("SELECT * FROM subjects ORDER BY created_at DESC LIMIT 5");

// Get recent teacher assignments
$recent_assignments = $conn->query("
    SELECT s.subject_name, t.first_name, t.last_name, s.updated_at
    FROM subjects s
    JOIN teachers t ON s.teacher_id = t.id
    WHERE s.teacher_id IS NOT NULL
    ORDER BY s.updated_at DESC
    LIMIT 5
");
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
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Admin Dashboard</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="teachers/index.php">Manage Teachers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="students/index.php">Manage Students</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="subjects/index.php">Manage Subjects</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="enrollment/index.php">Enrollment</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="schedules/index.php">Schedules</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <!-- Quick Action Buttons -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Quick Actions</h5>
                    <button class="btn btn-primary me-2" onclick="location.href='teachers/index.php'">
                        <i class="fas fa-chalkboard-teacher"></i> Manage Teachers
                    </button>
                    <button class="btn btn-success me-2" onclick="location.href='students/index.php'">
                        <i class="fas fa-user-graduate"></i> Manage Students
                    </button>
                    <button class="btn btn-info me-2" onclick="location.href='subjects/index.php'">
                        <i class="fas fa-book"></i> Manage Subjects
                    </button>
                    <button class="btn btn-warning me-2" onclick="location.href='assignments/index.php'">
                        <i class="fas fa-tasks"></i> Manage Assignments
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Teachers</h5>
                    <?php
                    $query = "SELECT COUNT(*) as count FROM teachers WHERE status = 'Active'";
                    $result = mysqli_query($conn, $query);
                    $row = mysqli_fetch_assoc($result);
                    ?>
                    <h2 class="card-text"><?php echo $row['count']; ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Students</h5>
                    <?php
                    $query = "SELECT COUNT(*) as count FROM students WHERE status = 'Active'";
                    $result = mysqli_query($conn, $query);
                    $row = mysqli_fetch_assoc($result);
                    ?>
                    <h2 class="card-text"><?php echo $row['count']; ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Subjects</h5>
                    <?php
                    $query = "SELECT COUNT(*) as count FROM subjects WHERE status = 'Active'";
                    $result = mysqli_query($conn, $query);
                    $row = mysqli_fetch_assoc($result);
                    ?>
                    <h2 class="card-text"><?php echo $row['count']; ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Assignments</h5>
                    <?php
                    $query = "SELECT COUNT(*) as count FROM teacher_subjects WHERE status = 'Active'";
                    $result = mysqli_query($conn, $query);
                    $row = mysqli_fetch_assoc($result);
                    ?>
                    <h2 class="card-text"><?php echo $row['count']; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    Recent Teacher Assignments
                </div>
                <div class="card-body">
                    <table>
                        <tr>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Date Assigned</th>
                        </tr>
                        <?php while ($assignment = $recent_assignments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($assignment['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($assignment['updated_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    Recent Activities
                </div>
                <div class="card-body">
                    <!-- Add recent activities table here -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?> 
</body>
</html> 