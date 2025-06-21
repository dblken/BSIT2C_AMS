<?php
// Set the content type to plain text for easier debugging
header('Content-Type: text/plain');

// Database connection
$db_host = "localhost";
$db_user = "root";
$db_password = "1234";
$db_name = "attendance_system_1";

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to database successfully.\n\n";

// Get all columns in students table
$columns_result = $conn->query("SHOW COLUMNS FROM students");
echo "Current columns in students table:\n";
while ($column = $columns_result->fetch_assoc()) {
    echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
}
echo "\n";

// Check if phone_number column already exists in students table
$result = $conn->query("SHOW COLUMNS FROM students LIKE 'phone_number'");
if ($result->num_rows > 0) {
    echo "The phone_number column already exists in the students table.\n";
} else {
    // Add phone_number column to students table
    $sql = "ALTER TABLE students ADD COLUMN phone_number VARCHAR(20) DEFAULT NULL";
    
    if ($conn->query($sql) === TRUE) {
        echo "Successfully added phone_number column to students table.\n";
    } else {
        echo "Error adding phone_number column: " . $conn->error . "\n";
    }
}

// Check if address column already exists in students table
$result = $conn->query("SHOW COLUMNS FROM students LIKE 'address'");
if ($result->num_rows > 0) {
    echo "The address column already exists in the students table.\n";
} else {
    // Add address column to students table
    $sql = "ALTER TABLE students ADD COLUMN address VARCHAR(255) DEFAULT NULL";
    
    if ($conn->query($sql) === TRUE) {
        echo "Successfully added address column to students table.\n";
    } else {
        echo "Error adding address column: " . $conn->error . "\n";
    }
}

// Check if email column already exists in students table
$result = $conn->query("SHOW COLUMNS FROM students LIKE 'email'");
if ($result->num_rows > 0) {
    echo "The email column already exists in the students table.\n";
} else {
    // Add email column to students table
    $sql = "ALTER TABLE students ADD COLUMN email VARCHAR(100) DEFAULT NULL";
    
    if ($conn->query($sql) === TRUE) {
        echo "Successfully added email column to students table.\n";
    } else {
        echo "Error adding email column: " . $conn->error . "\n";
    }
}

// Check if date_of_birth column already exists in students table
$result = $conn->query("SHOW COLUMNS FROM students LIKE 'date_of_birth'");
if ($result->num_rows > 0) {
    echo "The date_of_birth column already exists in the students table.\n";
} else {
    // Add date_of_birth column to students table
    $sql = "ALTER TABLE students ADD COLUMN date_of_birth DATE DEFAULT NULL";
    
    if ($conn->query($sql) === TRUE) {
        echo "Successfully added date_of_birth column to students table.\n";
    } else {
        echo "Error adding date_of_birth column: " . $conn->error . "\n";
    }
}

// Check if gender column already exists in students table
$result = $conn->query("SHOW COLUMNS FROM students LIKE 'gender'");
if ($result->num_rows > 0) {
    echo "The gender column already exists in the students table.\n";
} else {
    // Add gender column to students table
    $sql = "ALTER TABLE students ADD COLUMN gender ENUM('Male', 'Female', 'Other') DEFAULT NULL";
    
    if ($conn->query($sql) === TRUE) {
        echo "Successfully added gender column to students table.\n";
    } else {
        echo "Error adding gender column: " . $conn->error . "\n";
    }
}

// Get all columns in students table after modifications
$columns_result = $conn->query("SHOW COLUMNS FROM students");
echo "\nFinal columns in students table:\n";
while ($column = $columns_result->fetch_assoc()) {
    echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
}

$conn->close();
echo "\nDone!";
?> 