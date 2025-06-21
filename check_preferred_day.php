<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "CHECKING PREFERRED_DAY FORMAT\n";
echo "===========================\n\n";

// Include database connection
require_once 'config/database.php';

// Check assignments table
$result = mysqli_query($conn, "SELECT id, preferred_day FROM assignments LIMIT 5");
if ($result && mysqli_num_rows($result) > 0) {
    echo "Sample preferred_day values:\n";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo "\nID: {$row['id']}\n";
        echo "- Raw value: " . $row['preferred_day'] . "\n";
        echo "- PHP typeof: " . gettype($row['preferred_day']) . "\n";
        echo "- Length: " . strlen($row['preferred_day']) . "\n";
        echo "- First 20 chars: " . substr($row['preferred_day'], 0, 20) . "\n";
        
        // Try to decode as JSON
        $decoded = json_decode($row['preferred_day'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "- Decodable as JSON: Yes\n";
            echo "- Decoded value: " . print_r($decoded, true) . "\n";
        } else {
            echo "- Decodable as JSON: No\n";
            echo "- JSON error: " . json_last_error_msg() . "\n";
        }
    }
} else {
    echo "No assignments found or error: " . mysqli_error($conn) . "\n";
}

// Check timetable to see day format
$result = mysqli_query($conn, "SELECT id, day FROM timetable LIMIT 5");
if ($result && mysqli_num_rows($result) > 0) {
    echo "\nSample timetable day values:\n";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo "\nID: {$row['id']}\n";
        echo "- Day value: {$row['day']} (type: " . gettype($row['day']) . ")\n";
        
        $day_name = '';
        switch ($row['day']) {
            case 1: $day_name = 'Monday'; break;
            case 2: $day_name = 'Tuesday'; break;
            case 3: $day_name = 'Wednesday'; break;
            case 4: $day_name = 'Thursday'; break;
            case 5: $day_name = 'Friday'; break;
            case 6: $day_name = 'Saturday'; break;
            case 0: case 7: $day_name = 'Sunday'; break;
            default: $day_name = 'Unknown';
        }
        
        echo "- Day name: $day_name\n";
    }
}

echo "\nDone.\n";
?> 