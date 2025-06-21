<?php

// Function to convert time to column index
function getColumnIndex($time) {
    // Assuming schedule starts from 7 AM (column 0)
    $hour = (int)date('G', strtotime($time));
    $minute = (int)date('i', strtotime($time));
    return ($hour - 7) + ($minute / 60);
}

// Function to calculate colspan based on start and end time
function calculateColspan($start_time, $end_time) {
    $start_index = getColumnIndex($start_time);
    $end_index = getColumnIndex($end_time);
    return ceil($end_index - $start_index);
}

// Function to convert time to decimal hours (e.g., "13:30" -> 13.5)
function timeToDecimal($time) {
    $parts = explode(':', $time);
    return intval($parts[0]) + (intval($parts[1]) / 60);
}

// Function to calculate rowspan based on start and end times
function calculateRowspan($start_time, $end_time) {
    $start_decimal = timeToDecimal($start_time);
    $end_decimal = timeToDecimal($end_time);
    return ceil($end_decimal - $start_decimal);
}

// Assuming you have a table structure with hours as rows
echo "<table class='schedule-table'>";
echo "<tr><th>Time</th><th>Monday</th><th>Tuesday</th><th>Wednesday</th><th>Thursday</th><th>Friday</th></tr>";

// Generate time slots from 7 AM to 9 PM
$used_slots = array();
for ($hour = 7; $hour <= 21; $hour++) {
    echo "<tr>";
    // Display time slot
    echo "<td>" . sprintf("%02d:00", $hour) . "</td>";
    
    // Loop through each day
    for ($day = 1; $day <= 5; $day++) {
        // Skip if this slot is already used by a spanning schedule
        if (isset($used_slots[$day][$hour])) {
            continue;
        }
        
        // Check if there's a schedule for this day and time
        $time = sprintf("%02d:00:00", $hour);
        $query = "SELECT s.*, sub.subject_name, t.first_name, t.last_name 
                 FROM schedules s 
                 JOIN subjects sub ON s.subject_id = sub.subject_id
                 JOIN teachers t ON s.teacher_id = t.teacher_id
                 WHERE s.day = ? AND TIME(s.start_time) <= ? AND TIME(s.end_time) > ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iss", $day, $time, $time);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($schedule = $result->fetch_assoc()) {
            // Calculate rowspan
            $rowspan = calculateRowspan(
                date('H:i', strtotime($schedule['start_time'])),
                date('H:i', strtotime($schedule['end_time']))
            );
            
            // Mark slots as used
            for ($i = 0; $i < $rowspan; $i++) {
                $used_slots[$day][$hour + $i] = true;
            }
            
            // Display schedule with appropriate rowspan
            echo "<td class='schedule-cell' rowspan='{$rowspan}'>";
            echo "<div class='schedule-content'>";
            echo "<strong>{$schedule['subject_name']}</strong><br>";
            echo "{$schedule['first_name']} {$schedule['last_name']}<br>";
            echo date('g:i A', strtotime($schedule['start_time'])) . " - " . 
                 date('g:i A', strtotime($schedule['end_time']));
            echo "</div>";
            echo "</td>";
        } else {
            echo "<td></td>"; // Empty cell if no schedule
        }
    }
    echo "</tr>";
}
echo "</table>";

// Add some CSS to make it look better
?>
<style>
.schedule-table {
    width: 100%;
    border-collapse: collapse;
}

.schedule-table th,
.schedule-table td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: center;
}

.schedule-cell {
    background-color: #f0f7ff;
    vertical-align: top;
}

.schedule-content {
    padding: 8px;
    border-radius: 4px;
    height: 100%;
}

.schedule-content strong {
    color: #2c5282;
}
</style> 