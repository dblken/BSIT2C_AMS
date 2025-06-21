<?php
require_once '../config/database.php';
include 'includes/teacher_header.php';

// Get teacher ID from session
$teacher_id = $_SESSION['teacher_id'];

// Get teacher's name
$teacher_query = "SELECT CONCAT(first_name, ' ', last_name) as teacher_name FROM teachers WHERE id = '$teacher_id'";
$teacher_result = mysqli_query($conn, $teacher_query);
$teacher = mysqli_fetch_assoc($teacher_result);
?>

<div class="container-fluid">
    <!-- Notifications Section -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-bell"></i> Notifications</h5>
                    <span class="badge bg-light text-primary" id="notificationCount">0</span>
                </div>
                <div class="card-body" id="notificationsContainer" style="max-height: 200px; overflow-y: auto;">
                    <?php
                    $notifications_query = "SELECT * FROM notifications 
                                          WHERE teacher_id = '$teacher_id' 
                                          ORDER BY created_at DESC LIMIT 5";
                    $notifications_result = mysqli_query($conn, $notifications_query);
                    while ($notification = mysqli_fetch_assoc($notifications_result)) {
                        $badge_class = $notification['is_read'] ? 'text-muted' : 'text-primary fw-bold';
                        echo "<div class='notification-item p-2 border-bottom {$badge_class}'>
                                <i class='fas fa-info-circle me-2'></i>
                                {$notification['message']}
                                <small class='text-muted d-block'>
                                    " . date('M d, Y h:i A', strtotime($notification['created_at'])) . "
                                </small>
                              </div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar Section -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt"></i> 
                    Teaching Schedule - <?php echo htmlspecialchars($teacher['teacher_name']); ?>
                </h5>
                <div>
                    <button class="btn btn-light btn-sm" onclick="previousWeek()">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span id="currentWeek" class="mx-3 text-white"></span>
                    <button class="btn btn-light btn-sm" onclick="nextWeek()">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered calendar-table">
                    <thead>
                        <tr class="bg-light">
                            <th style="width: 100px;">Time</th>
                            <th>Monday</th>
                            <th>Tuesday</th>
                            <th>Wednesday</th>
                            <th>Thursday</th>
                            <th>Friday</th>
                            <th>Saturday</th>
                        </tr>
                    </thead>
                    <tbody id="calendarBody">
                        <!-- Time slots will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.calendar-table th {
    text-align: center;
    padding: 15px;
}

.calendar-table td {
    height: 100px;
    padding: 5px;
    vertical-align: top;
}

.schedule-item {
    background-color: #e3f2fd;
    border-left: 4px solid #1976d2;
    border-radius: 4px;
    padding: 8px;
    margin-bottom: 5px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.schedule-item:hover {
    transform: scale(1.02);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.subject-code {
    font-weight: bold;
    color: #1976d2;
}

.subject-details {
    font-size: 0.85rem;
    color: #666;
}

.location-badge {
    background-color: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8rem;
    color: #666;
}

.current-day {
    background-color: #fff3e0;
}

.notification-item {
    transition: all 0.3s ease;
}

.notification-item:hover {
    background-color: #f8f9fa;
}
</style>

<script>
let currentWeek = new Date();

function loadCalendar(weekStart) {
    fetch(`get_teacher_schedule.php?week_start=${weekStart.toISOString().split('T')[0]}&teacher_id=<?php echo $teacher_id; ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCalendarDisplay(data.schedules, data.week_dates);
                document.getElementById('currentWeek').textContent = 
                    `${formatDate(new Date(data.week_dates[0]))} - ${formatDate(new Date(data.week_dates[5]))}`;
            }
        });
}

function updateCalendarDisplay(schedules, weekDates) {
    const tbody = document.getElementById('calendarBody');
    tbody.innerHTML = '';

    // Create time slots from 7 AM to 9 PM
    for (let hour = 7; hour <= 21; hour++) {
        const row = document.createElement('tr');
        const timeCell = document.createElement('td');
        timeCell.textContent = formatTime(hour);
        timeCell.className = 'text-center fw-bold';
        row.appendChild(timeCell);

        // Create cells for each day
        for (let day = 0; day < 6; day++) {
            const cell = document.createElement('td');
            const daySchedules = schedules.filter(s => 
                s.preferred_day === getDayName(day) && 
                parseInt(s.time_start.split(':')[0]) === hour
            );

            daySchedules.forEach(schedule => {
                const scheduleDiv = document.createElement('div');
                scheduleDiv.className = 'schedule-item';
                scheduleDiv.innerHTML = `
                    <div class="subject-code">${schedule.subject_code}</div>
                    <div class="subject-details">${schedule.subject_name}</div>
                    <div class="location-badge">
                        <i class="fas fa-map-marker-alt"></i> ${schedule.location}
                    </div>
                    <div class="subject-details">
                        ${formatTime(parseInt(schedule.time_start.split(':')[0]))} - 
                        ${formatTime(parseInt(schedule.time_end.split(':')[0]))}
                    </div>
                `;
                cell.appendChild(scheduleDiv);
            });

            row.appendChild(cell);
        }
        tbody.appendChild(row);
    }

    // Highlight current day
    highlightCurrentDay();
}

function formatTime(hour) {
    return `${hour % 12 || 12}:00 ${hour >= 12 ? 'PM' : 'AM'}`;
}

function getDayName(index) {
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    return days[index];
}

function formatDate(date) {
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });
}

function previousWeek() {
    currentWeek.setDate(currentWeek.getDate() - 7);
    loadCalendar(currentWeek);
}

function nextWeek() {
    currentWeek.setDate(currentWeek.getDate() + 7);
    loadCalendar(currentWeek);
}

function highlightCurrentDay() {
    const today = new Date();
    const dayOfWeek = today.getDay() || 7; // Convert Sunday (0) to 7
    const cells = document.querySelectorAll(`.calendar-table td:nth-child(${dayOfWeek + 1})`);
    cells.forEach(cell => cell.classList.add('current-day'));
}

// Load initial calendar
document.addEventListener('DOMContentLoaded', function() {
    loadCalendar(currentWeek);
    
    // Check for new notifications periodically
    setInterval(checkNewNotifications, 60000); // Check every minute
});

function checkNewNotifications() {
    fetch('check_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.new_notifications > 0) {
                updateNotificationBadge(data.new_notifications);
            }
        });
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationCount');
    badge.textContent = count;
    if (count > 0) {
        badge.style.display = 'inline';
    } else {
        badge.style.display = 'none';
    }
}
</script> 