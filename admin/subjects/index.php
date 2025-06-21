<?php
require_once '../../config/database.php';
include '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-10">
            <!-- Page Header -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <h2 class="fw-bold text-primary mb-2">
                            <i class="fas fa-book me-2"></i> Subject Management
                        </h2>
                        <p class="text-muted mb-0">Create, edit and manage course subjects</p>
                    </div>
                </div>
            </div>
            
            <!-- Stats Row -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle text-white bg-primary me-3">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Total Subjects</div>
                                    <?php 
                                    $count_query = "SELECT COUNT(*) as total FROM subjects";
                                    $count_result = mysqli_query($conn, $count_query);
                                    $count_data = mysqli_fetch_assoc($count_result);
                                    echo "<h4 class='mb-0'>{$count_data['total']}</h4>";
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle text-white bg-success me-3">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Average Units</div>
                                    <?php 
                                    // Check if units column exists in subjects table
                                    $check_units_column = mysqli_query($conn, "SHOW COLUMNS FROM subjects LIKE 'units'");
                                    if (mysqli_num_rows($check_units_column) > 0) {
                                        $avg_query = "SELECT AVG(units) as avg_units FROM subjects";
                                        $avg_result = mysqli_query($conn, $avg_query);
                                        $avg_data = mysqli_fetch_assoc($avg_result);
                                        echo "<h4 class='mb-0'>" . number_format($avg_data['avg_units'], 1) . "</h4>";
                                    } else {
                                        echo "<h4 class='mb-0'>N/A</h4>";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle text-white bg-info me-3">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">School Year</div>
                                    <h4 class="mb-0">2025-2026</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Subjects Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-gradient-primary-to-secondary p-4 text-white d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-list me-2"></i> Subject List
                    </h5>
                    <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                        <i class="fas fa-plus me-2"></i> Add New Subject
                    </button>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="searchSubject" placeholder="Search by code, name, or description...">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="subjectsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 15%">Subject Code</th>
                                    <th style="width: 25%">Subject Name</th>
                                    <th style="width: 35%">Description</th>
                                    <?php
                                    // Check if units column exists
                                    $check_units = mysqli_query($conn, "SHOW COLUMNS FROM subjects LIKE 'units'");
                                    $has_units = mysqli_num_rows($check_units) > 0;
                                    if ($has_units) {
                                        echo '<th style="width: 10%" class="text-center">Units</th>';
                                    }
                                    ?>
                                    <th style="width: 15%" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT * FROM subjects ORDER BY id DESC";
                                $result = mysqli_query($conn, $query);
                                if (mysqli_num_rows($result) > 0) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        echo "<tr style='cursor: pointer;' onclick='viewSubject({$row['id']})' class='subject-row'>
                                                <td><span class='badge bg-light text-primary border'>{$row['subject_code']}</span></td>
                                                <td class='fw-bold'>{$row['subject_name']}</td>
                                                <td class='text-muted'>" . (empty($row['description']) ? "--" : $row['description']) . "</td>";
                                        
                                        if ($has_units) {
                                            echo "<td class='text-center'>{$row['units']}</td>";
                                        }
                                        
                                        echo "<td class='text-center'>
                                                    <button class='btn btn-sm btn-outline-primary me-1' onclick='editSubject({$row['id']}); event.stopPropagation();'>
                                                        <i class='fas fa-edit'></i>
                                                    </button>
                                                    <button class='btn btn-sm btn-outline-danger' onclick='deleteSubject({$row['id']}); event.stopPropagation();'>
                                                        <i class='fas fa-trash'></i>
                                                    </button>
                                                </td>
                                            </tr>";
                                    }
                                } else {
                                    $colspan = $has_units ? 5 : 4;
                                    echo "<tr><td colspan='{$colspan}' class='text-center py-4'>No subjects found. Click the 'Add New Subject' button to create one.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <nav>
                        <ul class="pagination justify-content-center m-0">
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1">Previous</a>
                            </li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-gradient-primary-to-secondary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>Add New Subject
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSubjectForm">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Subject Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject_code" required placeholder="e.g. CS101">
                        <div class="form-text">Enter a unique code for this subject</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject_name" required placeholder="e.g. Introduction to Programming">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Enter subject description..."></textarea>
                    </div>
                    <?php if ($has_units): ?>
                    <div class="mb-3">
                        <label class="form-label">Units <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="units" required min="1" max="5" value="3">
                        <div class="form-text">Number of academic units (1-5)</div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-gradient-primary-to-secondary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Edit Subject
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editSubjectForm">
                <div class="modal-body p-4">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="mb-3">
                        <label class="form-label">Subject Code</label>
                        <input type="text" class="form-control" id="edit_subject_code" name="subject_code">
                        <div class="form-text">Enter a unique code for this subject</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject Name</label>
                        <input type="text" class="form-control" id="edit_subject_name" name="subject_name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <?php if ($has_units): ?>
                    <div class="mb-3">
                        <label class="form-label">Units</label>
                        <input type="number" class="form-control" id="edit_units" name="units" min="1" max="5">
                        <div class="form-text">Number of academic units (1-5)</div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Subject Modal -->
<div class="modal fade" id="viewSubjectModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-gradient-primary-to-secondary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-book me-2"></i>Subject Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <span id="view_subject_code" class="badge bg-primary px-3 py-2 rounded-pill"></span>
                    </div>
                    <h4 id="view_subject_name" class="fw-bold mb-2"></h4>
                    <p id="view_description" class="text-muted mb-0"></p>
                </div>
                <div class="row g-3">
                    <?php if ($has_units): ?>
                    <div class="col-md-6">
                        <div class="card bg-light border-0">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-circle bg-primary text-white me-3">
                                        <i class="fas fa-graduation-cap"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small">Units</div>
                                        <div id="view_units" class="fw-bold"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6">
                        <div class="card bg-light border-0">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-circle bg-primary text-white me-3">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small">Students Enrolled</div>
                                        <div id="view_enrolled" class="fw-bold">--</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
                <button type="button" class="btn btn-primary" onclick="editSubject(currentSubjectId)">
                    <i class="fas fa-edit me-2"></i>Edit Subject
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Table styling for proper alignment */
    #subjectsTable {
        table-layout: fixed;
        width: 100%;
    }
    
    #subjectsTable th, 
    #subjectsTable td {
        vertical-align: middle;
    }
    
    #subjectsTable th.text-center, 
    #subjectsTable td.text-center {
        text-align: center !important;
    }
    
    /* Fix for potential browser inconsistencies */
    .table-responsive {
        overflow-x: auto;
    }
    
    /* Make units column values more prominent */
    #subjectsTable td.text-center {
        font-weight: 500;
    }
    
    .subject-row:hover {
        background-color: rgba(2, 31, 63, 0.05);
        transition: background-color 0.2s ease;
    }
    .icon-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }
