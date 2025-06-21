<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
include 'includes/header.php';

$teacher_id = $_SESSION['teacher_id'];

// Get teacher's schedule
$query = "SELECT 
    s.id as subject_id,
    s.subject_code,
    s.subject_name,
    a.preferred_day,
    a.time_start,
    a.time_end,
    a.location,
    a.month_from,
    a.month_to
FROM assignments a
JOIN subjects s ON a.subject_id = s.id
WHERE a.teacher_id = ?
ORDER BY 
    FIELD(a.preferred_day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
    a.time_start";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

// Process the schedule data to handle both single day and multiple days (JSON)
$schedules = [];
while ($row = $result->fetch_assoc()) {
    // Check if preferred_day is in JSON format
    $preferred_days = @json_decode($row['preferred_day'], true);
    
    if (is_array($preferred_days)) {
        // Create a schedule entry for each day in the array
        foreach ($preferred_days as $day) {
            // Create a copy of the row
            $schedule_entry = $row;
            $schedule_entry['preferred_day'] = $day;
            $schedules[] = $schedule_entry;
        }
    } else {
        $schedules[] = $row;
    }
}

// Get current date and week
$current_date = new DateTime();
$current_day = $current_date->format('l'); // Gets current day name

// Handle week navigation
$selected_week = isset($_GET['week']) ? new DateTime($_GET['week']) : clone $current_date;
$week_start = clone $selected_week;
$week_start->modify('monday this week');
$week_end = clone $week_start;
$week_end->modify('sunday this week');

// Create array of week days
$week_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>

<style>
    :root {
        --primary-color: #021F3F;
        --secondary-color: #C8A77E;
        --primary-dark: #011327;
        --secondary-light: #d8b78e;
        --text-dark: #1f2937;
        --text-light: #6b7280;
        --success-color: #059669;
        --warning-color: #d97706;
        --danger-color: #dc2626;
        --card-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f5f7fa;
        color: var(--text-dark);
    }
    
    .page-title {
        color: var(--primary-color);
        font-weight: 600;
        margin-bottom: 1rem;
        font-size: 1.5rem;
    }
    
    .schedule-container {
        background-color: white;
        border-radius: 8px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
    }
    
    .schedule-header {
        background: var(--primary-color);
        color: white;
        padding: 1rem;
    }
    
    .schedule-grid {
        display: grid;
        grid-template-columns: 80px repeat(6, 1fr);
        gap: 1px;
        background-color: #e5e7eb;
    }
    
    .schedule-wrapper {
        overflow-x: auto;
        margin: 0 -1rem;
        padding: 0 1rem;
    }
    
    .time-slot {
        background-color: white;
        padding: 0.5rem;
        font-weight: 500;
        color: var(--primary-color);
        text-align: center;
        border-bottom: 1px solid #e5e7eb;
        position: sticky;
        left: 0;
        z-index: 1;
        font-size: 0.875rem;
    }
    
    .day-header {
        background-color: white;
        padding: 0.75rem;
    text-align: center;
        font-weight: 500;
        color: var(--primary-color);
        border-bottom: 1px solid #e5e7eb;
        position: sticky;
        top: 0;
        z-index: 1;
        font-size: 0.875rem;
    }
    
    .day-header.current {
        background-color: var(--secondary-color);
        color: white;
}

.schedule-cell {
        background-color: white;
        min-height: 80px;
        padding: 0.5rem;
        border-bottom: 1px solid #e5e7eb;
}

.class-block {
        background-color: rgba(2, 31, 63, 0.05);
        border-left: 3px solid var(--primary-color);
    border-radius: 4px;
        padding: 0.5rem;
        margin-bottom: 0.25rem;
    cursor: pointer;
        transition: all 0.2s ease;
}

.class-block:hover {
        background-color: rgba(2, 31, 63, 0.1);
        transform: translateX(2px);
    }
    
    .class-block .subject-code {
        font-weight: 600;
        color: var(--primary-color);
        margin-bottom: 0.25rem;
        font-size: 0.875rem;
    }
    
    .class-block .subject-name {
        font-size: 0.75rem;
        color: var(--text-light);
        margin-bottom: 0.25rem;
    }
    
    .class-block .class-time {
        font-size: 0.75rem;
        color: var(--secondary-color);
    }
    
    .class-block .class-location {
    font-size: 0.75rem;
        color: var(--text-light);
    }
    
    .schedule-actions {
        display: flex;
        gap: 0.25rem;
        margin-top: 0.25rem;
    }
    
    .schedule-actions .btn {
        flex: 1;
    font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    
    .no-schedule {
        text-align: center;
        padding: 2rem;
        color: var(--text-light);
    }
    
    .no-schedule i {
        font-size: 2.5rem;
        margin-bottom: 0.75rem;
        color: #d1d5db;
    }
    
    .week-navigation {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
        background-color: white;
        padding: 0.5rem;
        border-radius: 8px;
        box-shadow: var(--card-shadow);
    }
    
    .week-navigation .btn {
        background-color: white;
        border: 1px solid var(--primary-color);
        color: var(--primary-color);
        padding: 0.5rem 1rem;
        border-radius: 5px;
        transition: all 0.2s ease;
        font-size: 0.875rem;
        min-width: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .week-navigation .btn:hover {
        background-color: var(--primary-color);
        color: white;
    }
    
    .week-navigation .current-week {
        font-weight: 500;
        color: var(--primary-color);
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        background-color: rgba(2, 31, 63, 0.05);
        border-radius: 5px;
        min-width: 200px;
        text-align: center;
    }
</style>

<div class="container py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="page-title mb-0">Weekly Schedule</h1>
        <div class="week-navigation d-flex align-items-center gap-3">
            <a href="?week=<?= $week_start->modify('-1 week')->format('Y-m-d') ?>" class="btn">
                <i class="bi bi-chevron-left"></i> Previous
            </a>
            <span class="current-week px-3">
                <?= $week_start->modify('+1 week')->format('M d') ?> - 
                <?= $week_end->format('M d, Y') ?>
            </span>
            <a href="?week=<?= $week_start->modify('+1 week')->format('Y-m-d') ?>" class="btn">
                Next <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>
    
    <?php if (count($schedules) === 0): ?>
    <div class="no-schedule">
        <i class="bi bi-calendar-x"></i>
        <h3>No Schedule Found</h3>
        <p class="text-muted">You don't have any assigned schedules yet.</p>
    </div>
    <?php else: ?>
    <div class="schedule-container">
        <div class="schedule-header">
            <h5 class="mb-0">Weekly Class Schedule</h5>
        </div>
        
        <div class="schedule-wrapper">
            <div class="schedule-grid">
                <!-- Time column -->
                <div class="time-slot">Time</div>
                
                <!-- Day headers -->
                <?php foreach ($week_days as $day): ?>
                    <div class="day-header <?= ($day === $current_day) ? 'current' : '' ?>">
                        <?= $day ?>
                    </div>
                <?php endforeach; ?>
                
                <!-- Time slots -->
                <?php
                $start_time = new DateTime('07:30:00');
                $end_time = new DateTime('17:30:00');
                $interval = new DateInterval('PT30M'); // 30-minute interval
                
                $current_time = clone $start_time;
                while ($current_time <= $end_time): 
                    $time_slot = $current_time->format('h:i A');
                    $next_time = clone $current_time;
                    $next_time->add($interval);
                ?>
                    <div class="time-slot">
                        <?= $time_slot ?>
                    </div>
                    
                    <!-- Schedule cells for each day -->
                    <?php foreach ($week_days as $day): ?>
                        <div class="schedule-cell">
                            <?php
                            // Loop through each schedule
                            foreach ($schedules as $schedule) {
                                // Check if the schedule is for this day and time slot
                                $schedule_start = new DateTime($schedule['time_start']);
                                $schedule_end = new DateTime($schedule['time_end']);
                                
                                // Handle both string day format and JSON format
                                $day_matches = false;
                                
                                // Check if the preferred_day is a valid day name or a string representation of the day
                                if (strtolower($schedule['preferred_day']) === strtolower($day) || 
                                    strtolower(getDayName($schedule['preferred_day'])) === strtolower($day)) {
                                    $day_matches = true;
                                }
                                
                                if ($day_matches && 
                                    $current_time >= $schedule_start && 
                                    $current_time < $schedule_end) {
                                    
                                    // Format the schedule time
                                    $formatted_start = date('h:i A', strtotime($schedule['time_start']));
                                    $formatted_end = date('h:i A', strtotime($schedule['time_end']));
                            ?>
                                <div class="class-block">
                                    <div class="subject-code"><?= htmlspecialchars($schedule['subject_code']) ?></div>
                                    <div class="subject-name"><?= htmlspecialchars($schedule['subject_name']) ?></div>
                                    <div class="class-time"><?= $formatted_start ?> - <?= $formatted_end ?></div>
                                    <div class="class-location"><?= htmlspecialchars($schedule['location']) ?></div>
                                    <div class="schedule-actions">
                                        <a href="attendance/take_attendance.php?subject_id=<?= $schedule['subject_id'] ?>&assignment_id=<?= $schedule['id'] ?? '0' ?>&date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-clipboard-check"></i> Attendance
                                        </a>
                                    </div>
                                </div>
                            <?php
                                }
                            }
                            ?>
                        </div>
                    <?php endforeach; ?>
                
                <?php 
                    $current_time->add($interval);
                endwhile; 
                ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include dirname(__FILE__) . '/includes/footer.php'; ?> 