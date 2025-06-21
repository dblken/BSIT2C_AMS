<?php
/**
 * Default Image Generator
 * 
 * This script creates a default profile image if one doesn't exist.
 * It generates a simple blue circle with a white silhouette.
 */

// Set the content type to plain text for easier debugging
header('Content-Type: text/plain');

// Specify the directory for profile pictures
$uploadDir = "uploads/profile/";

// Create the uploads directory if it doesn't exist
if (!file_exists($uploadDir)) {
    echo "Creating upload directory...\n";
    if (!mkdir($uploadDir, 0777, true)) {
        echo "Error: Failed to create upload directory";
        exit;
    }
    echo "Upload directory created successfully\n";
}

// Define the path for the default profile image
$defaultImagePath = $uploadDir . "default.jpg";

// Check if the default image already exists
if (file_exists($defaultImagePath)) {
    echo "Default profile image already exists at: $defaultImagePath\n";
    echo "Size: " . filesize($defaultImagePath) . " bytes\n";
    exit;
}

// Create a default profile image using a base64 encoded image
echo "Creating default profile image...\n";

// This is a simple blue avatar with a white silhouette (base64 encoded JPEG)
$defaultImageBase64 = "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";

// Decode base64 data to binary
$imageData = base64_decode($defaultImageBase64);

// Save the image to the default image path
if (file_put_contents($defaultImagePath, $imageData)) {
    echo "Default profile image created successfully at: $defaultImagePath\n";
    echo "Size: " . filesize($defaultImagePath) . " bytes\n";
    
    // Make sure the image is readable by the web server
    chmod($defaultImagePath, 0644);
    echo "Image permissions set to 0644\n";
} else {
    echo "Error: Failed to save default profile image\n";
}

echo "\nDone!";

// Show the image
echo '<h2>Default Profile Image:</h2>';
echo '<img src="' . $defaultImagePath . '" style="max-width: 300px; border: 1px solid #ddd; padding: 10px;">';
echo '<p>If you can see the image above, it should be working correctly.</p>';

// Show base64 encoded version as backup
echo '<h2>Base64 Encoded Version:</h2>';
$imageData = base64_encode(file_get_contents($defaultImagePath));
$mimeType = mime_content_type($defaultImagePath) ?: 'image/jpeg';
echo '<img src="data:' . $mimeType . ';base64,' . $imageData . '" style="max-width: 300px; border: 1px solid #ddd; padding: 10px;">';
echo '<p>If you can see the image above but not the one before, there may be issues with file permissions or paths.</p>';

// Add links to other tools
echo '<h2>Tools:</h2>';
echo '<ul>';
echo '<li><a href="upload_test.php">Test File Uploads</a></li>';
echo '<li><a href="view_image.php?image=default.jpg">View Image Details</a></li>';
echo '<li><a href="get_image.php?file=default.jpg">Direct Image Access</a></li>';
echo '<li><a href="student/profile.php">Go to Student Profile</a></li>';
echo '</ul>';
?> 