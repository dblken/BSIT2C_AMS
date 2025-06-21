<?php
require_once '../../config/database.php';
include '../includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Manage Teachers</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                <i class="fas fa-plus"></i> Add New Teacher
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="teachersTable">
                    <thead>
                        <tr>
                            <th>Teacher ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone Number</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM teachers ORDER BY id DESC";
                        $result = mysqli_query($conn, $query);
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr class='clickable-row' onclick='viewTeacher(\"{$row['teacher_id']}\")' style='cursor: pointer;'>
                                    <td>{$row['teacher_id']}</td>
                                    <td>{$row['first_name']} {$row['last_name']}</td>
                                    <td>{$row['email']}</td>
                                    <td>{$row['phone_number']}</td>
                                    <td>{$row['department']}</td>
                                    <td>
                                        <select class='form-select status-select' 
                                                onchange='updateStatus({$row['id']}, this.value)' 
                                                style='background-color: " . ($row['status'] == 'Active' ? '#198754' : ($row['status'] == 'Inactive' ? '#dc3545' : '#ffc107')) . "; 
                                                       color: white; 
                                                       cursor: pointer;
                                                       padding: 5px;
                                                       border-radius: 5px;'>
                                            <option value='Active' " . ($row['status'] == 'Active' ? 'selected' : '') . ">Active</option>
                                            <option value='Inactive' " . ($row['status'] == 'Inactive' ? 'selected' : '') . ">Inactive</option>
                                            <option value='On Leave' " . ($row['status'] == 'On Leave' ? 'selected' : '') . ">On Leave</option>
                                        </select>
                                    </td>
                                    <td>
                                        <button class='btn btn-warning btn-sm edit-btn' data-id='" . $row['id'] . "'><i class='fas fa-edit'></i></button>
                                        <button class='btn btn-danger btn-sm' onclick='deleteTeacher(" . $row['id'] . ")'><i class='fas fa-trash'></i></button>
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

<!-- View Teacher Modal -->
<div class="modal fade" id="viewTeacherModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Teacher Information</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="teacher-info">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-circle fa-4x text-primary"></i>
                        <h4 class="mt-2" id="view_teacher_name"></h4>
                        <p class="text-muted" id="view_teacher_id_display"></p>
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
                                <label class="text-muted">Department</label>
                                <p id="view_department"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group">
                                <label class="text-muted">Status</label>
                                <p id="view_status"></p>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="info-group">
                                <label class="text-muted">Assigned Subjects</label>
                                <div id="view_subjects" class="mt-2">
                                    <!-- Subjects will be populated here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" onclick="editTeacherFromView()">
                    <i class="fas fa-edit"></i> Edit
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addTeacherForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Teacher ID</label>
                                <input type="text" class="form-control" name="teacher_id" required>
                            </div>
                            <div class="mb-3">
                                <label>First Name</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="mb-3">
                                <label>Middle Name</label>
                                <input type="text" class="form-control" name="middle_name">
                            </div>
                            <div class="mb-3">
                                <label>Last Name</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Gender</label>
                                <select class="form-control" name="gender" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth" required>
                            </div>
                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label>Phone Number</label>
                                <input type="text" class="form-control" name="phone_number">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Address</label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Department</label>
                                <input type="text" class="form-control" name="department" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Designation</label>
                                <select class="form-control" name="designation" required>
                                    <option value="Full-time">Full-time</option>
                                    <option value="Part-time">Part-time</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Teacher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Teacher Modal -->
<div class="modal fade" id="editTeacherModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editTeacherForm" onsubmit="return updateTeacher(event)">
                <input type="hidden" name="teacher_id" id="edit_teacher_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>First Name</label>
                        <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                    </div>
                    <div class="mb-3">
                        <label>Last Name</label>
                        <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label>Phone Number</label>
                        <input type="tel" class="form-control" name="phone_number" id="edit_phone_number" 
                               required pattern="[0-9]{11}" placeholder="09XXXXXXXXX">
                    </div>
                    <div class="mb-3">
                        <label>Department</label>
                        <input type="text" class="form-control" name="department" id="edit_department" required>
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

.teacher-info .info-group {
    margin-bottom: 1.5rem;
}

.teacher-info .info-group label {
    display: block;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.teacher-info .info-group p {
    font-size: 1rem;
    margin: 0;
    color: #333;
}

.subject-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    margin: 0.25rem;
    background-color: #e9ecef;
    border-radius: 20px;
    font-size: 0.875rem;
}

.status-select option {
    background-color: white;
    color: black;
}

.status-select {
    border: none;
    width: 100px;
}
</style>

