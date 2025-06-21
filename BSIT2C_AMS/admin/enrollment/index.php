<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Enrollment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="../dashboard.php">Admin Dashboard</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="../teachers/index.php">Manage Teachers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../students/index.php">Manage Students</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../subjects/index.php">Manage Subjects</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="../enrollment/index.php">Enrollment</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../schedules/index.php">Schedules</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h2>Student Enrollment Management</h2>

    <div class="table-responsive mt-4">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $query = "SELECT id, student_id, first_name, last_name 
                         FROM students 
                         WHERE status = 'Active'
                         ORDER BY last_name, first_name";
                $result = $conn->query($query);
                
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>{$row['student_id']}</td>";
                    echo "<td>{$row['first_name']} {$row['last_name']}</td>";
                    echo "<td>
                            <button type='button' 
                                    class='btn btn-primary btn-sm'
                                    onclick='showEnrollModal({$row['id']}, \"{$row['first_name']} {$row['last_name']}\")'>
                                <i class='fas fa-user-plus'></i> Enroll
                            </button>
                            <button type='button' 
                                    class='btn btn-info btn-sm'
                                    onclick='showViewModal({$row['id']}, \"{$row['first_name']} {$row['last_name']}\")'>
                                <i class='fas fa-eye'></i> View
                            </button>
                          </td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Enrollment Modal -->
    <div class="modal fade" id="enrollmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Enroll Student</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="enrollmentForm">
                        <input type="hidden" name="student_id" id="modalStudentId">
                        <p class="fw-bold mb-3" id="modalStudentName"></p>
                        
                        <div class="mb-3">
                            <h6>Available Subjects and Schedules</h6>
                            <div class="schedule-list">
                                <?php
                                // Query to show available subjects for enrollment
                                $query = "SELECT 
                                    a.id as assignment_id,
                                    s.subject_code,
                                    s.subject_name,
                                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                                    a.preferred_day,
                                    TIME_FORMAT(a.time_start, '%h:%i %p') as formatted_start_time,
                                    TIME_FORMAT(a.time_end, '%h:%i %p') as formatted_end_time,
                                    a.location
                                    FROM assignments a
                                    JOIN subjects s ON a.subject_id = s.id
                                    JOIN teachers t ON a.teacher_id = t.id
                                    WHERE t.status = 'Active'
                                    ORDER BY a.preferred_day, a.time_start";
                                
                                $result = $conn->query($query);
                                
                                if ($result->num_rows > 0) {
                                    while ($assignment = $result->fetch_assoc()) {
                                        $day_name = [
                                            'M' => 'Monday',
                                            'T' => 'Tuesday',
                                            'W' => 'Wednesday',
                                            'TH' => 'Thursday',
                                            'F' => 'Friday',
                                            'SAT' => 'Saturday',
                                            'SUN' => 'Sunday'
                                        ][$assignment['preferred_day']] ?? $assignment['preferred_day'];

                                        echo "<div class='form-check mb-2'>";
                                        echo "<input class='form-check-input' type='checkbox' 
                                              name='assignment_ids[]' value='{$assignment['assignment_id']}' 
                                              id='assignment{$assignment['assignment_id']}'>";
                                        echo "<label class='form-check-label' for='assignment{$assignment['assignment_id']}'>";
                                        echo "<strong>{$assignment['subject_code']} - {$assignment['subject_name']}</strong><br>";
                                        echo "<small class='text-muted'>";
                                        echo "Teacher: {$assignment['teacher_name']}<br>";
                                        echo "Schedule: {$day_name}, ";
                                        echo $assignment['formatted_start_time'] . " - ";
                                        echo $assignment['formatted_end_time'];
                                        echo " | <strong>Location:</strong> " . ($assignment['location'] ?: 'TBA');
                                        echo "</small>";
                                        echo "</label>";
                                        echo "</div>";
                                    }
                                } else {
                                    echo "<div class='alert alert-info'>No subjects have been assigned to teachers yet.</div>";
                                }
                                ?>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="submitEnrollment()">
                        <i class="fas fa-save"></i> Save Enrollment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Enrolled Subjects</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewModalContent">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this after the enrollment form to display current enrollments -->
    <div class="modal fade" id="viewEnrollmentsModal" tabindex="-1" aria-labelledby="viewEnrollmentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewEnrollmentsModalLabel">Current Enrollments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="enrollmentsList">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Initialize modals
