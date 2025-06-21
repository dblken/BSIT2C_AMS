<?php
// Database configuration
require_once 'config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get table structure
$columns = [];
$tableExists = false;

// Check if assignments table exists
$table_check = $conn->query("SHOW TABLES LIKE 'assignments'");
if ($table_check->num_rows > 0) {
    $tableExists = true;
    $structure = $conn->query("DESCRIBE assignments");
    while ($column = $structure->fetch_assoc()) {
        $columns[$column['Field']] = $column;
    }
}

// Check for foreign key constraints
$fk_constraints = [];
if ($tableExists) {
    $fk_query = "
        SELECT 
            CONSTRAINT_NAME as constraint_name,
            COLUMN_NAME as column_name,
            REFERENCED_TABLE_NAME as ref_table,
            REFERENCED_COLUMN_NAME as ref_column
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'assignments'
        AND REFERENCED_TABLE_NAME IS NOT NULL;
    ";
    
    $fk_result = $conn->query($fk_query);
    if ($fk_result && $fk_result->num_rows > 0) {
        while ($fk = $fk_result->fetch_assoc()) {
            $fk_constraints[] = $fk;
        }
    }
}

// Get sample data
$sample_data = [];
if ($tableExists) {
    $data_query = $conn->query("SELECT * FROM assignments LIMIT 3");
    if ($data_query && $data_query->num_rows > 0) {
        while ($row = $data_query->fetch_assoc()) {
            $sample_data[] = $row;
        }
    }
}

// Close the database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments Table Structure Check</title>
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
    <h1>Assignments Table Structure</h1>
    
    <?php if (!$tableExists): ?>
    <p class="warning">Error: The assignments table does not exist in the database!</p>
    <?php else: ?>
    
    <h2>Table Columns</h2>
    <table>
        <tr>
            <th>Field</th>
            <th>Type</th>
            <th>Null</th>
            <th>Key</th>
            <th>Default</th>
            <th>Extra</th>
        </tr>
        <?php foreach ($columns as $name => $column): ?>
        <tr>
            <td><?php echo htmlspecialchars($column['Field']); ?></td>
            <td><?php echo htmlspecialchars($column['Type']); ?></td>
            <td><?php echo htmlspecialchars($column['Null']); ?></td>
            <td><?php echo htmlspecialchars($column['Key']); ?></td>
            <td><?php echo htmlspecialchars($column['Default'] ?? 'NULL'); ?></td>
            <td><?php echo htmlspecialchars($column['Extra']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <?php
    // Check for necessary columns
    $required_columns = ['id', 'teacher_id', 'subject_id', 'preferred_day', 'time_start', 'time_end'];
    $missing_columns = array_diff($required_columns, array_keys($columns));
    
    if (!empty($missing_columns)): ?>
    <p class="warning">Warning: The following required columns are missing: <?php echo implode(', ', $missing_columns); ?></p>
    <?php else: ?>
    <p class="success">All required columns are present in the assignments table.</p>
    <?php endif; ?>
    
    <h2>Foreign Key Constraints</h2>
    <?php if (!empty($fk_constraints)): ?>
    <table>
        <tr>
            <th>Constraint Name</th>
            <th>Column</th>
            <th>References</th>
        </tr>
        <?php foreach ($fk_constraints as $fk): ?>
        <tr>
            <td><?php echo htmlspecialchars($fk['constraint_name']); ?></td>
            <td><?php echo htmlspecialchars($fk['column_name']); ?></td>
            <td><?php echo htmlspecialchars($fk['ref_table'] . '(' . $fk['ref_column'] . ')'); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>No foreign key constraints found for the assignments table.</p>
    <?php endif; ?>
    
    <h2>Sample Data (First 3 rows)</h2>
    <?php if (!empty($sample_data)): ?>
    <table>
        <tr>
            <?php foreach (array_keys($columns) as $column): ?>
            <th><?php echo htmlspecialchars($column); ?></th>
            <?php endforeach; ?>
        </tr>
        
        <?php foreach ($sample_data as $row): ?>
        <tr>
            <?php foreach (array_keys($columns) as $column): ?>
            <td>
                <?php 
                $value = isset($row[$column]) ? $row[$column] : 'NULL';
                
                // Decode JSON for preferred_day if it exists
                if ($column === 'preferred_day' && $value !== 'NULL') {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $value = implode(', ', $decoded);
                    }
                }
                
                echo htmlspecialchars($value);
                ?>
            </td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>No data found in the assignments table.</p>
    <?php endif; ?>
    
    <?php endif; // End of table exists check ?>
</body>
</html> 