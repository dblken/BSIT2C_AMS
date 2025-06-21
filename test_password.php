<?php
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password: $password<br>";
echo "Generated Hash: $hash<br>";

// Test verification
if (password_verify($password, $hash)) {
    echo "Password verification successful!<br>";
} else {
    echo "Password verification failed!<br>";
}

// Test against stored hash
$stored_hash = '$2y$10$8s1K0QDZ9PKXaF6AD3FE3.PXfHaZwZqOHqvQHq8THt50.wlrqm2Uy';
if (password_verify($password, $stored_hash)) {
    echo "Stored hash verification successful!<br>";
} else {
    echo "Stored hash verification failed!<br>";
}
?> 