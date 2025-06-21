<?php
// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<h1>Assignments Table Diagnostic Tool</h1>";

// Function to output section header
function outputSection($title) {
    echo "<h2 style='margin-top: 30px; padding-bottom: 5px; border-bottom: 1px solid #ccc;'>{$title}</h2>";
}

// Check if the assignments table exists
outputSection("Table Check");
$table_check = $conn->query("SHOW TABLES LIKE 'assignments'");
if ($table_check->num_rows === 0) {
    echo "<div style='color: red; font-weight: bold;'>The assignments table does not exist!</div>";
    
    // Create the table
    echo "<p>Attempting to create the assignments table...</p>";
    
    $create_table_sql = "CREATE TABLE `assignments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `teacher_id` int(11) NOT NULL,
        `subject_id` int(11) NOT NULL,
        `preferred_day` text DEFAULT NULL,
        `time_start` time NOT NULL,
        `time_end` time NOT NULL,
        `location` varchar(255) NOT NULL,
        `month_from` date DEFAULT NULL,
        `month_to` date DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `teacher_id` (`teacher_id`),
        KEY `subject_id` (`subject_id`),
        CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
        CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($create_table_sql)) {
        echo "<div style='color: green; font-weight: bold;'>Successfully created the assignments table!</div>";
    } else {
        echo "<div style='color: red; font-weight: bold;'>Failed to create the table: " . $conn->error . "</div>";
    }
    
} else {
    echo "<div style='color: green;'>The assignments table exists.</div>";
}

// Check and display columns
outputSection("Column Structure");
$columns_check = $conn->query("SHOW COLUMNS FROM assignments");
if ($columns_check) {
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $has_preferred_day = false;
    while ($column = $columns_check->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] !== null ? $column['Default'] : 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
        
        if ($column['Field'] === 'preferred_day') {
            $has_preferred_day = true;
            if ($column['Type'] !== 'text') {
                echo "<div style='color: red; margin-top: 10px;'>Warning: preferred_day column is not of type 'text'.</div>";
            }
        }
    }
    echo "</table>";
    
    if (!$has_preferred_day) {
        echo "<div style='color: red; margin-top: 10px;'>Error: preferred_day column is missing!</div>";
        
        // Add the column
        echo "<p>Attempting to add the preferred_day column...</p>";
        $add_column_sql = "ALTER TABLE assignments ADD COLUMN preferred_day text DEFAULT NULL";
        if ($conn->query($add_column_sql)) {
            echo "<div style='color: green; font-weight: bold;'>Successfully added the preferred_day column!</div>";
        } else {
            echo "<div style='color: red; font-weight: bold;'>Failed to add column: " . $conn->error . "</div>";
        }
    }
} else {
    echo "<div style='color: red; font-weight: bold;'>Failed to get column information: " . $conn->error . "</div>";
}

