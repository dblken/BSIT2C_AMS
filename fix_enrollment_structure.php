<?php
// Set content type to plain text for easier debugging
header('Content-Type: text/plain');

echo "FIXING ENROLLMENT TABLE STRUCTURE\n";
echo "===============================\n\n";

// Include database connection
require_once 'config/database.php';

// Check if enrollments table exists
$check_enrollments_table = mysqli_query($conn, "SHOW TABLES LIKE 'enrollments'");
if (mysqli_num_rows($check_enrollments_table) == 0) {
    echo "Creating enrollments table...\n";
    
    $create_table_sql = "CREATE TABLE enrollments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        subject_id INT NOT NULL,
        assignment_id INT NOT NULL,
        status ENUM('Enrolled', 'Dropped', 'Completed') DEFAULT 'Enrolled',
        enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
        FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE
    )";
    
    if (mysqli_query($conn, $create_table_sql)) {
        echo "✅ Enrollments table created successfully!\n\n";
    } else {
        echo "❌ Error creating enrollments table: " . mysqli_error($conn) . "\n\n";
    }
} else {
    echo "Enrollments table already exists.\n";
    
    // Check and add subject_id column if it doesn't exist
    $check_subject_column = mysqli_query($conn, "SHOW COLUMNS FROM enrollments LIKE 'subject_id'");
    if (mysqli_num_rows($check_subject_column) == 0) {
        echo "Adding subject_id column to enrollments table...\n";
        if (mysqli_query($conn, "ALTER TABLE enrollments ADD COLUMN subject_id INT NOT NULL AFTER student_id")) {
            echo "✅ Added subject_id column\n";
        } else {
            echo "❌ Error adding subject_id column: " . mysqli_error($conn) . "\n";
        }
    }
    
    // Check and add assignment_id column if it doesn't exist
    $check_assignment_column = mysqli_query($conn, "SHOW COLUMNS FROM enrollments LIKE 'assignment_id'");
    if (mysqli_num_rows($check_assignment_column) == 0) {
        echo "Adding assignment_id column to enrollments table...\n";
        if (mysqli_query($conn, "ALTER TABLE enrollments ADD COLUMN assignment_id INT NOT NULL AFTER subject_id")) {
            echo "✅ Added assignment_id column\n";
        } else {
            echo "❌ Error adding assignment_id column: " . mysqli_error($conn) . "\n";
        }
    }
    
    // Check and add created_at column if it doesn't exist
    $check_created_column = mysqli_query($conn, "SHOW COLUMNS FROM enrollments LIKE 'created_at'");
    if (mysqli_num_rows($check_created_column) == 0) {
        echo "Adding created_at column to enrollments table...\n";
        if (mysqli_query($conn, "ALTER TABLE enrollments ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP")) {
            echo "✅ Added created_at column\n";
        } else {
            echo "❌ Error adding created_at column: " . mysqli_error($conn) . "\n";
        }
    }
}

// Check final structure
echo "\nFinal enrollments table structure:\n";
$result = mysqli_query($conn, "DESCRIBE enrollments");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

echo "\nDone.\n";
?> 