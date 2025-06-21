<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/database.php';

echo "<h2>Subjects Table Structure</h2>";
$result = mysqli_query($conn, "DESCRIBE subjects");
if (!$result) {
    echo "Error: " . mysqli_error($conn);
} else {
    echo "<pre>";
    while($row = mysqli_fetch_assoc($result)) {
        print_r($row);
    }
    echo "</pre>";
}
?> 