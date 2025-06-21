<?php
// A simple test script to verify image display

$image_path = "uploads/profile/default.png";

// Check if file exists and is accessible
$file_exists = file_exists($image_path);
$file_size = $file_exists ? filesize($image_path) : 'N/A';
$file_readable = $file_exists ? is_readable($image_path) : false;
$is_image = $file_exists ? (bool)@getimagesize($image_path) : false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Display Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1 { color: #4e73df; }
        .image-container { border: 1px solid #ccc; padding: 20px; margin: 20px 0; text-align: center; }
        img { max-width: 300px; max-height: 300px; border: 1px solid #eee; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow: auto; }
    </style>
</head>
<body>
    <h1>Image Display Test</h1>
    
    <h2>Image Information</h2>
    <pre>
Path: <?= htmlspecialchars($image_path) ?>
Exists: <?= $file_exists ? 'Yes' : 'No' ?>
Size: <?= $file_size ?> bytes
Readable: <?= $file_readable ? 'Yes' : 'No' ?>
Valid Image: <?= $is_image ? 'Yes' : 'No' ?>
    </pre>
    
    <h2>Default Image Display Test</h2>
    <div class="image-container">
        <h3>Direct Image Tag</h3>
        <img src="<?= htmlspecialchars($image_path) ?>" alt="Default Image">
        <p>
            <?php if ($file_exists && $is_image): ?>
                <span class="success">✓ Image should display above if browser can access it</span>
            <?php else: ?>
                <span class="error">✗ Image file issue detected: <?= !$file_exists ? 'File does not exist' : (!$is_image ? 'Not a valid image' : 'Unknown issue') ?></span>
            <?php endif; ?>
        </p>
    </div>
    
    <h2>Image with Error Handling</h2>
    <div class="image-container">
        <img src="<?= htmlspecialchars($image_path) ?>" alt="Default Image with Error Handling" 
             onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=Test+User&background=4e73df&color=ffffff&size=200'; this.nextElementSibling.style.display='block';">
        <p style="display: none;" class="error">Image failed to load - showing fallback</p>
    </div>
    
    <h2>Other Tests</h2>
    <div class="image-container">
        <h3>Base64 Embedded Image Test</h3>
        <?php if ($file_exists && $file_readable): 
            $image_data = base64_encode(file_get_contents($image_path));
            $mime_type = $is_image ? getimagesize($image_path)['mime'] : 'image/png';
        ?>
            <img src="data:<?= $mime_type ?>;base64,<?= $image_data ?>" alt="Base64 Embedded Image">
            <p class="success">This uses a base64 embedded image to bypass any URL issues</p>
        <?php else: ?>
            <p class="error">Cannot create base64 image: file not accessible</p>
        <?php endif; ?>
    </div>
    
    <h2>External Image Test</h2>
    <div class="image-container">
        <img src="https://ui-avatars.com/api/?name=Test+User&background=4e73df&color=ffffff&size=200" alt="External Image">
        <p>This tests if your browser can display external images from ui-avatars.com</p>
    </div>
</body>
</html> 