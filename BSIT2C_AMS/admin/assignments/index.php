<?php
require_once '../../config/database.php';
include '../includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Manage Assignments</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                <i class="fas fa-plus"></i> Add New Assignment
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="assignmentsTable">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Subject</th>
                            <th>Schedule</th>
                            <th>Time</th>
                            <th>Location</th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT a.*, 
                                 CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                                 s.subject_code, s.subject_name
                                 FROM assignments a
                                 JOIN teachers t ON a.teacher_id = t.id
                                 JOIN subjects s ON a.subject_id = s.id
                                 ORDER BY a.created_at DESC";
                        $result = mysqli_query($conn, $query);
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>
                                    <td>{$row['teacher_name']}</td>
                                    <td>{$row['subject_code']} - {$row['subject_name']}</td>
                                    <td>{$row['preferred_day']}</td>
                                    <td>" . date('h:i A', strtotime($row['time_start'])) . " - " . 
                                          date('h:i A', strtotime($row['time_end'])) . "</td>
                                    <td>{$row['location']}</td>
                                    <td>" . date('M d, Y', strtotime($row['month_from'])) . " - " .
                                          date('M d, Y', strtotime($row['month_to'])) . "</td>
                                    <td>
                                        <button class='btn btn-warning btn-sm' onclick='editAssignment({$row['id']})'>
                                            <i class='fas fa-edit'></i>
                                        </button>
                                        <button class='btn btn-danger btn-sm' onclick='deleteAssignment({$row['id']})'>
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

<!-- Update the Add Assignment Modal with a better teacher selection -->
<div class="modal fade" id="addAssignmentModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAssignmentForm">
                <div class="modal-body">
                    <!-- Teacher Selection Section -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select Teacher</label>
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Select</th>
                                        <th>Teacher ID</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Current Load</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $teacher_query = "SELECT t.*, 
                                        (SELECT COUNT(*) FROM assignments a WHERE a.teacher_id = t.id) as current_load
                                        FROM teachers t 
                                        WHERE t.status = 'Active'
                                        ORDER BY t.last_name, t.first_name";
                                    $teacher_result = mysqli_query($conn, $teacher_query);
                                    while ($teacher = mysqli_fetch_assoc($teacher_result)) {
                                        echo "<tr>
                                                <td>
                                                    <input type='radio' name='teacher_id' value='{$teacher['id']}' 
                                                           class='form-check-input' required>
                                                </td>
                                                <td>{$teacher['teacher_id']}</td>
                                                <td>{$teacher['first_name']} {$teacher['last_name']}</td>
                                                <td>{$teacher['department']}</td>
                                                <td>{$teacher['current_load']} subjects</td>
                                            </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Subject Selection Section -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select Subject</label>
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Select</th>
                                        <th>Subject Code</th>
                                        <th>Subject Name</th>
                                        <th>Units</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $subject_query = "SELECT s.*,
                                        CASE 
                                            WHEN a.subject_id IS NOT NULL THEN 'Assigned'
                                            ELSE 'Available'
                                        END as assignment_status
                                        FROM subjects s
                                        LEFT JOIN assignments a ON s.id = a.subject_id
                                        ORDER BY s.subject_code";
                                    $subject_result = mysqli_query($conn, $subject_query);
                                    while ($subject = mysqli_fetch_assoc($subject_result)) {
                                        $disabled = $subject['assignment_status'] == 'Assigned' ? 'disabled' : '';
                                        $status_class = $subject['assignment_status'] == 'Assigned' ? 'text-danger' : 'text-success';
                                        echo "<tr>
                                                <td>
                                                    <input type='radio' name='subject_id' value='{$subject['id']}' 
                                                           class='form-check-input' required {$disabled}>
                                                </td>
                                                <td>{$subject['subject_code']}</td>
                                                <td>{$subject['subject_name']}</td>
                                                <td>{$subject['units']}</td>
                                                <td class='{$status_class}'>{$subject['assignment_status']}</td>
                                            </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Schedule Details Section -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Schedule Details</label>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Month From</label>
                                <input type="date" class="form-control" name="month_from" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Month To</label>
                                <input type="date" class="form-control" name="month_to" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Preferred Day</label>
                                <select class="form-select" name="preferred_day" required>
                                    <option value="">Select Day</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Time Start</label>
                                <input type="time" class="form-control" name="time_start" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Time End</label>
                                <input type="time" class="form-control" name="time_end" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" name="location" 
                                       placeholder="Enter classroom or location" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add this CSS -->
<style>
.table-responsive {
    max-height: 300px;
    overflow-y: auto;
}

.form-check-input[type="radio"] {
    cursor: pointer;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

.table td, .table th {
    vertical-align: middle;
}

.disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

<!-- Add this JavaScript -->
<script>
// Highlight selected row
document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        // Remove highlight from all rows in the same table
        this.closest('table').querySelectorAll('tr').forEach(tr => {
            tr.classList.remove('table-active');
        });
        // Add highlight to selected row
        if (this.checked) {
            this.closest('tr').classList.add('table-active');
        }
    });
});

// Form validation and submission
document.getElementById('addAssignmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Additional validation
    const timeStart = this.elements['time_start'].value;
    const timeEnd = this.elements['time_end'].value;
    const monthFrom = new Date(this.elements['month_from'].value);
    const monthTo = new Date(this.elements['month_to'].value);

    if (monthTo < monthFrom) {
        alert('End date must be after start date');
        return;
    }

    if (timeEnd <= timeStart) {
        alert('End time must be after start time');
        return;
    }

    let formData = new FormData(this);
    
    fetch('../assignments/add_assignment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Assignment added successfully!');
            location.reload();
        } else {
            alert(data.message || 'Error adding assignment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adding assignment');
    });
});

// Delete Assignment
function deleteAssignment(id) {
    if (confirm('Are you sure you want to delete this assignment?')) {
        fetch('../assignments/delete_assignment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Assignment deleted successfully!');
                location.reload();
            } else {
                alert(data.message || 'Error deleting assignment');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting assignment');
        });
    }
}
</script>

<?php include '../includes/admin_footer.php'; ?> 