<script>
// Add Teacher
document.getElementById('addTeacherForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    let formData = new FormData(this);
    
    fetch('add_teacher.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Teacher added successfully!');
            location.reload();
        } else {
            alert(data.message || 'Error adding teacher');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adding teacher');
    });
});

// View Teacher
function viewTeacher(teacherId) {
    fetch(`get_teacher.php?teacher_id=${teacherId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const teacher = data.teacher;
                
                // Populate the view modal
                document.getElementById('view_teacher_name').textContent = 
                    `${teacher.first_name} ${teacher.last_name}`;
                document.getElementById('view_teacher_id_display').textContent = 
                    `Teacher ID: ${teacher.teacher_id}`;
                document.getElementById('view_email').textContent = teacher.email;
                document.getElementById('view_phone_number').textContent = teacher.phone_number;
                document.getElementById('view_department').textContent = teacher.department;
                document.getElementById('view_status').innerHTML = 
                    `<span class="badge ${teacher.status === 'Active' ? 'bg-success' : 'bg-danger'}">${teacher.status}</span>`;

                // Fetch and display assigned subjects
                fetchTeacherSubjects(teacher.teacher_id);

                // Show the modal
                $('#viewTeacherModal').modal('show');
            }
        });
}

// Fetch Teacher's Subjects
function fetchTeacherSubjects(teacherId) {
    fetch(`get_teacher_subjects.php?teacher_id=${teacherId}`)
        .then(response => response.json())
        .then(data => {
            const subjectsContainer = document.getElementById('view_subjects');
            subjectsContainer.innerHTML = '';

            if (data.subjects && data.subjects.length > 0) {
                data.subjects.forEach(subject => {
                    const badge = document.createElement('div');
                    badge.className = 'subject-badge';
                    badge.textContent = `${subject.subject_code} - ${subject.subject_name}`;
                    subjectsContainer.appendChild(badge);
                });
            } else {
                subjectsContainer.innerHTML = '<p class="text-muted">No subjects assigned</p>';
            }
        });
}

// Edit from View Modal
function editTeacherFromView() {
    const teacherId = document.getElementById('view_teacher_id_display').textContent.split(': ')[1];
    $('#viewTeacherModal').modal('hide');
    editTeacher(teacherId);
}

// Edit Teacher
document.addEventListener('DOMContentLoaded', function() {
    // Add click event listeners to all edit buttons
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent row click event
            const teacherId = this.getAttribute('data-id');
            fetch(`get_teacher.php?teacher_id=${teacherId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const teacher = data.teacher;
                        document.getElementById('edit_teacher_id').value = teacher.teacher_id;
                        document.getElementById('edit_first_name').value = teacher.first_name;
                        document.getElementById('edit_last_name').value = teacher.last_name;
                        document.getElementById('edit_email').value = teacher.email;
                        document.getElementById('edit_phone_number').value = teacher.phone_number;
                        document.getElementById('edit_department').value = teacher.department;
                        const editModal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
                        editModal.show();
                    } else {
                        alert('Error loading teacher data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading teacher data');
                });
        });
    });
});

// Update Teacher
function updateTeacher(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    fetch('update_teacher.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Teacher updated successfully!');
            location.reload();
        } else {
            alert(data.message || 'Error updating teacher');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the teacher');
    });

    return false;
}

// Delete Teacher
function deleteTeacher(id) {
    // Create and show confirmation modal
    const modalHtml = `
        <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Confirm Deletion</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this teacher?</p>
                        <p class="text-danger"><strong>Warning:</strong> This will permanently remove the teacher and all their subject assignments.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="confirmDelete(${id})">Delete Teacher</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('deleteConfirmModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to document
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

// Confirm Delete Action
function confirmDelete(id) {
    // Hide the modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
    modal.hide();
    
    fetch('delete_teacher.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ teacher_id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the teacher');
    });
}

function updateStatus(id, newStatus) {
    fetch('update_status.php', {
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
            // Update the select element's color based on the new status
            const selectElement = event.target;
            if (newStatus === 'Active') {
                selectElement.style.backgroundColor = '#198754';
            } else if (newStatus === 'Inactive') {
                selectElement.style.backgroundColor = '#dc3545';
            } else {
                selectElement.style.backgroundColor = '#ffc107';
            }
        } else {
            alert(data.message || 'Error updating status');
            location.reload(); // Reload if update fails
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating status');
        location.reload();
    });
}

// Add event listeners for delete buttons
document.addEventListener('DOMContentLoaded', function() {
    // Add click event listeners to all delete buttons
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const teacherId = this.getAttribute('data-id');
            deleteTeacher(teacherId);
        });
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?>