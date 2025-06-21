<?php
/**
 * Image Server Script
 * 
 * This script serves images from the uploads directory safely.
 * Usage: get_image.php?file=filename.jpg
 */

// Validate the file parameter
if (!isset($_GET['file']) || empty($_GET['file'])) {
    header("HTTP/1.0 400 Bad Request");
    exit("No file specified");
}

// Sanitize the filename
$filename = basename($_GET['file']);

// Make sure the filename only contains allowed characters
if (!preg_match('/^[a-zA-Z0-9_.-]+\.(jpg|jpeg|png|gif)$/i', $filename)) {
    header("HTTP/1.0 400 Bad Request");
    exit("Invalid filename");
}

// Define the path to the image
$filepath = "uploads/profile/" . $filename;

// Check if the file exists
if (!file_exists($filepath)) {
    header("HTTP/1.0 404 Not Found");
    exit("File not found: " . $filepath);
}

// Get file info
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

// Set the content type header
header("Content-Type: " . $mime_type);
header("Content-Length: " . filesize($filepath));

// Disable caching for development (remove in production)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Output the file
readfile($filepath);
exit;
?> 