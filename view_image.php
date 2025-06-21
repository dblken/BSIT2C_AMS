<?php
/**
 * Direct Image View 
 * 
 * This script provides a direct view of the profile image
 * for troubleshooting purposes.
 */

// Validate parameters
$image = isset($_GET['image']) ? $_GET['image'] : 'default.png';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'view';

// Sanitize the filename
$filename = basename($image);

// Path to the image
$filepath = "uploads/profile/" . $filename;

// Check if the file exists
$file_exists = file_exists($filepath);
$file_readable = $file_exists && is_readable($filepath);
$file_size = $file_exists ? filesize($filepath) : 0;
$is_valid_image = false;

// Check if it's a valid image
if ($file_exists && $file_readable) {
    $image_info = @getimagesize($filepath);
    $is_valid_image = $image_info !== false;
    $mime_type = $image_info['mime'] ?? 'unknown';
} else {
    $mime_type = 'unknown';
}

// If mode is direct, output the image directly
if ($mode === 'direct' && $file_exists && $is_valid_image) {
    header("Content-Type: $mime_type");
    header("Content-Length: $file_size");
    readfile($filepath);
    exit;
}

// Otherwise show a diagnostic page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Viewer</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1, h2 { color: #4e73df; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
        .success { color: green; }
        .error { color: red; }
        .image-container { text-align: center; margin: 20px 0; padding: 20px; border: 1px solid #ddd; }
        img { max-width: 100%; max-height: 400px; }
    </style>
</head>
<body>
    <h1>Image Viewer: <?= htmlspecialchars($filename) ?></h1>
    
    <table>
        <tr>
            <th>Property</th>
            <th>Value</th>
        </tr>
        <tr>
            <td>Filepath</td>
            <td><?= htmlspecialchars($filepath) ?></td>
        </tr>
        <tr>
            <td>File Exists</td>
            <td><?= $file_exists ? '<span class="success">Yes</span>' : '<span class="error">No</span>' ?></td>
        </tr>
        <tr>
            <td>File Readable</td>
            <td><?= $file_readable ? '<span class="success">Yes</span>' : '<span class="error">No</span>' ?></td>
        </tr>
        <tr>
            <td>File Size</td>
            <td><?= $file_exists ? "$file_size bytes" : 'N/A' ?></td>
        </tr>
        <tr>
            <td>Valid Image</td>
            <td><?= $is_valid_image ? '<span class="success">Yes</span>' : '<span class="error">No</span>' ?></td>
        </tr>
        <tr>
            <td>MIME Type</td>
            <td><?= htmlspecialchars($mime_type) ?></td>
        </tr>
    </table>
    
    <h2>Image Display</h2>
    
    <?php if ($file_exists && $is_valid_image): ?>
        <div class="image-container">
            <h3>1. Regular Image Tag</h3>
            <img src="<?= htmlspecialchars($filepath) ?>" alt="<?= htmlspecialchars($filename) ?>">
        </div>
        
        <div class="image-container">
            <h3>2. Direct Mode (separate request)</h3>
            <img src="view_image.php?image=<?= htmlspecialchars(urlencode($filename)) ?>&mode=direct" alt="<?= htmlspecialchars($filename) ?>">
        </div>
        
        <div class="image-container">
            <h3>3. Base64 Encoded</h3>
            <?php
            $img_data = base64_encode(file_get_contents($filepath));
            ?>
            <img src="data:<?= htmlspecialchars($mime_type) ?>;base64,<?= $img_data ?>" alt="<?= htmlspecialchars($filename) ?>">
        </div>
        
        <div class="image-container">
            <h3>4. Using get_image.php</h3>
            <img src="get_image.php?file=<?= htmlspecialchars(urlencode($filename)) ?>" alt="<?= htmlspecialchars($filename) ?>">
        </div>
    <?php else: ?>
        <div class="image-container error">
            <p>Cannot display image. File does not exist, is not readable, or is not a valid image.</p>
        </div>
    <?php endif; ?>
    
    <h2>Actions</h2>
    <p>
        <a href="student/profile.php">Return to Profile</a> | 
        <a href="upload_test.php">Upload Test Tool</a> | 
        <a href="view_image.php?image=default.png">View Default Image</a>
    </p>
</body>
</html> 