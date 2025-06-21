<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "CHECKING TIMETABLE ENTRIES FOR ASSIGNMENT #5\n";
echo "=========================================\n\n";

// Include database connection
require_once 'config/database.php';

// Query to get timetable entries for assignment 5
$query = "SELECT t.id, t.subject_id, t.day, t.time_start, t.time_end, 
                 s.subject_code, s.subject_name
          FROM timetable t
          LEFT JOIN subjects s ON t.subject_id = s.id
          WHERE t.assignment_id = 5";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    echo "Found " . mysqli_num_rows($result) . " timetable entries for assignment #5:\n\n";
    
    echo "ID | Subject | Day | Time\n";
    echo "--------------------------------\n";
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Convert day number to name
        $day_name = '';
        switch($row['day']) {
            case 0: $day_name = 'Sunday'; break;
            case 1: $day_name = 'Monday'; break;
            case 2: $day_name = 'Tuesday'; break;
            case 3: $day_name = 'Wednesday'; break;
            case 4: $day_name = 'Thursday'; break;
            case 5: $day_name = 'Friday'; break;
            case 6: $day_name = 'Saturday'; break;
            default: $day_name = 'Unknown';
        }
        
        echo "{$row['id']} | {$row['subject_code']} | {$day_name} | {$row['time_start']} - {$row['time_end']}\n";
    }
} else {
    echo "No timetable entries found for assignment #5.\n";
    echo "Error: " . mysqli_error($conn) . "\n";
}

// Check assignment details
echo "\nASSIGNMENT DETAILS:\n";
echo "=================\n\n";

$assignment_query = "SELECT a.id, a.subject_id, a.teacher_id, a.preferred_day, a.time_start, a.time_end,
                           s.subject_code, s.subject_name, 
                           CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                    FROM assignments a
                    LEFT JOIN subjects s ON a.subject_id = s.id
                    LEFT JOIN teachers t ON a.teacher_id = t.id
                    WHERE a.id = 5";

$assignment_result = mysqli_query($conn, $assignment_query);

if ($assignment_result && mysqli_num_rows($assignment_result) > 0) {
    $assignment = mysqli_fetch_assoc($assignment_result);
    
    echo "ID: {$assignment['id']}\n";
    echo "Subject: {$assignment['subject_code']} - {$assignment['subject_name']}\n";
    echo "Teacher: {$assignment['teacher_name']}\n";
    echo "Preferred Days: {$assignment['preferred_day']}\n";
    echo "Time: {$assignment['time_start']} - {$assignment['time_end']}\n";
    } else {
    echo "Assignment #5 not found.\n";
    echo "Error: " . mysqli_error($conn) . "\n";
}

echo "\nDone.\n";

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Structure Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
        h2 { color: #444; margin-top: 20px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th { background-color: #f2f2f2; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .warning { color: #e74c3c; font-weight: bold; }
        .success { color: #2ecc71; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Timetable Table Structure</h1>

<?php
try {
    // Check if timetable table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'timetable'");
    if ($table_check->num_rows == 0) {
        echo "<p class='warning'>Error: The timetable table does not exist in the database!</p>";
        exit;
    }
    
    // Get table structure
    $structure = $conn->query("DESCRIBE timetable");
    
    if ($structure) {
        echo "<h2>Table Columns</h2>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        $columns = array();
        while ($column = $structure->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "<td>{$column['Extra']}</td>";
            echo "</tr>";
            
            // Store column names
            $columns[] = $column['Field'];
        }
        echo "</table>";
        
        // Check for necessary columns
        $required_columns = ['id', 'subject_id', 'assignment_id', 'day', 'time_start', 'time_end'];
        $missing_columns = array_diff($required_columns, $columns);
        
        if (!empty($missing_columns)) {
            echo "<p class='warning'>Warning: The following required columns are missing: " . implode(', ', $missing_columns) . "</p>";
        } else {
            echo "<p class='success'>All required columns are present in the timetable table.</p>";
        }
        
        // Check specifically for teacher_id
        if (!in_array('teacher_id', $columns)) {
            echo "<p class='warning'>Note: The 'teacher_id' column is missing from the timetable table. This is expected as we're getting teacher information through the assignment relation.</p>";
        }
        
        // Show sample data
        echo "<h2>Sample Data (First 3 rows)</h2>";
        $data = $conn->query("SELECT * FROM timetable LIMIT 3");
        
        if ($data->num_rows > 0) {
            echo "<table>";
            
            // Table header
            echo "<tr>";
            foreach ($columns as $column) {
                echo "<th>{$column}</th>";
            }
            echo "</tr>";
            
            // Table data
            while ($row = $data->fetch_assoc()) {
                echo "<tr>";
                foreach ($columns as $column) {
                    echo "<td>" . (isset($row[$column]) ? htmlspecialchars($row[$column]) : 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p>No data found in the timetable table.</p>";
        }
        
    } else {
        echo "<p class='warning'>Error: Unable to retrieve table structure: " . $conn->error . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='warning'>Error: " . $e->getMessage() . "</p>";
}

// Close connection
$conn->close();
?>

</body>
</html> 