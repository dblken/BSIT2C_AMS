<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
include '../includes/header.php';

$teacher_id = $_SESSION['teacher_id']; // Assuming you have teacher_id in session
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-dark">
            <i class="bi bi-calendar2-check me-2"></i>Attendance Management
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Attendance</li>
            </ol>
        </nav>
    </div>

    <!-- Schedule Selection -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius: 10px;">
        <div class="card-header py-3" style="background-color: #021f3f; border-radius: 10px 10px 0 0;">
            <h6 class="m-0 font-weight-bold text-white">
                <i class="bi bi-calendar-week me-2"></i>Select Schedule
            </h6>
        </div>
        <div class="card-body p-4">
            <div class="form-group">
                <select class="form-select form-select-lg" id="scheduleSelect" onchange="loadStudents()" style="border: 2px solid #e9ecef; border-radius: 8px; padding: 12px;">
                    <option value="">Select a class schedule...</option>
                    <?php
                    $query = "SELECT sch.*, sub.subject_name 
                             FROM schedules sch
                             JOIN subjects sub ON sch.subject_id = sub.subject_id
                             WHERE sch.teacher_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $teacher_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    while ($row = $result->fetch_assoc()) {
                        echo "<option value='{$row['schedule_id']}'>";
                        echo "<strong>{$row['subject_name']}</strong> - " .
                             getDayName($row['day']) . " " . 
                             date('h:i A', strtotime($row['start_time'])) . " - " . 
                             date('h:i A', strtotime($row['end_time']));
                        echo "</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Attendance Form -->
    <div class="card shadow-sm border-0 mb-4" id="attendanceCard" style="display: none; border-radius: 10px;">
        <div class="card-header py-3 d-flex justify-content-between align-items-center" style="background-color: #021f3f; border-radius: 10px 10px 0 0;">
            <h6 class="m-0 font-weight-bold text-white">
                <i class="bi bi-pencil-square me-2"></i>Mark Attendance
            </h6>
            <input type="date" id="attendanceDate" class="form-control" 
                   style="width: auto; border: 2px solid #e9ecef; border-radius: 6px; padding: 8px;" 
                   value="<?php echo date('Y-m-d'); ?>" onchange="loadAttendance()">
        </div>
        <div class="card-body p-4">
            <form id="attendanceForm">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-bold" style="width: 35%;">Student Name</th>
                                <th class="fw-bold" style="width: 30%;">Status</th>
                                <th class="fw-bold" style="width: 35%;">Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="studentList">
                            <!-- Students will be loaded here -->
                        </tbody>
                    </table>
                </div>
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-5 py-2 d-inline-flex align-items-center gap-2" 
                            style="background-color: #021F3F; border: none; transition: all 0.3s ease;">
                        <i class="bi bi-save2-fill"></i>
                        <span>Save Attendance</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.form-select:focus {
    border-color: #021f3f;
    box-shadow: 0 0 0 0.25rem rgba(2, 31, 63, 0.25);
}

.form-control:focus {
    border-color: #021f3f;
    box-shadow: 0 0 0 0.25rem rgba(2, 31, 63, 0.25);
}

.btn-primary:hover {
    background-color: #053469 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(2, 31, 63, 0.15);
}

.status-select {
    font-weight: 500;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    padding: 8px;
    width: 100%;
    transition: all 0.2s ease;
}

.status-select.present {
    background-color: #d1fae5;
    color: #065f46;
    border-color: #065f46;
}

.status-select.absent {
    background-color: #fee2e2;
    color: #b91c1c;
    border-color: #b91c1c;
}

.status-select.late {
    background-color: #fef3c7;
    color: #92400e;
    border-color: #92400e;
}

.status-select.excused {
    background-color: #e0e7ff;
    color: #3730a3;
    border-color: #3730a3;
}

.remarks-input {
    border: 2px solid #e9ecef;
    border-radius: 6px;
    padding: 8px;
    width: 100%;
    transition: all 0.2s ease;
}

.remarks-input:focus {
    border-color: #021f3f;
    box-shadow: 0 0 0 0.25rem rgba(2, 31, 63, 0.25);
}

.breadcrumb-item a {
    color: #021f3f;
    text-decoration: none;
}

.breadcrumb-item.active {
    color: #6c757d;
}
</style>

<script>
function loadStudents() {
    const scheduleId = document.getElementById('scheduleSelect').value;
    const date = document.getElementById('attendanceDate').value;
    
    if (!scheduleId) {
        document.getElementById('attendanceCard').style.display = 'none';
        return;
    }

    document.getElementById('attendanceCard').style.display = 'block';
    
    fetch(`get_enrolled_students.php?schedule_id=${scheduleId}&date=${date}`)
        .then(response => response.json())
        .then(data => {
            const studentList = document.getElementById('studentList');
            studentList.innerHTML = '';
            
            data.forEach(student => {
                studentList.innerHTML += `
                    <tr>
                        <td class="align-middle">${student.first_name} ${student.last_name}</td>
                        <td>
                            <select name="status[${student.enrollment_id}]" 
                                    class="status-select form-select ${student.status || ''}"
                                    onchange="updateStatusStyle(this)">
                                <option value="">Select Status</option>
                                <option value="present" ${student.status === 'present' ? 'selected' : ''}>Present</option>
                                <option value="late" ${student.status === 'late' ? 'selected' : ''}>Late</option>
                                <option value="absent" ${student.status === 'absent' ? 'selected' : ''}>Absent</option>
                                <option value="excused" ${student.status === 'excused' ? 'selected' : ''}>Excused</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="remarks[${student.enrollment_id}]" 
                                   class="remarks-input" placeholder="Add remarks..."
                                   value="${student.remarks || ''}">
                        </td>
                    </tr>
                `;
            });
            
            // Initialize status styles
            document.querySelectorAll('.status-select').forEach(updateStatusStyle);
        });
}

function updateStatusStyle(select) {
    select.classList.remove('present', 'absent', 'late', 'excused');
    if (select.value) {
        select.classList.add(select.value);
    }
}

document.getElementById('attendanceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('date', document.getElementById('attendanceDate').value);
    formData.append('schedule_id', document.getElementById('scheduleSelect').value);
    
    fetch('save_attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success toast or modal
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Attendance has been saved successfully.',
                showConfirmButton: false,
                timer: 2000
            });
        } else {
            // Show error toast or modal
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to save attendance.',
                confirmButtonColor: '#021f3f'
            });
        }
    });
});

function loadAttendance() {
    loadStudents();
}
</script>

<!-- Add SweetAlert2 for better notifications -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php include '../includes/footer.php'; ?> 