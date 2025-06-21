<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
include '../includes/header.php';

$teacher_id = $_SESSION['teacher_id']; // Assuming you have teacher_id in session

function getDayName($dayNumber) {
    $days = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday'
    ];
    
    return $days[$dayNumber] ?? 'Unknown';
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Attendance Management</h1>

    <!-- Schedule Selection -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Select Schedule</h6>
        </div>
        <div class="card-body">
            <div class="form-group">
                <select class="form-control" id="scheduleSelect" onchange="loadStudents()">
                    <option value="">Select Schedule</option>
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
                        echo "<option value='{$row['schedule_id']}'>{$row['subject_name']} - " .
                             getDayName($row['day']) . " " . 
                             date('h:i A', strtotime($row['start_time'])) . "-" . 
                             date('h:i A', strtotime($row['end_time'])) . "</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Attendance Form -->
    <div class="card shadow mb-4" id="attendanceCard" style="display: none;">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Mark Attendance</h6>
            <input type="date" id="attendanceDate" class="form-control" style="width: auto;" 
                   value="<?php echo date('Y-m-d'); ?>" onchange="loadAttendance()">
        </div>
        <div class="card-body">
            <form id="attendanceForm">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="studentList">
                            <!-- Students will be loaded here -->
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-primary">Save Attendance</button>
            </form>
        </div>
    </div>
</div>

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
                        <td>${student.first_name} ${student.last_name}</td>
                        <td>
                            <select name="status[${student.enrollment_id}]" class="form-control">
                                <option value="present" ${student.status === 'present' ? 'selected' : ''}>Present</option>
                                <option value="absent" ${student.status === 'absent' ? 'selected' : ''}>Absent</option>
                                <option value="late" ${student.status === 'late' ? 'selected' : ''}>Late</option>
                                <option value="excused" ${student.status === 'excused' ? 'selected' : ''}>Excused</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="remarks[${student.enrollment_id}]" 
                                   class="form-control" value="${student.remarks || ''}">
                        </td>
                    </tr>
                `;
            });
        });
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
            alert('Attendance saved successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    });
});

function loadAttendance() {
    loadStudents();
}
</script>

<?php include '../includes/footer.php'; ?> 