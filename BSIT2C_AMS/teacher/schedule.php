<?php
// Include the header
require_once 'includes/teacher_header.php';

// Get teacher ID from session
$teacher_id = $_SESSION['teacher_id'];

// Get teacher's name
$teacher_query = "SELECT CONCAT(first_name, ' ', last_name) as teacher_name FROM teachers WHERE id = '$teacher_id'";
$teacher_result = mysqli_query($conn, $teacher_query);
$teacher = mysqli_fetch_assoc($teacher_result);

// Get current date information
$current_date = new DateTime();
$current_month = $current_date->format('Y-m');
$current_day = $current_date->format('Y-m-d');
?>

<div class="container-fluid">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-calendar-alt"></i> 
                Schedule for <?php echo htmlspecialchars($teacher['teacher_name']); ?>
            </h5>
        </div>
    </div>

    <!-- Schedule Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="bg-light">
                        <tr>
                            <th>Time</th>
                            <?php
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                            foreach ($days as $day) {
                                echo "<th>$day</th>";
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Time slots from 7 AM to 8 PM
                        for ($hour = 7; $hour <= 20; $hour++) {
                            echo "<tr>";
                            // Time slot
                            echo "<td class='time-slot'>" . 
                                date('h:i A', strtotime($hour . ':00')) . 
                                "</td>";

                            // Check each day
                            foreach ($days as $day) {
                                echo "<td class='schedule-cell'>";
                                
                                // Query for classes in this time slot
                                $query = "SELECT a.*, s.subject_code, s.subject_name 
                                        FROM assignments a 
                                        JOIN subjects s ON a.subject_id = s.id 
                                        WHERE a.teacher_id = '$teacher_id' 
                                        AND a.preferred_day = '$day' 
                                        AND HOUR(time_start) = $hour";
                                
                                $result = mysqli_query($conn, $query);
                                
                                if ($class = mysqli_fetch_assoc($result)) {
                                    $start_time = date('h:i A', strtotime($class['time_start']));
                                    $end_time = date('h:i A', strtotime($class['time_end']));
                                    
                                    echo "<div class='class-block'>
                                            <div class='subject-code'>{$class['subject_code']}</div>
                                            <div class='subject-name'>{$class['subject_name']}</div>
                                            <div class='class-time'>$start_time - $end_time</div>
                                            <div class='class-location'>
                                                <i class='fas fa-map-marker-alt'></i> {$class['location']}
                                            </div>
                                          </div>";
                                }
                                
                                echo "</td>";
                            }
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- List of All Assignments -->
    <div class="card mt-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Complete Schedule Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Subject</th>
                            <th>Time</th>
                            <th>Location</th>
                            <th>Period</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $schedule_query = "SELECT a.*, s.subject_code, s.subject_name 
                                         FROM assignments a 
                                         JOIN subjects s ON a.subject_id = s.id 
                                         WHERE a.teacher_id = '$teacher_id' 
                                         ORDER BY FIELD(a.preferred_day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                                         a.time_start";
                        
                        $schedule_result = mysqli_query($conn, $schedule_query);
                        
                        while ($schedule = mysqli_fetch_assoc($schedule_result)) {
                            $start_time = date('h:i A', strtotime($schedule['time_start']));
                            $end_time = date('h:i A', strtotime($schedule['time_end']));
                            $start_date = date('M d, Y', strtotime($schedule['month_from']));
                            $end_date = date('M d, Y', strtotime($schedule['month_to']));
                            
                            echo "<tr>
                                    <td>{$schedule['preferred_day']}</td>
                                    <td>
                                        <strong>{$schedule['subject_code']}</strong><br>
                                        <small class='text-muted'>{$schedule['subject_name']}</small>
                                    </td>
                                    <td>$start_time - $end_time</td>
                                    <td>{$schedule['location']}</td>
                                    <td>$start_date - $end_date</td>
                                  </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.time-slot {
    width: 100px;
    font-weight: bold;
    background-color: #f8f9fa;
    text-align: center;
}

.schedule-cell {
    position: relative;
    height: 80px;
    padding: 5px;
}

.class-block {
    background-color: #e3f2fd;
    border-left: 4px solid #1976d2;
    border-radius: 4px;
    padding: 8px;
    height: 100%;
    cursor: pointer;
    transition: all 0.3s ease;
}

.class-block:hover {
    transform: scale(1.02);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    background-color: #bbdefb;
}

.subject-code {
    font-weight: bold;
    color: #1976d2;
    font-size: 0.9rem;
}

.subject-name {
    font-size: 0.8rem;
    margin-bottom: 3px;
}

.class-time {
    font-size: 0.75rem;
    color: #666;
    margin-bottom: 2px;
}

.class-location {
    font-size: 0.75rem;
    color: #666;
}

/* Highlight current day */
.current-day {
    background-color: #fff3e0;
}

@media (max-width: 768px) {
    .subject-name, .class-location {
        display: none;
    }
    
    .schedule-cell {
        height: 60px;
    }
}
</style>

<script>
// Highlight current day
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toLocaleString('en-us', {weekday: 'long'});
    const cells = document.querySelectorAll('th');
    cells.forEach(cell => {
        if (cell.textContent === today) {
            const columnIndex = Array.from(cell.parentElement.children).indexOf(cell);
            document.querySelectorAll(`td:nth-child(${columnIndex + 1})`).forEach(td => {
                td.classList.add('current-day');
            });
        }
    });
});
</script>

<?php include 'includes/teacher_footer.php'; ?> 