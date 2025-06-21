<?php
// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Get an existing assignment ID (if any)
$query = "SELECT a.id, s.subject_code, s.subject_name, 
          CONCAT(t.first_name, ' ', t.last_name) as teacher_name
          FROM assignments a
          JOIN subjects s ON a.subject_id = s.id
          JOIN teachers t ON a.teacher_id = t.id
          LIMIT 1";
$result = $conn->query($query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['assignment_id'];
    
    echo "<h1>Testing Assignment Deletion</h1>";
    echo "<p>Attempting to delete assignment ID: {$id}...</p>";
    
    // Prepare and execute request to delete_assignment.php
    $ch = curl_init('http://localhost/BSIT2C_AMS/admin/assignments/delete_assignment.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['id' => $id]);
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    echo "<pre>";
    echo "HTTP Status: " . $info['http_code'] . "\n\n";
    echo "Response: \n";
    print_r($response);
    echo "</pre>";
    
    if ($response) {
        try {
            $json = json_decode($response, true);
            if ($json) {
                echo "<div style='padding: 10px; margin: 10px 0; " . 
                    ($json['success'] ? "background-color: #d4edda;" : "background-color: #f8d7da;") . 
                    "'>";
                
                echo "<strong>" . ($json['success'] ? "Success:" : "Error:") . "</strong> ";
                echo $json['message'] ?? 'No message provided';
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<div style='padding: 10px; margin: 10px 0; background-color: #f8d7da;'>";
            echo "<strong>Error parsing response:</strong> " . $e->getMessage();
            echo "</div>";
        }
    }
    
    echo "<p><a href='test_delete_assignment.php'>Test another deletion</a></p>";
    echo "<p><a href='admin/assignments/index.php'>Return to assignments page</a></p>";
} else {
    // Display the form
    echo "<h1>Test Assignment Deletion</h1>";
    
    if ($result && $result->num_rows > 0) {
        echo "<form method='post'>";
        echo "<p>Select an assignment to delete:</p>";
        echo "<select name='assignment_id' style='padding: 5px; width: 100%; max-width: 400px;'>";
        
        $result->data_seek(0); // Reset pointer to start
        while ($row = $result->fetch_assoc()) {
            echo "<option value='" . $row['id'] . "'>" . 
                 htmlspecialchars($row['subject_code'] . " - " . $row['subject_name'] . 
                 " (Teacher: " . $row['teacher_name'] . ")") . 
                 "</option>";
        }
        
        echo "</select>";
        echo "<p style='color: red; font-weight: bold;'>Warning: This will permanently delete the selected assignment!</p>";
        echo "<button type='submit' style='padding: 10px 15px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;'>Delete Assignment</button>";
        echo "</form>";
    } else {
        echo "<p>No assignments found in the database. <a href='test_add_assignment.php'>Add an assignment first</a>.</p>";
    }
    
    echo "<p><a href='admin/assignments/index.php' style='display: inline-block; margin-top: 20px; padding: 8px 15px; background-color: #4285f4; color: white; text-decoration: none; border-radius: 4px;'>Return to Assignments Page</a></p>";
}
?> 