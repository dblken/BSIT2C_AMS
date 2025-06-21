<?php
require_once '../../config/database.php';
include '../includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Manage Students</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="fas fa-plus"></i> Add New Student
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="studentsTable">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone Number</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM students ORDER BY student_id DESC";
                        $result = mysqli_query($conn, $query);
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr class='clickable-row' onclick='viewStudent({$row['id']})' style='cursor: pointer;'>
                                    <td>{$row['student_id']}</td>
                                    <td>{$row['first_name']} {$row['last_name']}</td>
                                    <td>{$row['email']}</td>
                                    <td>{$row['phone_number']}</td>
                                    <td onclick='event.stopPropagation();'>
                                        <select class='form-select status-select' 
                                                onchange='updateStatus({$row['id']}, this.value)' 
                                                style='background-color: " . ($row['status'] == 'Active' ? '#198754' : '#dc3545') . "; 
                                                       color: white;'>
                                            <option value='Active' " . ($row['status'] == 'Active' ? 'selected' : '') . ">Active</option>
                                            <option value='Inactive' " . ($row['status'] == 'Inactive' ? 'selected' : '') . ">Inactive</option>
                                        </select>
                                    </td>
                                    <td onclick='event.stopPropagation();'>
                                        <button class='btn btn-warning btn-sm' onclick='editStudent({$row['id']})'>
                                            <i class='fas fa-edit'></i>
                                        </button>
                                        <button class='btn btn-danger btn-sm' onclick='deleteStudent({$row['id']})'>
                                            <i class='fas fa-trash'></i>
                                        </button>
                                    </td>
                                </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStudentModalLabel">Add New Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addStudentForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="student_id" class="form-label">Student ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="student_id" name="student_id" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="middle_name" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name">
                        </div>
                        <div class="col-md-4">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="dob" name="dob" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveNewStudent">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- View Student Modal -->
<div class="modal fade" id="viewStudentModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Student Information</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="student-info">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-graduate fa-4x text-primary"></i>
                        <h4 class="mt-2" id="view_student_name"></h4>
                        <p class="text-muted" id="view_student_id_display"></p>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <label class="text-muted">Email</label>
                                <p id="view_email"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group">
                                <label class="text-muted">Phone Number</label>
                                <p id="view_phone_number"></p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <label class="text-muted">Gender</label>
                                <p id="view_gender"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group">
                                <label class="text-muted">Date of Birth</label>
                                <p id="view_date_of_birth"></p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="info-group">
                                <label class="text-muted">Address</label>
                                <p id="view_address"></p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="info-group">
                                <label class="text-muted">Status</label>
                                <p id="view_status"></p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-group">
                                <label class="text-muted">Program</label>
                                <p class="text-primary fw-bold">BSIT</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group">
                                <label class="text-muted">Year Level</label>
                                <p class="text-primary fw-bold">2nd Year</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group">
                                <label class="text-muted">Section</label>
                                <p class="text-primary fw-bold">Block C</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" onclick="editStudentFromView()">
                    <i class="fas fa-edit"></i> Edit
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add this Edit Student Modal -->
<div class="modal fade" id="editStudentModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStudentForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Student ID</label>
                                <input type="text" class="form-control" id="edit_student_id" name="student_id" readonly>
                            </div>
                            <div class="mb-3">
                                <label>First Name</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                            </div>
                            <div class="mb-3">
                                <label>Middle Name</label>
                                <input type="text" class="form-control" id="edit_middle_name" name="middle_name">
                            </div>
                            <div class="mb-3">
                                <label>Last Name</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Gender</label>
                                <select class="form-control" id="edit_gender" name="gender" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Date of Birth</label>
                                <input type="date" class="form-control" id="edit_date_of_birth" name="date_of_birth" required>
                            </div>
                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label>Phone Number</label>
                                <input type="text" class="form-control" id="edit_phone_number" name="phone_number">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.clickable-row:hover {
    background-color: #f8f9fa;
}

.status-select {
    border: none;
    width: 100px;
    cursor: pointer;
    padding: 5px;
    border-radius: 5px;
}

.status-select option {
    background-color: white;
    color: black;
}

.student-info .info-group {
    margin-bottom: 1.5rem;
}

