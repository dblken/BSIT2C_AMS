<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/database.php';

echo "<h2>All Tables</h2>";
$result = mysqli_query($conn, "SHOW TABLES");
if (!$result) {
    echo "Error: " . mysqli_error($conn);
} else {
    echo "<pre>";
    while($row = mysqli_fetch_row($result)) {
        echo $row[0] . "\n";
    }
    echo "</pre>";
}
?> 