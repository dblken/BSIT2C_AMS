<?php
// Simple upload test script

// Process upload if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $upload_dir = "uploads/profile/";
    $file_info = $_FILES['test_file'];
    
    echo "<h3>Upload Results:</h3>";
    echo "<pre>";
    echo "File Information:\n";
    print_r($file_info);
    echo "\n\nServer Information:\n";
    echo "Max upload size: " . ini_get('upload_max_filesize') . "\n";
    echo "Max post size: " . ini_get('post_max_size') . "\n";
    echo "File uploads enabled: " . (ini_get('file_uploads') ? 'Yes' : 'No') . "\n";
    echo "Temporary directory: " . ini_get('upload_tmp_dir') . "\n";
    echo "</pre>";
    
    // Check if upload directory exists and is writable
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            echo "<div style='color: red;'>Failed to create upload directory: $upload_dir</div>";
        } else {
            echo "<div style='color: green;'>Created upload directory: $upload_dir</div>";
            chmod($upload_dir, 0777);
        }
    }
    
    echo "<div>Upload directory exists: " . (file_exists($upload_dir) ? 'Yes' : 'No') . "</div>";
    echo "<div>Upload directory is writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "</div>";
    
    // Process the upload
    if ($file_info['error'] == 0) {
        $target_file = $upload_dir . basename($file_info['name']);
        
        if (move_uploaded_file($file_info['tmp_name'], $target_file)) {
            echo "<div style='color: green;'>File uploaded successfully to: $target_file</div>";
            echo "<div>File size: " . filesize($target_file) . " bytes</div>";
            echo "<div>File exists: " . (file_exists($target_file) ? 'Yes' : 'No') . "</div>";
            echo "<div>File readable: " . (is_readable($target_file) ? 'Yes' : 'No') . "</div>";
            
            // If it's an image, try to verify it
            $image_info = @getimagesize($target_file);
            if ($image_info) {
                echo "<div style='color: green;'>Valid image detected. Dimensions: " . $image_info[0] . "x" . $image_info[1] . "</div>";
                echo "<img src='$target_file' style='max-width: 300px; max-height: 300px;' alt='Uploaded image'>";
            } else {
                echo "<div style='color: red;'>Not a valid image file.</div>";
            }
        } else {
            echo "<div style='color: red;'>Failed to move uploaded file from {$file_info['tmp_name']} to $target_file</div>";
        }
    } else {
        $error_codes = [
            UPLOAD_ERR_OK => "No error.",
            UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
            UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.",
            UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded.",
            UPLOAD_ERR_NO_FILE => "No file was uploaded.",
            UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
        ];
        
        echo "<div style='color: red;'>Upload error: " . ($error_codes[$file_info['error']] ?? "Unknown error") . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1 { color: #333; }
        form { margin: 20px 0; padding: 20px; border: 1px solid #ccc; border-radius: 5px; }
        input[type="file"] { margin: 10px 0; }
        input[type="submit"] { background: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background: #45a049; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow: auto; }
    </style>
</head>
<body>
    <h1>File Upload Test</h1>
    
    <form action="" method="POST" enctype="multipart/form-data">
        <h2>Test File Upload</h2>
        <p>Use this form to test if file uploads are working correctly.</p>
        
        <input type="file" name="test_file" required>
        <br>
        <input type="submit" value="Upload Test File">
    </form>
    
    <h2>PHP Server Information</h2>
    <pre>
PHP Version: <?= phpversion() ?>

Upload Settings:
- max_file_uploads: <?= ini_get('max_file_uploads') ?>
- upload_max_filesize: <?= ini_get('upload_max_filesize') ?>
- post_max_size: <?= ini_get('post_max_size') ?>
- file_uploads enabled: <?= ini_get('file_uploads') ? 'Yes' : 'No' ?>
- upload_tmp_dir: <?= ini_get('upload_tmp_dir') ?: 'System default' ?>

Directory Information:
- Current script: <?= __FILE__ ?>
- Upload directory: <?= realpath('uploads/profile') ?: 'Not created yet' ?>
- Upload directory exists: <?= file_exists('uploads/profile') ? 'Yes' : 'No' ?>
<?php if (file_exists('uploads/profile')): ?>
- Upload directory writable: <?= is_writable('uploads/profile') ? 'Yes' : 'No' ?>
- Upload directory permissions: <?= substr(sprintf('%o', fileperms('uploads/profile')), -4) ?>
<?php endif; ?>
    </pre>
    
    <h2>Debug Information</h2>
    <p>Check these existing files in the upload directory:</p>
    <ul>
    <?php
    if (file_exists('uploads/profile') && is_readable('uploads/profile')) {
        $files = scandir('uploads/profile');
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $file_path = 'uploads/profile/' . $file;
                $file_size = file_exists($file_path) ? filesize($file_path) : 'N/A';
                echo "<li>" . htmlspecialchars($file) . " (" . $file_size . " bytes)</li>";
            }
        }
    } else {
        echo "<li>Cannot read upload directory or it does not exist.</li>";
    }
    ?>
    </ul>
</body>
</html> 