.student-info .info-group label {
    display: block;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.student-info .info-group p {
    font-size: 1rem;
    margin: 0;
    color: #333;
}
</style>

<script>
// Add Student
$(document).ready(function() {
    // Save new student with improved error handling
    $('#saveNewStudent').click(function() {
        // Validate form
        var form = document.getElementById('addStudentForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Show loading
        $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
        $(this).prop('disabled', true);
        
        // Get form data
        var formData = $('#addStudentForm').serialize();
        
        // Log data being sent
        console.log("Sending data:", formData);
        
        // Submit via AJAX
        $.ajax({
            url: 'add_student.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                console.log("Response:", response);
                if (response.success) {
                    // Show success message
                    alert(response.message);
                    // Reset form
                    $('#addStudentForm')[0].reset();
                    // Close modal
                    $('#addStudentModal').modal('hide');
                    // Reload page to show new student
                    location.reload();
                } else {
                    // Show error message
                    alert(response.message || 'Unknown error occurred');
                }
            },
            error: function(xhr, status, error) {
                // Log detailed error information
                console.error("AJAX Error:", status, error);
                console.error("Response Text:", xhr.responseText);
                try {
                    var response = JSON.parse(xhr.responseText);
                    alert('Error adding student: ' + (response.message || error));
                } catch(e) {
                    alert('Error adding student: ' + error + '\nCheck console for details');
                }
            },
            complete: function() {
                // Reset button
                $('#saveNewStudent').html('Save');
                $('#saveNewStudent').prop('disabled', false);
            }
        });
    });
});

// View Student
function viewStudent(id) {
    fetch('../subjects/get_subject.php?id=${id}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const student = data.student;
                
                // Populate the view modal
                document.getElementById('view_student_name').textContent = 
                    `${student.first_name} ${student.last_name}`;
                document.getElementById('view_student_id_display').textContent = 
                    `Student ID: ${student.student_id}`;
                document.getElementById('view_email').textContent = student.email;
                document.getElementById('view_phone_number').textContent = student.phone_number;
                document.getElementById('view_gender').textContent = student.gender;
                document.getElementById('view_date_of_birth').textContent = student.date_of_birth;
                document.getElementById('view_address').textContent = student.address;
                document.getElementById('view_status').innerHTML = 
                    `<span class="badge ${student.status === 'Active' ? 'bg-success' : 'bg-danger'}">${student.status}</span>`;

                // Show the modal
                $('#viewStudentModal').modal('show');
            }
        });
}

// Delete Student
function deleteStudent(id) {
    if (confirm('Are you sure you want to delete this student?')) {
        fetch('../subjects/delete_subject.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Student deleted successfully!');
                location.reload();
            } else {
                alert(data.message || 'Error deleting student');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting student');
        });
    }
}

// Update Status
function updateStatus(id, newStatus) {
    fetch('../subjects/update_subject.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: id,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const selectElement = event.target;
            selectElement.style.backgroundColor = newStatus === 'Active' ? '#198754' : '#dc3545';
        } else {
            alert(data.message || 'Error updating status');
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating status');
        location.reload();
    });
}

// Edit Student
function editStudent(id) {
    // Prevent the row click event
    event.stopPropagation();
    
    // Fetch student data
    fetch('../subjects/get_subject.php?id=${id}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const student = data.student;
                
                // Populate the edit form
                document.getElementById('edit_id').value = student.id;
                document.getElementById('edit_student_id').value = student.student_id;
                document.getElementById('edit_first_name').value = student.first_name;
                document.getElementById('edit_middle_name').value = student.middle_name;
                document.getElementById('edit_last_name').value = student.last_name;
                document.getElementById('edit_gender').value = student.gender;
                document.getElementById('edit_date_of_birth').value = student.date_of_birth;
                document.getElementById('edit_email').value = student.email;
                document.getElementById('edit_phone_number').value = student.phone_number;
                document.getElementById('edit_address').value = student.address;

                // Show the modal
                $('#editStudentModal').modal('show');
            } else {
                alert('Error fetching student data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error fetching student data');
        });
}

// Handle edit form submission
document.getElementById('editStudentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('../subjects/update_subject.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Student updated successfully!');
            $('#editStudentModal').modal('hide');
            location.reload();
        } else {
            alert(data.message || 'Error updating student');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating student');
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?> 