const enrollmentModal = new bootstrap.Modal(document.getElementById('enrollmentModal'));
const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));

// Function to show enrollment modal
function showEnrollModal(studentId, studentName) {
    document.getElementById('modalStudentId').value = studentId;
    document.getElementById('modalStudentName').textContent = 'Student: ' + studentName;
    enrollmentModal.show();
}

// Function to show view modal
function showViewModal(studentId, studentName) {
    fetch('get_enrollments.php?student_id=' + studentId)
        .then(response => response.json())
        .then(data => {
            const content = document.getElementById('viewModalContent');
            content.innerHTML = `<h6>Enrolled Subjects for ${studentName}</h6>`;
            
            if (data.success && data.enrollments.length > 0) {
                let html = '<div class="table-responsive"><table class="table table-bordered mt-3">';
                html += '<thead class="table-light"><tr><th>Subject</th><th>Schedule</th><th>Teacher</th><th>Location</th></tr></thead><tbody>';
                
                data.enrollments.forEach(enrollment => {
                    html += `<tr>
                        <td>${enrollment.subject_code} - ${enrollment.subject_name}</td>
                        <td>${enrollment.schedule}</td>
                        <td>${enrollment.teacher}</td>
                        <td>${enrollment.location}</td>
                    </tr>`;
                });
                
                html += '</tbody></table></div>';
                content.innerHTML += html;
            } else {
                content.innerHTML += '<p class="text-muted">No subjects enrolled for this student.</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('viewModalContent').innerHTML = 
                '<p class="text-danger">Error loading enrollments.</p>';
        });
    
    viewModal.show();
}

// Function to submit enrollment
function submitEnrollment() {
    const form = document.getElementById('enrollmentForm');
    const formData = new FormData(form);
    
    // Validate selection
    const selectedAssignments = formData.getAll('assignment_ids[]');
    if (selectedAssignments.length === 0) {
        alert('Please select at least one subject to enroll.');
        return;
    }

    // Submit enrollment
    fetch('process_enrollment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            enrollmentModal.hide();
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while processing the enrollment.');
    });
}

// Add this script to handle viewing enrollments
function viewEnrollments(studentId, studentName) {
    $('#viewEnrollmentsModalLabel').text('Enrollments for ' + studentName);
    $('#viewEnrollmentsModal').modal('show');
    
    // Clear previous data and show loading spinner
    $('#enrollmentsList').html('<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');
    
    // Fetch enrollments
    $.ajax({
        url: 'get_enrollments.php',
        type: 'GET',
        data: { student_id: studentId },
        dataType: 'json',
        success: function(response) {
            console.log("Enrollment response:", response); // Debug info
            
            if (response.success) {
                if (response.enrollments.length > 0) {
                    var html = '<table class="table table-striped">';
                    html += '<thead><tr><th>Subject</th><th>Teacher</th><th>Schedule</th><th>Location</th></tr></thead>';
                    html += '<tbody>';
                    
                    response.enrollments.forEach(function(enrollment) {
                        html += '<tr>';
                        html += '<td>' + enrollment.subject_code + ' - ' + enrollment.subject_name + '</td>';
                        html += '<td>' + enrollment.teacher + '</td>';
                        html += '<td>' + enrollment.schedule + '</td>';
                        html += '<td>' + (enrollment.location || 'TBA') + '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    $('#enrollmentsList').html(html);
                } else {
                    $('#enrollmentsList').html('<div class="alert alert-info">No enrollments found for this student.</div>');
                }
            } else {
                $('#enrollmentsList').html('<div class="alert alert-danger">' + response.message + '</div>');
            }
        },
        error: function(xhr, status, error) {
            $('#enrollmentsList').html('<div class="alert alert-danger">Error loading enrollments: ' + error + '</div>');
            console.error(xhr.responseText);
        }
    });
}

// Update the existing view button click handler
$(document).on('click', '.view-enrollments-btn', function() {
    var studentId = $(this).data('student-id');
    var studentName = $(this).data('student-name');
    viewEnrollments(studentId, studentName);
});
</script>

</body>
</html>