// Check foreign keys
outputSection("Foreign Key Constraints");
$fk_check = $conn->query("
    SELECT 
        CONSTRAINT_NAME, 
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
        TABLE_SCHEMA = DATABASE() AND
        TABLE_NAME = 'assignments' AND
        REFERENCED_TABLE_NAME IS NOT NULL;
");

if ($fk_check) {
    if ($fk_check->num_rows > 0) {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f0f0f0;'><th>Constraint Name</th><th>Column</th><th>Referenced Table</th><th>Referenced Column</th></tr>";
        
        while ($fk = $fk_check->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($fk['CONSTRAINT_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['COLUMN_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['REFERENCED_TABLE_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div style='color: red;'>No foreign key constraints found. This may cause referential integrity issues.</div>";
        
        // Add foreign keys
        echo "<p>Attempting to add the necessary foreign key constraints...</p>";
        
        $add_fk_sql = "
            ALTER TABLE assignments
            ADD CONSTRAINT assignments_ibfk_1 FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE,
            ADD CONSTRAINT assignments_ibfk_2 FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
        ";
        
        if ($conn->query($add_fk_sql)) {
            echo "<div style='color: green; font-weight: bold;'>Successfully added foreign key constraints!</div>";
        } else {
            echo "<div style='color: red; font-weight: bold;'>Failed to add foreign keys: " . $conn->error . "</div>";
        }
    }
} else {
    echo "<div style='color: red; font-weight: bold;'>Failed to check foreign keys: " . $conn->error . "</div>";
}

// Sample data check
outputSection("Sample Data");
$data_check = $conn->query("SELECT COUNT(*) as count FROM assignments");
if ($data_check) {
    $count = $data_check->fetch_assoc()['count'];
    echo "<div>Total assignments: {$count}</div>";
    
    if ($count > 0) {
        $sample_data = $conn->query("
            SELECT a.*, 
                   CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                   s.subject_code, s.subject_name
            FROM assignments a
            JOIN teachers t ON a.teacher_id = t.id
            JOIN subjects s ON a.subject_id = s.id
            LIMIT 5
        ");
        
        if ($sample_data && $sample_data->num_rows > 0) {
            echo "<p>Sample data (up to 5 records):</p>";
            echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
            echo "<tr style='background-color: #f0f0f0;'><th>ID</th><th>Teacher</th><th>Subject</th><th>Days</th><th>Time</th><th>Location</th></tr>";
            
            while ($row = $sample_data->fetch_assoc()) {
                // Format preferred_day as JSON for display
                $days = "NULL";
                if ($row['preferred_day'] !== null) {
                    try {
                        $days_array = json_decode($row['preferred_day'], true);
                        if ($days_array) {
                            $days = implode(', ', $days_array);
                        } else {
                            $days = $row['preferred_day']; // Raw value if not valid JSON
                        }
                    } catch (Exception $e) {
                        $days = $row['preferred_day']; // Raw value if JSON decode fails
                    }
                }
                
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['teacher_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['subject_code'] . ' - ' . $row['subject_name']) . "</td>";
                echo "<td>" . htmlspecialchars($days) . "</td>";
                echo "<td>" . htmlspecialchars(date('h:i A', strtotime($row['time_start'])) . ' - ' . date('h:i A', strtotime($row['time_end']))) . "</td>";
                echo "<td>" . htmlspecialchars($row['location']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p>No assignment data found in the table.</p>";
    }
} else {
    echo "<div style='color: red; font-weight: bold;'>Failed to check sample data: " . $conn->error . "</div>";
}

// Test insert functionality
outputSection("Test Insert Functionality");
echo "<p>This section will test if insertions to the assignments table work properly.</p>";
echo "<form method='post' action=''>";
echo "<input type='hidden' name='test_insert' value='1'>";
echo "<button type='submit' style='padding: 8px 15px; background-color: #4285f4; color: white; border: none; border-radius: 4px; cursor: pointer;'>Test Insert</button>";
echo "</form>";

if (isset($_POST['test_insert'])) {
    // Get a teacher and subject ID that doesn't already have an assignment
    $get_available = $conn->query("
        SELECT t.id as teacher_id, s.id as subject_id
        FROM teachers t, subjects s
        WHERE t.status = 'Active'
        AND NOT EXISTS (
            SELECT 1 FROM assignments a 
            WHERE a.teacher_id = t.id AND a.subject_id = s.id
        )
        LIMIT 1
    ");
    
    if ($get_available && $get_available->num_rows > 0) {
        $available = $get_available->fetch_assoc();
        $teacher_id = $available['teacher_id'];
        $subject_id = $available['subject_id'];
        
        $days = ["Monday", "Wednesday", "Friday"];
        $preferred_days = json_encode($days);
        $time_start = "08:00:00";
        $time_end = "10:00:00";
        $location = "Test Room";
        $month_from = date('Y-m-d');
        $month_to = date('Y-m-d', strtotime('+6 months'));
        
        $stmt = $conn->prepare("INSERT INTO assignments (
            teacher_id, subject_id, month_from, month_to,
            preferred_day, time_start, time_end, location
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            echo "<div style='color: red; margin-top: 10px;'>Prepare statement failed: " . $conn->error . "</div>";
        } else {
            $stmt->bind_param("iissssss", 
                $teacher_id, 
                $subject_id, 
                $month_from, 
                $month_to, 
                $preferred_days, 
                $time_start, 
                $time_end, 
                $location
            );
            
            if ($stmt->execute()) {
                echo "<div style='color: green; margin-top: 10px; font-weight: bold;'>Test insertion successful! ID: " . $conn->insert_id . "</div>";
                echo "<p>Used Teacher ID: {$teacher_id}, Subject ID: {$subject_id}</p>";
                echo "<p>Preferred days: " . implode(", ", $days) . " (JSON: {$preferred_days})</p>";
            } else {
                echo "<div style='color: red; margin-top: 10px;'>Insertion failed: " . $stmt->error . "</div>";
                echo "<p>SQL error code: " . $stmt->errno . "</p>";
            }
        }
    } else {
        echo "<div style='color: red; margin-top: 10px;'>No available teacher/subject combination found for testing.</div>";
    }
}

echo "<p style='margin-top: 30px;'><a href='admin/assignments/index.php' style='padding: 8px 15px; background-color: #4285f4; color: white; text-decoration: none; border-radius: 4px;'>Return to Assignments Page</a></p>";
?> 