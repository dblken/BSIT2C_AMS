<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$success_msg = $error_msg = '';

// Get all active teachers
$teachers_query = "SELECT t.id, CONCAT(t.first_name, ' ', t.last_name) as name 
                  FROM teachers t 
                  WHERE t.status = 'Active'";
$teachers = $conn->query($teachers_query);

// Get all active subjects
$subjects_query = "SELECT id, subject_name, subject_code 
                  FROM subjects 
                  WHERE status = 'Active'";
$subjects = $conn->query($subjects_query);

// Function to get current weekly classes count - optimized
function getWeeklyClassesCount($conn, $teacher_id, $subject_id) {
    $sql = "SELECT COUNT(*) as count 
            FROM timetable t
            JOIN subjects s ON t.subject_id = s.id
            WHERE s.teacher_id = ? AND t.subject_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $teacher_id, $subject_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['count'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $teacher_id = (int)$_POST['teacher_id'];
    $subject_id = (int)$_POST['subject_id'];
    $weekly_classes = (int)$_POST['weekly_classes'];

    try {
        // Set transaction isolation level for better performance
        $conn->query("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
        $conn->query("SET innodb_lock_wait_timeout=10");
        
        // Check total weekly classes for this teacher - optimized query
        $total_classes_sql = "SELECT SUM(t.weekly_classes) as total 
                            FROM timetable t
                            JOIN subjects s ON t.subject_id = s.id
                            WHERE s.teacher_id = ?";
        $stmt = $conn->prepare($total_classes_sql);
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $total_classes = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

        // Check if adding these classes would exceed the limit
        if (($total_classes + $weekly_classes) > 3) {
            throw new Exception("This teacher already has the maximum allowed classes per week.");
        }

        // Check specific subject-teacher combination
        $current_classes = getWeeklyClassesCount($conn, $teacher_id, $subject_id);
        if (($current_classes + $weekly_classes) > 3) {
            throw new Exception("This teacher already has 3 classes assigned for this subject this week.");
        }

        // Begin transaction with optimized settings
        $conn->begin_transaction();

        // Using batch operations to minimize round trips
        $sql = "UPDATE subjects SET teacher_id = ? WHERE id = ?;
                INSERT INTO timetable (subject_id, weekly_classes, day_of_week, start_time, end_time, room) 
                VALUES (?, ?, 'Monday', '08:00:00', '09:30:00', 'TBA')";
        
        $stmt1 = $conn->prepare("UPDATE subjects SET teacher_id = ? WHERE id = ?");
        $stmt1->bind_param("ii", $teacher_id, $subject_id);
        $stmt1->execute();
        
        $stmt2 = $conn->prepare("INSERT INTO timetable (subject_id, weekly_classes, day_of_week, start_time, end_time, room) 
                               VALUES (?, ?, 'Monday', '08:00:00', '09:30:00', 'TBA')");
        $stmt2->bind_param("ii", $subject_id, $weekly_classes);
        $stmt2->execute();

        $conn->commit();
        $success_msg = "Teacher assigned to subject successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Assign Teacher to Subject - BSIT 2C AMS</title>
    <style>
        /* Use same CSS as add_subject.php */
        .weekly-classes { display: flex; gap: 10px; align-items: center; }
        .class-indicator { width: 20px; height: 20px; border-radius: 50%; }
        .available { background: #4CAF50; }
        .unavailable { background: #ddd; }
    </style>
    <script>
        // Cache for responses to avoid unnecessary requests
        const responseCache = {};
        let debounceTimeout = null;

        function checkWeeklyClasses() {
            const teacherId = document.getElementById('teacher_id').value;
            const subjectId = document.getElementById('subject_id').value;
            
            // Clear any pending requests
            if (debounceTimeout) {
                clearTimeout(debounceTimeout);
            }
            
            if (teacherId && subjectId) {
                // Create a cache key
                const cacheKey = `${teacherId}-${subjectId}`;
                
                // Use cached response if available and not older than 30 seconds
                if (responseCache[cacheKey] && 
                    (Date.now() - responseCache[cacheKey].timestamp) < 30000) {
                    updateUI(responseCache[cacheKey].data);
                    return;
                }
                
                // Debounce the request to avoid multiple calls
                debounceTimeout = setTimeout(() => {
                    // Show loading state
                    document.getElementById('remaining_classes').textContent = 'Loading...';
                    
                    fetch(`get_weekly_classes.php?teacher_id=${teacherId}&subject_id=${subjectId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            // Cache the response
                            responseCache[cacheKey] = {
                                data: data,
                                timestamp: Date.now()
                            };
                            updateUI(data);
                        })
                        .catch(error => {
                            console.error('Error fetching data:', error);
                            document.getElementById('remaining_classes').textContent = 
                                'Error loading data. Please try again.';
                        });
                }, 300); // 300ms debounce delay
            }
        }
        
        function updateUI(data) {
            const remaining = 3 - data.current_classes;
            document.getElementById('weekly_classes').max = remaining;
            document.getElementById('remaining_classes').textContent = 
                `Available slots: ${remaining} of 3`;
            
            // Update visual indicators
            const indicators = document.querySelectorAll('.class-indicator');
            indicators.forEach((indicator, index) => {
                indicator.className = 'class-indicator ' + 
                    (index < remaining ? 'available' : 'unavailable');
            });
        }
    </script>
</head>
<body>
    <div class="container">
        <h2>Assign Teacher to Subject</h2>
        
        <?php if ($success_msg): ?>
            <div class="success"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        
        <?php if ($error_msg): ?>
            <div class="error"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Select Teacher:</label>
                <select name="teacher_id" id="teacher_id" required onchange="checkWeeklyClasses()">
                    <option value="">Select a teacher</option>
                    <?php while ($teacher = $teachers->fetch_assoc()): ?>
                        <option value="<?php echo $teacher['id']; ?>">
                            <?php echo htmlspecialchars($teacher['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Select Subject:</label>
                <select name="subject_id" id="subject_id" required onchange="checkWeeklyClasses()">
                    <option value="">Select a subject</option>
                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                        <option value="<?php echo $subject['id']; ?>">
                            <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Weekly Classes:</label>
                <div class="weekly-classes">
                    <input type="number" name="weekly_classes" id="weekly_classes" 
                           min="1" max="3" value="1" required>
                    <span id="remaining_classes">Available slots: 3 of 3</span>
                    <div class="class-indicator available"></div>
                    <div class="class-indicator available"></div>
                    <div class="class-indicator available"></div>
                </div>
            </div>

            <button type="submit" class="btn">Assign Teacher</button>
        </form>
    </div>
</body>
</html> 