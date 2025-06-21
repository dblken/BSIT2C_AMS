<?php
// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<h1>Test Adding Assignment</h1>";

// Get all teachers
$teachers = $conn->query("SELECT id, teacher_id, first_name, last_name FROM teachers WHERE status = 'Active'");
// Get all subjects
$subjects = $conn->query("SELECT id, subject_code, subject_name FROM subjects");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Form submitted</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    try {
        // Format days array for the database
        $days = isset($_POST['days']) ? $_POST['days'] : [];
        $preferred_days = json_encode($days);
        
        // Default dates if not provided
        $month_from = isset($_POST['month_from']) ? $_POST['month_from'] : date('Y-m-d');
        $month_to = isset($_POST['month_to']) ? $_POST['month_to'] : date('Y-m-d', strtotime('+6 months'));
        
        // Insert assignment
        $query = "INSERT INTO assignments (
            teacher_id, subject_id, month_from, month_to,
            preferred_day, time_start, time_end, location
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }
        
        $stmt->bind_param("iissssss", 
            $_POST['teacher_id'], 
            $_POST['subject_id'], 
            $month_from,
            $month_to,
            $preferred_days,
            $_POST['time_start'],
            $_POST['time_end'],
            $_POST['location']
        );
        
        if ($stmt->execute()) {
            echo "<div style='background-color: #d4edda; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>
                <strong>Success!</strong> Assignment added successfully.
            </div>";
        } else {
            throw new Exception("Error executing statement: " . $stmt->error);
        }
    } catch (Exception $e) {
        echo "<div style='background-color: #f8d7da; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>
            <strong>Error:</strong> " . $e->getMessage() . "
        </div>";
    }
}
?>

<form method="POST" action="">
    <div style="margin-bottom: 20px;">
        <h3>Teacher</h3>
        <select name="teacher_id" required>
            <option value="">Select Teacher</option>
            <?php while ($teacher = $teachers->fetch_assoc()): ?>
                <option value="<?php echo $teacher['id']; ?>">
                    <?php echo htmlspecialchars($teacher['teacher_id'] . ' - ' . $teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    
    <div style="margin-bottom: 20px;">
        <h3>Subject</h3>
        <select name="subject_id" required>
            <option value="">Select Subject</option>
            <?php while ($subject = $subjects->fetch_assoc()): ?>
                <option value="<?php echo $subject['id']; ?>">
                    <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    
    <div style="margin-bottom: 20px;">
        <h3>Schedule</h3>
        <div>
            <label><strong>Days:</strong></label><br>
            <?php
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            foreach ($days as $day): ?>
                <label>
                    <input type="checkbox" name="days[]" value="<?php echo $day; ?>"> 
                    <?php echo $day; ?>
                </label>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top: 10px;">
            <label><strong>Time Start:</strong></label>
            <input type="time" name="time_start" required>
        </div>
        
        <div style="margin-top: 10px;">
            <label><strong>Time End:</strong></label>
            <input type="time" name="time_end" required>
        </div>
        
        <div style="margin-top: 10px;">
            <label><strong>Location:</strong></label>
            <input type="text" name="location" value="Room" required>
        </div>
    </div>
    
    <div>
        <button type="submit" style="background-color: #021F3F; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
            Add Assignment
        </button>
    </div>
</form> 