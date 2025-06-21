<?php
/**
 * Profile Images Gallery
 * 
 * This script displays all profile pictures in the uploads directory
 * for debugging purposes.
 */

// Define the directory
$directory = 'uploads/profile';

// Check if directory exists
$dir_exists = file_exists($directory);
$dir_readable = $dir_exists && is_readable($directory);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Images Gallery</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
        h1, h2 { color: #4e73df; }
        .image-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .image-card { border: 1px solid #ddd; border-radius: 5px; padding: 15px; text-align: center; }
        .image-card img { max-width: 100%; max-height: 200px; margin-bottom: 10px; border: 1px solid #eee; }
        .image-info { font-size: 0.9em; text-align: left; margin-top: 10px; }
        .success { color: green; }
        .error { color: red; }
        .tools { margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Profile Images Gallery</h1>
    
    <div class="tools">
        <h2>Tools</h2>
        <p>
            <a href="create_default_image.php" class="btn">Create Default Image</a> | 
            <a href="upload_test.php" class="btn">Test File Uploads</a> | 
            <a href="view_image.php?image=default.png" class="btn">View Default Image</a> | 
            <a href="student/profile.php" class="btn">Go to Profile</a>
        </p>
    </div>
    
    <h2>Directory Information</h2>
    <p>
        <strong>Directory Path:</strong> <?= htmlspecialchars(realpath($directory) ?: $directory) ?><br>
        <strong>Directory Exists:</strong> <span class="<?= $dir_exists ? 'success' : 'error' ?>"><?= $dir_exists ? 'Yes' : 'No' ?></span><br>
        <strong>Directory Readable:</strong> <span class="<?= $dir_readable ? 'success' : 'error' ?>"><?= $dir_readable ? 'Yes' : 'No' ?></span><br>
        <?php if ($dir_exists): ?>
            <strong>Directory Permissions:</strong> <?= substr(sprintf('%o', fileperms($directory)), -4) ?>
        <?php endif; ?>
    </p>
    
    <?php if ($dir_exists && $dir_readable): ?>
        <h2>All Profile Images</h2>
        <div class="image-grid">
            <?php
            $files = scandir($directory);
            $has_images = false;
            
            foreach ($files as $file) {
                if ($file == '.' || $file == '..') continue;
                
                $filepath = "$directory/$file";
                $filesize = file_exists($filepath) ? filesize($filepath) : 0;
                $is_image = @getimagesize($filepath) ? true : false;
                
                if ($is_image):
                    $has_images = true;
                    $mime_type = mime_content_type($filepath) ?: 'unknown';
                    $dimensions = @getimagesize($filepath);
                    $width = $dimensions[0] ?? 'Unknown';
                    $height = $dimensions[1] ?? 'Unknown';
            ?>
                <div class="image-card">
                    <h3><?= htmlspecialchars($file) ?></h3>
                    <img src="<?= htmlspecialchars($filepath) ?>" alt="<?= htmlspecialchars($file) ?>" 
                         onerror="this.onerror=null; this.src='data:image/png;base64,<?= base64_encode(file_get_contents($filepath)) ?>'; this.nextElementSibling.style.display='block';">
                    <div class="error" style="display: none;">Direct URL failed, showing base64 version</div>
                    
                    <div class="image-info">
                        <strong>File Size:</strong> <?= $filesize ?> bytes<br>
                        <strong>MIME Type:</strong> <?= htmlspecialchars($mime_type) ?><br>
                        <strong>Dimensions:</strong> <?= $width ?>x<?= $height ?><br>
                        <strong>Last Modified:</strong> <?= date('Y-m-d H:i:s', filemtime($filepath)) ?>
                    </div>
                    
                    <div style="margin-top: 10px;">
                        <a href="view_image.php?image=<?= urlencode($file) ?>" target="_blank">View Details</a> | 
                        <a href="get_image.php?file=<?= urlencode($file) ?>" target="_blank">Direct Access</a>
                    </div>
                </div>
            <?php
                endif;
            }
            
            if (!$has_images):
            ?>
                <div class="error">No image files found in the directory.</div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="error">
            <?php if (!$dir_exists): ?>
                The directory does not exist. Please create it first.
            <?php elseif (!$dir_readable): ?>
                The directory exists but is not readable. Please check permissions.
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="tools" style="margin-top: 30px;">
        <h2>Create Test Image</h2>
        <form action="create_default_image.php" method="get">
            <button type="submit">Generate Default Profile Image</button>
        </form>
    </div>
</body>
</html> 