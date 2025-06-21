<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    // Check user credentials and role
    $sql = "SELECT u.id, u.password, u.role FROM users u WHERE u.username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        if ($password == $user['password']) {
            // Verify user is a student
            if ($user['role'] != 'student') {
                $_SESSION['error'] = "Please use the appropriate login portal for your role.";
                if ($user['role'] == 'admin') {
                    header("Location: ../admin/");
                } else {
                    header("Location: ../teacher/");
                }
                exit();
            }

            // Get student details
            $sql = "SELECT id FROM students WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $student_result = $stmt->get_result();

            if ($student_result->num_rows == 1) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['student_id'] = $student_result->fetch_assoc()['id'];
                
                // Update last login
                $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();

                header("Location: dashboard.php");
                exit();
            }
        }
    }

    $_SESSION['error'] = "Invalid username or password";
    header("Location: index.php");
    exit();
}
?> 

/* Add to your existing styles */
.assignments-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.assignments-table th,
.assignments-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.assignments-table th {
    background-color: #f5f5f5;
    font-weight: bold;
}

.assignments-table tr:hover {
    background-color: #f9f9f9;
}

.assignments-table td:last-child {
    color: #666;
    font-size: 0.9em;
}

<!-- Assign Teacher Modal -->
<div id="assignTeacherModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('assignTeacherModal')">&times;</span>
        <h2>Assign Teacher to Subject</h2>
        <div id="assignFormMessage"></div>
        <form id="assignTeacherForm" onsubmit="return handleAssignTeacher(event)">
            <div class="form-group">
                <label>Select Teacher: *</label>
                <select name="teacher_id" id="teacher_id" required onchange="checkWeeklyClasses()">
                    <option value="">Select Teacher</option>
                    <?php
                    $teachers = $conn->query("SELECT id, first_name, last_name FROM teachers WHERE status = 'Active'");
                    while ($teacher = $teachers->fetch_assoc()):
                    ?>
                    <option value="<?php echo $teacher['id']; ?>">
                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Select Subject: *</label>
                <select name="subject_id" id="subject_id" required onchange="checkWeeklyClasses()">
                    <option value="">Select Subject</option>
                    <?php
                    $subjects = $conn->query("SELECT id, subject_name, subject_code FROM subjects WHERE teacher_id IS NULL AND status = 'Active'");
                    while ($subject = $subjects->fetch_assoc()):
                    ?>
                    <option value="<?php echo $subject['id']; ?>">
                        <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Weekly Classes: *</label>
                <div class="weekly-classes">
                    <input type="number" name="weekly_classes" id="weekly_classes" min="1" max="3" value="1" required>
                    <span id="remaining_classes">Available slots: 3 of 3</span>
                    <div class="class-indicators">
                        <div class="class-indicator available"></div>
                        <div class="class-indicator available"></div>
                        <div class="class-indicator available"></div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn">Assign Teacher</button>
        </form>
    </div>
</div>

<style>
.weekly-classes {
    display: flex;
    align-items: center;
    gap: 10px;
}

.class-indicators {
    display: flex;
    gap: 5px;
}

.class-indicator {
    width: 15px;
    height: 15px;
    border-radius: 50%;
}

.class-indicator.available {
    background-color: #4CAF50;
}

.class-indicator.unavailable {
    background-color: #ddd;
}
</style>

<script>
async function handleAssignTeacher(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const messageDiv = document.getElementById('assignFormMessage');

    try {
        const response = await fetch('process_assign_teacher.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        
        if (result.success) {
            messageDiv.innerHTML = `
                <div class="alert alert-success">
                    ${result.message}
                </div>
            `;
            form.reset();
            await refreshDashboardData();
            setTimeout(() => {
                hideModal('assignTeacherModal');
                messageDiv.innerHTML = '';
            }, 2000);
        } else {
            messageDiv.innerHTML = `
                <div class="alert alert-danger">
                    ${result.message}
                </div>
            `;
        }
    } catch (error) {
        console.error('Error:', error);
        messageDiv.innerHTML = `
            <div class="alert alert-danger">
                An error occurred. Please try again.
            </div>
        `;
    }
    return false;
}

function checkWeeklyClasses() {
    const teacherId = document.getElementById('teacher_id').value;
    const subjectId = document.getElementById('subject_id').value;
    
    if (teacherId && subjectId) {
        fetch(`get_weekly_classes.php?teacher_id=${teacherId}&subject_id=${subjectId}`)
            .then(response => response.json())
            .then(data => {
                const remaining = 3 - data.current_classes;
                document.getElementById('weekly_classes').max = remaining;
                document.getElementById('remaining_classes').textContent = 
                    `Available slots: ${remaining} of 3`;
                
                // Update indicators
                const indicators = document.querySelectorAll('.class-indicator');
                indicators.forEach((indicator, index) => {
                    indicator.className = `class-indicator ${index < remaining ? 'available' : 'unavailable'}`;
                });
            });
    }
}
</script>

<link rel="stylesheet" href="../assets/css/modal.css">
<script src="../assets/js/modal.js"></script>