</style>

<script>
let currentSubjectId = null;

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
    // Show loading state
    const editModal = new bootstrap.Modal(document.getElementById('editSubjectModal'));
    editModal.show();
    
    fetch(`get_subject.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const subject = data.subject;
                // Fill in the form fields
                document.getElementById('edit_id').value = subject.id;
                document.getElementById('edit_subject_code').value = subject.subject_code || '';
                document.getElementById('edit_subject_name').value = subject.subject_name || '';
                document.getElementById('edit_description').value = subject.description || '';
                
                // Only set units if the field exists
                const unitsField = document.getElementById('edit_units');
                if (unitsField && subject.units) {
                    unitsField.value = subject.units;
                }
            } else {
                showToast(data.message || 'Error loading subject data', 'danger');
                editModal.hide();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading subject data', 'danger');
            editModal.hide();
        });
}

// Update Subject
document.getElementById('editSubjectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // Remove empty fields from FormData
    for (let pair of formData.entries()) {
        if (!pair[1].trim()) {
            formData.delete(pair[0]);
        }
    }
    
    fetch('update_subject.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Subject updated successfully!', 'success');
            location.reload();
        } else {
            showToast(data.message || 'Error updating subject', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error updating subject', 'danger');
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

// Search functionality for subjects table
document.getElementById('searchSubject').addEventListener('keyup', function() {
    const value = this.value.toLowerCase();
    const table = document.getElementById('subjectsTable');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        let found = false;
        const cells = rows[i].getElementsByTagName('td');
        
        // Skip if this is a "no subjects found" row
        if (cells.length <= 1) continue;
        
        for (let j = 0; j < cells.length; j++) {
            // Get the text content - for cells with badges or other elements, ensure we get all text
            let cellText;
            
            // Special handling for subject code cell which has a badge
            if (j === 0) { // First column is subject code with badge
                const badge = cells[j].querySelector('.badge');
                cellText = badge ? badge.textContent.toLowerCase() : '';
            } else {
                cellText = cells[j].textContent.toLowerCase();
            }
            
            if (cellText.indexOf(value) > -1) {
                found = true;
                break;
            }
        }
        
        rows[i].style.display = found ? '' : 'none';
    }
    
    // Show a message if no results are found
    const visibleRows = table.querySelectorAll('tbody tr:not([style*="display: none"])').length;
    let noResultsRow = table.querySelector('.no-results-row');
    
    if (visibleRows === 0 && value !== '') {
        if (!noResultsRow) {
            const tbody = table.querySelector('tbody');
            const colspan = <?php echo $has_units ? '5' : '4'; ?>;
            
            noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row';
            noResultsRow.innerHTML = `
                <td colspan="${colspan}" class="text-center py-4">
                    <div class="text-muted">
                        <i class="fas fa-search fa-2x mb-3"></i>
                        <p>No subjects matching "${value}" found.</p>
                    </div>
                </td>
            `;
            tbody.appendChild(noResultsRow);
        } else {
            noResultsRow.querySelector('p').textContent = `No subjects matching "${value}" found.`;
        }
    } else if (noResultsRow) {
        noResultsRow.remove();
    }
});

// Replace alert with nicer notifications
function showToast(message, type = 'success') {
    // Create toast container if it doesn't exist
    if (!document.getElementById('toastContainer')) {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
    }
    
    // Create toast element
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    toast.id = toastId;
    
    // Toast content
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    // Add toast to container
    document.getElementById('toastContainer').appendChild(toast);
    
    // Initialize and show the toast
    const toastInstance = new bootstrap.Toast(toast);
    toastInstance.show();
    
    // Remove toast after it's hidden
    toast.addEventListener('hidden.bs.toast', function() {
        document.getElementById(toastId).remove();
    });
}

// Override the original alert functions
window.originalAlert = window.alert;
window.alert = function(message) {
    if (message.includes('success')) {
        showToast(message, 'success');
    } else {
        showToast(message, 'danger');
    }
};

function viewSubject(id) {
    currentSubjectId = id;
    fetch(`get_subject.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const subject = data.subject;
                document.getElementById('view_subject_code').textContent = subject.subject_code;
                document.getElementById('view_subject_name').textContent = subject.subject_name;
                document.getElementById('view_description').textContent = subject.description || 'No description available';
                
                const unitsElement = document.getElementById('view_units');
                if (unitsElement) {
                    unitsElement.textContent = subject.units || '--';
                }
                
                // Update enrolled students count
                document.getElementById('view_enrolled').textContent = subject.enrolled_count || '0';
                
                const viewModal = new bootstrap.Modal(document.getElementById('viewSubjectModal'));
                viewModal.show();
            } else {
                showToast('Error loading subject details', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading subject details', 'danger');
        });
}
</script>

<?php include '../includes/admin_footer.php'; ?> 