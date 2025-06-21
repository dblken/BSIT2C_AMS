<?php
// Include the database connection file
require_once '../includes/db_connection.php';

// Check if the connection is successful
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>

<div class="container-fluid">
    <div class="row">
        <!-- Quick Action Cards -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Subjects</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php
                                $query = "SELECT COUNT(*) as count FROM subjects";
                                $result = mysqli_query($conn, $query);
                                $row = mysqli_fetch_assoc($result);
                                echo $row['count'];
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <a href="../subjects/index.php" class="text-decoration-none">
                                <i class="fas fa-book fa-2x text-gray-300"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Teachers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php
                                $query = "SELECT COUNT(*) as count FROM teachers";
                                $result = mysqli_query($conn, $query);
                                $row = mysqli_fetch_assoc($result);
                                echo $row['count'];
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <a href="../teachers/index.php" class="text-decoration-none">
                                <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Students</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php
                                $query = "SELECT COUNT(*) as count FROM students";
                                $result = mysqli_query($conn, $query);
                                $row = mysqli_fetch_assoc($result);
                                echo $row['count'];
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <a href="../students/index.php" class="text-decoration-none">
                                <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Attendance Records</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php
                                $query = "SELECT COUNT(*) as count FROM attendance";
                                $result = mysqli_query($conn, $query);
                                $row = mysqli_fetch_assoc($result);
                                echo $row['count'];
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <a href="../attendance/index.php" class="text-decoration-none">
                                <i class="fas fa-clipboard-check fa-2x text-gray-300"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Action Buttons -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <a href="../subjects/index.php" class="btn btn-primary mr-2">
                        <i class="fas fa-plus"></i> Add Subject
                    </a>
                    <a href="../teachers/index.php" class="btn btn-success mr-2">
                        <i class="fas fa-plus"></i> Add Teacher
                    </a>
                    <a href="../students/index.php" class="btn btn-info mr-2">
                        <i class="fas fa-plus"></i> Add Student
                    </a>
                    <a href="../attendance/index.php" class="btn btn-warning">
                        <i class="fas fa-clipboard-check"></i> Record Attendance
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities Section -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activities</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Activity</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Add your recent activities here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
}

.border-left-primary {
    border-left: 4px solid #4e73df !important;
}

.border-left-success {
    border-left: 4px solid #1cc88a !important;
}

.border-left-info {
    border-left: 4px solid #36b9cc !important;
}

.border-left-warning {
    border-left: 4px solid #f6c23e !important;
}

.nav-link {
    transition: all 0.3s ease;
}

.nav-link:hover {
    background-color: rgba(78, 115, 223, 0.1);
    transform: translateX(5px);
}

.nav-link i {
    margin-right: 8px;
}
</style> 