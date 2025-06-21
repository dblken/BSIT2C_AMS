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

// Helper function to get the day name from schedule
function getDayName(dayCode) {
    const days = {
        'M': 'Monday',
        'T': 'Tuesday',
        'W': 'Wednesday',
        'TH': 'Thursday',
        'F': 'Friday',
        'SAT': 'Saturday',
        'SUN': 'Sunday',
        '1': 'Monday',
        '2': 'Tuesday',
        '3': 'Wednesday',
        '4': 'Thursday',
        '5': 'Friday',
        '6': 'Saturday',
        '7': 'Sunday'
    };
    return days[dayCode] || dayCode;
}

// Load the calendar
function loadCalendar(weekStart) {
    fetch(`get_teacher_schedule.php?week_start=${weekStart}&teacher_id=<?= $teacher_id ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Display the date range in the header
                const startDate = new Date(weekStart);
                const endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + 5);
                
                document.getElementById('week-date-range').textContent = 
                    `${startDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${endDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
                
                // Clear existing schedule cells
                const cells = document.querySelectorAll('.schedule-cell');
                cells.forEach(cell => cell.innerHTML = '');
                
                // Fill the schedule
                data.schedules.forEach(s => {
                    // For each schedule, determine which day it belongs to
                    const dayIndex = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'].indexOf(getDayName(s.preferred_day));
                    if (dayIndex === -1) return; // Skip if day is not valid
                    
                    // Get time slot
                    const startTime = new Date(`2000-01-01T${s.time_start}`);
                    const endTime = new Date(`2000-01-01T${s.time_end}`);
                    const startHour = startTime.getHours();
                    const startMinutes = startTime.getMinutes();
                    
                    // Determine time slot cell
                    const timeSlotIndex = (startHour - 7) * 2 + (startMinutes >= 30 ? 1 : 0);
                    
                    // Calculate cell index in the grid (16 columns: 1 for time + 6 for days)
                    const cellIndex = (timeSlotIndex + 1) * 7 + dayIndex + 1; // +1 for the header row
                    
                    // Find or create cell
                    if (cellIndex < cells.length) {
                        const classBlock = document.createElement('div');
                        classBlock.className = 'class-block';
                        classBlock.innerHTML = `
                            <div class="subject-code">${s.subject_code}</div>
                            <div class="subject-name">${s.subject_name}</div>
                            <div class="class-time">${formatTime(s.time_start)} - ${formatTime(s.time_end)}</div>
                            <div class="class-location">${s.location}</div>
                            <div class="schedule-actions">
                                <a href="attendance/take_attendance.php?subject_id=${s.subject_id}&assignment_id=${s.id}&date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-clipboard-check"></i> Attendance
                                </a>
                            </div>
                        `;
                        cells[cellIndex].appendChild(classBlock);
                    }
                });
            } else {
                console.error('Failed to load schedule:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading schedule:', error);
        });
}

function formatTime(hour) {
    // Check if hour is a string that might contain minutes
    if (typeof hour === 'string' && hour.includes(':')) {
        // It's already a formatted time string, parse it
        const [hours, minutes] = hour.split(':');
        const h = parseInt(hours);
        return `${h % 12 || 12}:${minutes} ${h >= 12 ? 'PM' : 'AM'}`;
    } else {
        // It's just an hour number
        const h = parseInt(hour);
        return `${h % 12 || 12}:00 ${h >= 12 ? 'PM' : 'AM'}`;
    }
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