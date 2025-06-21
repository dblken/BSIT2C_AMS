<?php
require_once '../../config/database.php';
include '../includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Manage Subjects</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                <i class="fas fa-plus"></i> Add New Subject
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Description</th>
                            <th>Units</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM subjects ORDER BY subject_code";
                        $result = mysqli_query($conn, $query);
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>
                                    <td>{$row['subject_code']}</td>
                                    <td>{$row['subject_name']}</td>
                                    <td>{$row['description']}</td>
                                    <td>{$row['units']}</td>
                                    <td>
                                        <button class='btn btn-warning btn-sm' onclick='editSubject({$row['id']})'>
                                            <i class='fas fa-edit'></i>
                                        </button>
                                        <button class='btn btn-danger btn-sm' onclick='deleteSubject({$row['id']})'>
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

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSubjectForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Subject Code</label>
                        <input type="text" class="form-control" name="subject_code" required>
                    </div>
                    <div class="mb-3">
                        <label>Subject Name</label>
                        <input type="text" class="form-control" name="subject_name" required>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Units</label>
                        <input type="number" class="form-control" name="units" required min="1" max="5">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editSubjectForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="mb-3">
                        <label>Subject Code</label>
                        <input type="text" class="form-control" id="edit_subject_code" name="subject_code" required>
                    </div>
                    <div class="mb-3">
                        <label>Subject Name</label>
                        <input type="text" class="form-control" id="edit_subject_name" name="subject_name" required>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Units</label>
                        <input type="number" class="form-control" id="edit_units" name="units" required min="1" max="5">
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

<script>
// Add Subject
document.getElementById('addSubjectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    fetch('add_subject.php', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Subject added successfully!');
            location.reload();
        } else {
            alert(data.message || 'Error adding subject');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adding subject');
    });
});

// Edit Subject
function editSubject(id) {
    fetch(`get_subject.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_id').value = data.subject.id;
                document.getElementById('edit_subject_code').value = data.subject.subject_code;
                document.getElementById('edit_subject_name').value = data.subject.subject_name;
                document.getElementById('edit_description').value = data.subject.description;
                document.getElementById('edit_units').value = data.subject.units;
                
                new bootstrap.Modal(document.getElementById('editSubjectModal')).show();
            }
        });
}

// Update Subject
document.getElementById('editSubjectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    fetch('update_subject.php', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Subject updated successfully!');
            location.reload();
        } else {
            alert(data.message || 'Error updating subject');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating subject');
    });
});

// Delete Subject
function deleteSubject(id) {
    if (confirm('Are you sure you want to delete this subject?')) {
        fetch('delete_subject.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Subject deleted successfully!');
                location.reload();
            } else {
                alert(data.message || 'Error deleting subject');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting subject');
        });
    }
}
</script>

<?php include '../includes/admin_footer.php'; ?> 