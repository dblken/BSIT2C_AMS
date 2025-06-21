<?php
// Include database connection
require_once 'config/database.php';

// Get today's day
$today_day = date('N'); // 1 (Monday) to 7 (Sunday)
$day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$today_name = $day_names[$today_day - 1];

echo "<h3>Day Debugging Information</h3>";
echo "<p>Today is day $today_day: $today_name</p>";

// Get all assignments with their preferred days
$query = "
    SELECT 
        s.subject_code,
        s.subject_name,
        a.preferred_day,
        a.time_start,
        a.time_end,
        a.location,
        CONCAT(t.first_name, ' ', t.last_name) as teacher_name
    FROM 
        assignments a
    JOIN 
        subjects s ON a.subject_id = s.id
    JOIN 
        teachers t ON a.teacher_id = t.id
    ORDER BY 
        a.preferred_day, a.time_start";

$result = $conn->query($query);

if ($result) {
    echo "<h4>Found " . $result->num_rows . " assignments</h4>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Subject Code</th><th>Subject Name</th><th>Preferred Day (Raw)</th><th>Teacher</th><th>Time</th><th>Location</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['subject_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['subject_name']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($row['preferred_day']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['teacher_name']) . "</td>";
        echo "<td>" . date('h:i A', strtotime($row['time_start'])) . " - " . date('h:i A', strtotime($row['time_end'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['location']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>Error: " . $conn->error . "</p>";
}

// Day code conversion mapping for reference
echo "<h4>Day Code Mapping</h4>";
echo "<ul>";
echo "<li>1 or M = Monday</li>";
echo "<li>2 or T = Tuesday</li>";
echo "<li>3 or W = Wednesday</li>";
echo "<li>4 or TH = Thursday</li>";
echo "<li>5 or F = Friday</li>";
echo "<li>6 or SAT = Saturday</li>";
echo "<li>7 or SUN = Sunday</li>";
echo "</ul>";
?> 