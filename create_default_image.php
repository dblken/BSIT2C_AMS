<?php
/**
 * Default Image Generator
 * 
 * This script creates a default profile image if one doesn't exist.
 * It generates a simple blue circle with a white silhouette.
 */

// Define the directory and file path
$directory = 'uploads/profile';
$imagePath = "$directory/default.png";

// Check if directory exists, create it if not
if (!file_exists($directory)) {
    if (!mkdir($directory, 0777, true)) {
        die("Failed to create directory: $directory");
    }
    echo "Created directory: $directory<br>";
}

// Only create the image if it doesn't exist or is invalid
if (!file_exists($imagePath) || filesize($imagePath) < 100 || !@getimagesize($imagePath)) {
    // Create a 400x400 image with a blue background
    $image = imagecreatetruecolor(400, 400);
    
    // Define colors
    $blue = imagecolorallocate($image, 78, 115, 223); // #4e73df
    $white = imagecolorallocate($image, 255, 255, 255);
    $transparent = imagecolorallocate($image, 0, 0, 0);
    
    // Make the background transparent
    imagecolortransparent($image, $transparent);
    
    // Fill with light blue
    imagefill($image, 0, 0, $blue);
    
    // Draw a circle for the head
    imagefilledellipse($image, 200, 150, 160, 160, $white);
    
    // Draw a rounded rectangle for the body
    imagefilledrectangle($image, 120, 230, 280, 400, $white);
    
    // Save the image
    if (imagepng($image, $imagePath)) {
        echo "Default profile image created successfully: $imagePath<br>";
        echo "File size: " . filesize($imagePath) . " bytes<br>";
        
        // Set proper permissions
        chmod($imagePath, 0644);
        echo "Set permissions to 0644<br>";
    } else {
        echo "Failed to create default profile image.<br>";
    }
    
    // Free memory
    imagedestroy($image);
} else {
    echo "Default profile image already exists: $imagePath<br>";
    echo "File size: " . filesize($imagePath) . " bytes<br>";
}

// Show the image
echo '<h2>Default Profile Image:</h2>';
echo '<img src="' . $imagePath . '" style="max-width: 300px; border: 1px solid #ddd; padding: 10px;">';
echo '<p>If you can see the image above, it should be working correctly.</p>';

// Show base64 encoded version as backup
echo '<h2>Base64 Encoded Version:</h2>';
$imageData = base64_encode(file_get_contents($imagePath));
$mimeType = mime_content_type($imagePath) ?: 'image/png';
echo '<img src="data:' . $mimeType . ';base64,' . $imageData . '" style="max-width: 300px; border: 1px solid #ddd; padding: 10px;">';
echo '<p>If you can see the image above but not the one before, there may be issues with file permissions or paths.</p>';

// Add links to other tools
echo '<h2>Tools:</h2>';
echo '<ul>';
echo '<li><a href="upload_test.php">Test File Uploads</a></li>';
echo '<li><a href="view_image.php?image=default.png">View Image Details</a></li>';
echo '<li><a href="get_image.php?file=default.png">Direct Image Access</a></li>';
echo '<li><a href="student/profile.php">Go to Student Profile</a></li>';
echo '</ul>';
?> 