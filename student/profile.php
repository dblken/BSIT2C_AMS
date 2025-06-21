<?php
// Start session
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: ../login.php');
    exit;
}

// Include database connection
require_once '../config/database.php';

// Initialize variables
$student_id = $_SESSION['student_id'];
$success_message = '';
$error_message = '';

// Check for session success message
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get student details
$stmt = $conn->prepare("SELECT s.*, u.username 
                        FROM students s 
                        JOIN users u ON s.user_id = u.id 
                        WHERE s.id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update personal information
        $first_name = trim($_POST['first_name']);
        $middle_name = trim($_POST['middle_name']);
        $last_name = trim($_POST['last_name']);
        $gender = $_POST['gender'];
        $date_of_birth = $_POST['date_of_birth'];
        $phone = trim($_POST['phone_number']);
        $address = trim($_POST['address']);
        $email = trim($_POST['email'] ?? '');
        
        // Validate input - Initialize errors array
        $errors = [];
        
        // Validate first name
        if (empty($first_name)) {
            $errors[] = "First name is required.";
        } else if (!preg_match('/^[a-zA-Z\s]+$/', $first_name) || strlen($first_name) < 3) {
            $errors[] = "First name must be at least 3 characters and contain only letters.";
        }
        
        // Validate middle name if provided
        if (!empty($middle_name) && !preg_match('/^[a-zA-Z\s]+$/', $middle_name)) {
            $errors[] = "Middle name must contain only letters.";
        }
        
        // Validate last name
        if (empty($last_name)) {
            $errors[] = "Last name is required.";
        } else if (!preg_match('/^[a-zA-Z\s]+$/', $last_name) || strlen($last_name) < 3) {
            $errors[] = "Last name must be at least 3 characters and contain only letters.";
        }
        
        // Validate gender
        if (empty($gender)) {
            $errors[] = "Gender is required.";
        }
        
        // Validate email if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        }
        
        // Validate phone if provided
        if (!empty($phone) && !preg_match('/^09\d{9}$/', $phone)) {
            $errors[] = "Phone number must be in format 09XXXXXXXXX.";
        }
        
        // Validate date of birth if provided
        if (empty($date_of_birth)) {
            $errors[] = "Date of birth is required.";
        } else {
            $dob = new DateTime($date_of_birth);
            $today = new DateTime();
            $min_date = clone $today;
            $min_date->modify('-15 years');
            
            if ($dob > $today) {
                $errors[] = "Date of birth cannot be in the future.";
            } else if ($dob > $min_date) {
                $errors[] = "You must be at least 15 years old.";
            }
        }
        
        // If there are errors, display them
        if (!empty($errors)) {
            $error_message = implode("<br>", $errors);
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update student record
                $update_student = $conn->prepare("UPDATE students SET 
                    first_name = ?, 
                    middle_name = ?, 
                    last_name = ?, 
                    gender = ?, 
                    date_of_birth = ?, 
                    phone = ?, 
                    address = ? 
                    WHERE id = ?");
                $update_student->bind_param("sssssssi", 
                    $first_name, 
                    $middle_name, 
                    $last_name, 
                    $gender, 
                    $date_of_birth, 
                    $phone, 
                    $address, 
                    $student_id
                );
                $update_student->execute();
                
                // Update session variables with new name
                $_SESSION['name'] = $first_name . ' ' . $last_name;
                
                // Try to update user email if the email field exists
                if (!empty($email)) {
                    try {
                        // Check if email column exists in users table
                        $email_check_query = "SHOW COLUMNS FROM users LIKE 'email'";
                        $email_check_result = $conn->query($email_check_query);
                        
                        if ($email_check_result->num_rows > 0) {
                            // Email column exists, proceed with email update
                            
                            // Check if email is already in use by another user
                            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                            if ($check_email) {
                                $check_email->bind_param("si", $email, $student['user_id']);
                                $check_email->execute();
                                $email_result = $check_email->get_result();
                                
                                if ($email_result->num_rows > 0) {
                                    $error_message = "Email is already in use by another user.";
                                    $conn->rollback();
                                    goto end_processing;
                                } else {
                                    // Update email
                                    $update_user = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                                    if ($update_user) {
                                        $update_user->bind_param("si", $email, $student['user_id']);
                                        $update_user->execute();
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // If there's an error with email update, just continue
                        // This handles cases where the email column doesn't exist
                    }
                }
                
                // Handle profile picture upload
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    $filename = $_FILES['profile_picture']['name'];
                    $filesize = $_FILES['profile_picture']['size'];
                    $filetype = $_FILES['profile_picture']['type'];
                    $tmp_name = $_FILES['profile_picture']['tmp_name'];
                    
                    // Debug
                    error_log("Profile Picture Upload Debug: Name=$filename, Size=$filesize, Type=$filetype, TmpName=$tmp_name");
                    error_log("File exists: " . (file_exists($tmp_name) ? 'Yes - ' . filesize($tmp_name) . ' bytes' : 'No'));
                    
                    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    // Validate file extension
                    if (in_array($file_extension, $allowed)) {
                        // Validate file size (max 5MB)
                        if ($filesize <= 5242880) {
                            // Generate unique filename
                            $new_filename = "student_" . $student_id . "_" . uniqid() . "." . $file_extension;
                            $upload_dir = "../uploads/profile/";
                            
                            // Create directory if it doesn't exist
                            if (!file_exists($upload_dir)) {
                                error_log("Upload directory doesn't exist: $upload_dir");
                                if (mkdir($upload_dir, 0777, true)) {
                                    error_log("Created directory: $upload_dir");
                                    // Set directory permissions
                                    chmod($upload_dir, 0777);
                                    error_log("Set permissions on directory: $upload_dir");
                                } else {
                                    error_log("Failed to create directory: $upload_dir");
                                    $error_message = "Failed to create upload directory. Please contact administrator.";
                                    $conn->rollback();
                                    goto end_processing;
                                }
                            } else {
                                error_log("Upload directory exists: $upload_dir");
                                // Make sure directory is writable
                                if (!is_writable($upload_dir)) {
                                    error_log("Upload directory is not writable: $upload_dir");
                                    chmod($upload_dir, 0777);
                                    error_log("Attempted to set permissions on directory: $upload_dir");
                                }
                            }
                            
                            // Validate upload path is writable
                            $upload_path = $upload_dir . $new_filename;
                            $upload_dir_writable = is_writable($upload_dir);
                            error_log("Upload directory writable: " . ($upload_dir_writable ? 'Yes' : 'No'));
                            error_log("Attempting to upload file to: $upload_path");
                            
                            // Try to create an empty file to test writability
                            $test_file = $upload_dir . 'test_' . time() . '.txt';
                            $test_writability = @file_put_contents($test_file, 'test');
                            if ($test_writability !== false) {
                                error_log("Successfully wrote test file: $test_file");
                                @unlink($test_file);  // Clean up
                            } else {
                                error_log("Failed to write test file: $test_file");
                            }
                            
                            // First copy the file instead of moving it (as a test)
                            $copy_result = @copy($tmp_name, $upload_path);
                            if ($copy_result) {
                                error_log("Successfully copied file using copy() function");
                                
                                // Set file permissions
                                chmod($upload_path, 0644);
                                error_log("Set permissions on file: $upload_path");
                                
                                // Additional debug for file
                                $file_exists = file_exists($upload_path);
                                $file_size = $file_exists ? filesize($upload_path) : 'N/A';
                                error_log("Uploaded file exists: " . ($file_exists ? 'Yes' : 'No'));
                                error_log("Uploaded file size: " . $file_size);
                                
                                // Verify image is valid by trying to get image size
                                $image_info = @getimagesize($upload_path);
                                if (!$image_info && $file_exists) {
                                    error_log("Uploaded file is not a valid image. Removing it.");
                                    @unlink($upload_path);
                                    $error_message = "The uploaded file is not a valid image. Please try again with a different file.";
                                    $conn->rollback();
                                    goto end_processing;
                                }
                                
                                // Delete old profile picture if exists
                                if (!empty($student['profile_picture'])) {
                                    $old_file = $upload_dir . $student['profile_picture'];
                                    if (file_exists($old_file)) {
                                        if (@unlink($old_file)) {
                                            error_log("Old profile picture deleted: " . $old_file);
                                        } else {
                                            error_log("Failed to delete old profile picture: " . $old_file);
                                        }
                                    } else {
                                        error_log("Old profile file not found to delete: " . $old_file);
                                    }
                                }
                                
                                // Update profile picture in database
                                $update_picture = $conn->prepare("UPDATE students SET profile_picture = ? WHERE id = ?");
                                if ($update_picture) {
                                    $update_picture->bind_param("si", $new_filename, $student_id);
                                    if ($update_picture->execute()) {
                                        error_log("Database updated with new profile picture: $new_filename");
                                        $success_message = "Profile updated successfully! Profile picture has been updated.";
                                    } else {
                                        error_log("Failed to update database with new profile picture: " . $conn->error);
                                        $success_message = "Profile updated but profile picture could not be updated in database.";
                                    }
                                } else {
                                    error_log("Failed to prepare profile picture update query: " . $conn->error);
                                    $success_message = "Profile updated but profile picture could not be updated.";
                                }
                            } else {
                                // If copy fails, try move_uploaded_file
                                error_log("Copy failed, trying move_uploaded_file instead");
                                
                                if (move_uploaded_file($tmp_name, $upload_path)) {
                                    error_log("File uploaded successfully to: $upload_path");
                                    // Set file permissions
                                    chmod($upload_path, 0644);
                                    error_log("Set permissions on file: $upload_path");
                                    
                                    // Additional debug for file
                                    $file_exists = file_exists($upload_path);
                                    $file_size = $file_exists ? filesize($upload_path) : 'N/A';
                                    error_log("Uploaded file exists: " . ($file_exists ? 'Yes' : 'No'));
                                    error_log("Uploaded file size: " . $file_size);
                                    
                                    // Verify image is valid by trying to get image size
                                    $image_info = @getimagesize($upload_path);
                                    if (!$image_info && $file_exists) {
                                        error_log("Uploaded file is not a valid image. Removing it.");
                                        @unlink($upload_path);
                                        $error_message = "The uploaded file is not a valid image. Please try again with a different file.";
                                        $conn->rollback();
                                        goto end_processing;
                                    }
                                    
                                    // Delete old profile picture if exists
                                    if (!empty($student['profile_picture'])) {
                                        $old_file = $upload_dir . $student['profile_picture'];
                                        if (file_exists($old_file)) {
                                            if (@unlink($old_file)) {
                                                error_log("Old profile picture deleted: " . $old_file);
                                            } else {
                                                error_log("Failed to delete old profile picture: " . $old_file);
                                            }
                                        } else {
                                            error_log("Old profile file not found to delete: " . $old_file);
                                        }
                                    }
                                    
                                    // Update profile picture in database
                                    $update_picture = $conn->prepare("UPDATE students SET profile_picture = ? WHERE id = ?");
                                    if ($update_picture) {
                                        $update_picture->bind_param("si", $new_filename, $student_id);
                                        if ($update_picture->execute()) {
                                            error_log("Database updated with new profile picture: $new_filename");
                                            $success_message = "Profile updated successfully! Profile picture has been updated.";
                                        } else {
                                            error_log("Failed to update database with new profile picture: " . $conn->error);
                                            $success_message = "Profile updated but profile picture could not be updated in database.";
                                        }
                                    } else {
                                        error_log("Failed to prepare profile picture update query: " . $conn->error);
                                        $success_message = "Profile updated but profile picture could not be updated.";
                                    }
                                } else {
                                    error_log("Failed to move uploaded file from $tmp_name to $upload_path");
                                    
                                    // Last resort - try file_put_contents
                                    $file_contents = @file_get_contents($tmp_name);
                                    if ($file_contents !== false && @file_put_contents($upload_path, $file_contents)) {
                                        error_log("Used file_put_contents as last resort - SUCCESS");
                                        
                                        // Set file permissions
                                        chmod($upload_path, 0644);
                                        
                                        // Update profile picture in database
                                        $update_picture = $conn->prepare("UPDATE students SET profile_picture = ? WHERE id = ?");
                                        if ($update_picture) {
                                            $update_picture->bind_param("si", $new_filename, $student_id);
                                            if ($update_picture->execute()) {
                                                error_log("Database updated with new profile picture: $new_filename");
                                                $success_message = "Profile updated successfully! Profile picture has been updated.";
                                            } else {
                                                error_log("Failed to update database with new profile picture: " . $conn->error);
                                                $success_message = "Profile updated but profile picture could not be updated in database.";
                                            }
                                        } else {
                                            error_log("Failed to prepare profile picture update query: " . $conn->error);
                                            $success_message = "Profile updated but profile picture could not be updated.";
                                        }
                                    } else {
                                        error_log("All methods of file upload failed");
                                        $error_message = "Failed to upload profile picture. Please try again.";
                                    }
                                    
                                    // Try to understand why the upload failed
                                    $upload_errors = [
                                        UPLOAD_ERR_OK => "No error.",
                                        UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
                                        UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.",
                                        UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded.",
                                        UPLOAD_ERR_NO_FILE => "No file was uploaded.",
                                        UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
                                        UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
                                        UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
                                    ];
                                    
                                    $error_code = $_FILES['profile_picture']['error'];
                                    error_log("Upload error code: " . $error_code . " - " . ($upload_errors[$error_code] ?? "Unknown error"));
                                }
                            }
                        } else {
                            error_log("File size too large: $filesize bytes");
                            $error_message = "File size exceeds the maximum limit of 5MB.";
                            $conn->rollback();
                            goto end_processing;
                        }
                    } else {
                        error_log("Invalid file extension: $file_extension");
                        $error_message = "Invalid file format. Allowed formats: JPG, JPEG, PNG, GIF.";
                        $conn->rollback();
                        goto end_processing;
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                // Set success message
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
                    $success_message = "Profile updated successfully! " . (isset($update_picture) ? "Profile picture has been updated." : "");
                } else {
                    $success_message = "Profile updated successfully!";
                }
                
                // Store success message in session
                $_SESSION['success_message'] = $success_message;
                
                // Redirect to refresh page and all session values
                header("Location: profile.php");
                exit;
                
                // Refresh student data (this won't execute due to redirect)
                $stmt->execute();
                $student = $stmt->get_result()->fetch_assoc();
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error updating profile: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate password
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New password and confirm password do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } elseif (preg_match('/[^a-zA-Z0-9\!\@\#\$\%\^\&\*\(\)\_\-\+\=\.]/', $new_password)) {
            $error_message = "Password contains invalid special characters. Please use only letters, numbers, and basic special characters (!@#$%^&*()_-+=.)";
        } else {
            try {
                // Verify current password
                $check_password = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $check_password->bind_param("i", $student['user_id']);
                $check_password->execute();
                $pwd_result = $check_password->get_result()->fetch_assoc();
                
                $password_verified = false;
                
                // Try different hashing methods
                if (password_verify($current_password, $pwd_result['password'])) {
                    $password_verified = true;
                } elseif (md5($current_password) === $pwd_result['password']) {
                    $password_verified = true;
                } elseif ($current_password === $pwd_result['password']) { // Plain text fallback
                    $password_verified = true;
                }
                
                if ($password_verified) {
                    // Choose the appropriate hashing method
                    // Try password_hash first, but fall back to md5 if the database doesn't support long hashes
                    try {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        // Test password length against database column
                        $test_length_query = "SHOW COLUMNS FROM users WHERE Field = 'password'";
                        $test_result = $conn->query($test_length_query);
                        $password_column = $test_result->fetch_assoc();
                        
                        // Extract the column type and length
                        if (preg_match('/varchar\((\d+)\)/', $password_column['Type'], $matches)) {
                            $column_length = (int)$matches[1];
                            
                            // If the hashed password is too long, use md5 instead
                            if (strlen($hashed_password) > $column_length) {
                                // Fall back to md5 with a salt
                                $salt = bin2hex(random_bytes(8));
                                $hashed_password = md5($new_password . $salt);
                            }
                        }
                        
                        // Update password
                        $update_password = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $update_password->bind_param("si", $hashed_password, $student['user_id']);
                        
                        if ($update_password->execute()) {
                            $success_message = "Password changed successfully!";
                        } else {
                            $error_message = "Error changing password.";
                        }
                    } catch (Exception $e) {
                        // If something goes wrong with the advanced method, fall back to md5
                        try {
                            $hashed_password = md5($new_password);
                            $update_password = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $update_password->bind_param("si", $hashed_password, $student['user_id']);
                            
                            if ($update_password->execute()) {
                                $success_message = "Password changed successfully!";
                            } else {
                                $error_message = "Error changing password.";
                            }
                        } catch (Exception $e) {
                            $error_message = "Error changing password: " . $e->getMessage();
                        }
                    }
                } else {
                    $error_message = "Current password is incorrect.";
                }
            } catch (Exception $e) {
                $error_message = "Error verifying password: " . $e->getMessage();
            }
        }
    }
}

end_processing:

// Get profile picture path with better error handling and checks
$fallback_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($student['first_name'] . '+' . $student['last_name']) . '&background=021F3F&color=ffffff&size=200';

// Define paths
$default_profile_path = "../uploads/profile/default.png"; // Server file path
$default_profile_url = "../get_image.php?file=default.png"; // Using image server script

$profile_pic = $fallback_avatar; // Default to fallback avatar

// Debug info for troubleshooting
error_log("Profile Picture Debug: DB value = " . ($student['profile_picture'] ?? 'NULL'));

if (!empty($student['profile_picture'])) {
    $profile_path = "../uploads/profile/" . $student['profile_picture']; // Server file path
    $profile_url = "../get_image.php?file=" . urlencode($student['profile_picture']); // Using image server script
    
    // Check if file exists and is valid
    if (file_exists($profile_path) && is_readable($profile_path) && filesize($profile_path) > 0) {
        $profile_pic = $profile_url; // Use image server script
        error_log("Found valid profile picture at: $profile_path (Size: " . filesize($profile_path) . " bytes)");
        error_log("Using profile URL: $profile_url");
    } else {
        error_log("Custom profile picture not valid: $profile_path");
        
        // Try default image
        if (file_exists($default_profile_path) && is_readable($default_profile_path) && filesize($default_profile_path) > 0) {
            $profile_pic = $default_profile_url;
            error_log("Using default profile image: $default_profile_path");
            error_log("Default profile URL: $default_profile_url");
        } else {
            error_log("Default profile image not valid, using fallback avatar");
        }
    }
} else {
    // Try default image
    if (file_exists($default_profile_path) && is_readable($default_profile_path) && filesize($default_profile_path) > 0) {
        $profile_pic = $default_profile_url;
        error_log("No profile picture set, using default: $default_profile_path");
        error_log("Default profile URL: $default_profile_url");
    } else {
        error_log("Default profile image not valid, using fallback avatar");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - BSIT 2C AMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background: #f5f7fa; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px; border: none; }
        .card-header { border-bottom: 1px solid #eee; font-weight: 600; }
        .profile-header { position: relative; }
        .profile-cover { height: 150px; background: linear-gradient(135deg, #021F3F 0%, #021F3F 100%); border-radius: 10px 10px 0 0; }
        .profile-avatar { position: absolute; bottom: -50px; left: 50px; }
        .profile-avatar img { width: 100px; height: 100px; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.1); object-fit: cover; }
        .profile-info { padding-left: 170px; padding-top: 20px; }
        .profile-actions { position: absolute; bottom: 20px; right: 20px; }
        .nav-pills .nav-link { color: #6c757d; border-radius: 0; padding: 10px 15px; }
        .nav-pills .nav-link.active { background-color: transparent; color: #021F3F; border-bottom: 2px solid #021F3F; }
        
        /* Form validation styles */
        .is-invalid {
            border-color: #dc3545 !important;
            padding-right: calc(1.5em + .75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(.375em + .1875rem) center;
            background-size: calc(.75em + .375rem) calc(.75em + .375rem);
        }
        
        .is-valid {
            border-color: #198754 !important;
            padding-right: calc(1.5em + .75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(.375em + .1875rem) center;
            background-size: calc(.75em + .375rem) calc(.75em + .375rem);
        }
        
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-4">
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i> <?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="profile-header">
                <div class="profile-cover"></div>
                <div class="profile-avatar">
                    <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile Picture" 
                         onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($student['first_name'] . '+' . $student['last_name']) ?>&background=021F3F&color=ffffff&size=200'; console.log('Profile image failed to load, using fallback');">
                </div>
                <div class="profile-info">
                    <h4><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h4>
                    <p class="text-muted mb-0">
                        BSIT - 2C
                    </p>
                </div>
            </div>
            <div class="card-body pt-5 pb-3">
                <ul class="nav nav-pills mb-4" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab" aria-controls="info" aria-selected="true">
                            <i class="bi bi-person me-2"></i>Personal Information
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab" aria-controls="account" aria-selected="false">
                            <i class="bi bi-shield-lock me-2"></i>Account Settings
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="profileTabsContent">
                    <!-- Personal Information Tab -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
                        <form method="POST" action="" class="needs-validation" novalidate enctype="multipart/form-data">
                            <div class="row p-3 px-md-4">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label required-field">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($student['first_name'] ?? '') ?>" 
                                           pattern="^[A-Za-z\s]{3,}$" 
                                           title="First name must be at least 3 characters and contain only letters"
                                           required>
                                    <div class="invalid-feedback" style="display: none;">
                                        First name must be at least 3 characters and contain only letters
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                           value="<?= htmlspecialchars($student['middle_name'] ?? '') ?>"
                                           pattern="^[A-Za-z\s]*$"
                                           title="Middle name can only contain letters">
                                    <div class="invalid-feedback" style="display: none;">
                                        Middle name can only contain letters
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label required-field">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($student['last_name'] ?? '') ?>" 
                                           pattern="^[A-Za-z\s]{3,}$" 
                                           title="Last name must be at least 3 characters and contain only letters"
                                           required>
                                    <div class="invalid-feedback" style="display: none;">
                                        Last name must be at least 3 characters and contain only letters
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label required-field">Gender</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="" disabled <?= empty($student['gender']) ? 'selected' : '' ?>>Select Gender</option>
                                        <option value="Male" <?= ($student['gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($student['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= ($student['gender'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                    <div class="invalid-feedback" style="display: none;">
                                        Please select a gender
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label required-field">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?= htmlspecialchars($student['date_of_birth'] ?? '') ?>" 
                                           max="<?= date('Y-m-d', strtotime('-15 years')) ?>"
                                           required>
                                    <div class="invalid-feedback" style="display: none;">
                                        Date of birth is required and you must be at least 15 years old
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone_number" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="phone_number" name="phone_number" 
                                           value="<?= htmlspecialchars($student['phone'] ?? '') ?>"
                                           pattern="^09[0-9]{9}$"
                                           title="Phone number must be in format 09XXXXXXXXX">
                                    <div class="invalid-feedback" style="display: none;">
                                        Phone number must be in format 09XXXXXXXXX
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label required-field">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($student['email'] ?? '') ?>"
                                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                                           required>
                                    <div class="invalid-feedback" style="display: none;">
                                        Please enter a valid email address
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($student['address'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" name="update_profile" class="btn btn-primary" style="background-color: #021F3F; border-color: #021F3F;">
                                    <i class="bi bi-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Account Settings Tab -->
                    <div class="tab-pane fade" id="account" role="tabpanel" aria-labelledby="account-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-3">Account Information</h5>
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($student['username']) ?>" readonly>
                                    <div class="form-text">You cannot change your username.</div>
                                </div>
                                
                                <h5 class="mb-3 mt-4">Change Password</h5>
                                <form method="POST" name="password_form">
                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" name="new_password" minlength="6" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" name="confirm_password" minlength="6" required>
                                    </div>
                                    <div class="text-end">
                                        <button type="submit" name="change_password" class="btn btn-primary" style="background-color: #021F3F; border-color: #021F3F;">
                                            <i class="bi bi-shield-lock me-2"></i>Change Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5 class="mb-3">Account Security Tips</h5>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item bg-transparent border-0 ps-0">
                                                <i class="bi bi-shield-check text-primary me-2"></i>
                                                Use a strong password that includes uppercase letters, lowercase letters, numbers, and special characters.
                                            </li>
                                            <li class="list-group-item bg-transparent border-0 ps-0">
                                                <i class="bi bi-shield-check text-primary me-2"></i>
                                                Do not share your password with others.
                                            </li>
                                            <li class="list-group-item bg-transparent border-0 ps-0">
                                                <i class="bi bi-shield-check text-primary me-2"></i>
                                                Change your password regularly.
                                            </li>
                                            <li class="list-group-item bg-transparent border-0 ps-0">
                                                <i class="bi bi-shield-check text-primary me-2"></i>
                                                Always log out when using a public computer.
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('../includes/footer.php'); ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            
            // Fetch all the forms we want to apply custom validation to
            var forms = document.querySelectorAll('.needs-validation');
            
            // Function to show/hide feedback
            function toggleFeedback(element, isValid) {
                const feedback = element.nextElementSibling;
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.style.display = isValid ? 'none' : 'block';
                }
            }
            
            // Date fields
            const dobField = document.getElementById('date_of_birth');
            
            // Name fields
            const firstNameField = document.getElementById('first_name');
            const lastNameField = document.getElementById('last_name');
            const middleNameField = document.getElementById('middle_name');
            
            // Phone field
            const phoneField = document.getElementById('phone_number');
            
            // Add date of birth validation
            if (dobField) {
                const validateDob = function() {
                    const dob = new Date(dobField.value);
                    const today = new Date();
                    const minDate = new Date();
                    minDate.setFullYear(today.getFullYear() - 15);
                    
                    if (!dobField.value) {
                        dobField.setCustomValidity('');
                        dobField.classList.remove('is-invalid', 'is-valid');
                        toggleFeedback(dobField, true);
                    } else if (dob > today) {
                        dobField.setCustomValidity('Date of birth cannot be in the future');
                        dobField.classList.add('is-invalid');
                        dobField.classList.remove('is-valid');
                        toggleFeedback(dobField, false);
                    } else if (dob > minDate) {
                        dobField.setCustomValidity('You must be at least 15 years old');
                        dobField.classList.add('is-invalid');
                        dobField.classList.remove('is-valid');
                        toggleFeedback(dobField, false);
                    } else {
                        dobField.setCustomValidity('');
                        dobField.classList.remove('is-invalid');
                        dobField.classList.add('is-valid');
                        toggleFeedback(dobField, true);
                    }
                };
                
                dobField.addEventListener('input', validateDob);
                dobField.addEventListener('change', validateDob);
                
                // Validate on page load
                if (dobField.value) {
                    validateDob();
                }
            }
            
            // Validate names contain only letters and spaces
            const nameRegex = /^[A-Za-z\s]{3,}$/;
            const anyLettersRegex = /^[A-Za-z\s]*$/;
            
            // First name validation
            if (firstNameField) {
                const validateFirstName = function() {
                    const value = firstNameField.value;
                    if (!value) {
                        firstNameField.setCustomValidity('');
                        firstNameField.classList.remove('is-invalid', 'is-valid');
                        toggleFeedback(firstNameField, true);
                    } else if (!nameRegex.test(value)) {
                        if (value.length < 3) {
                            firstNameField.setCustomValidity('First name must be at least 3 characters');
                        } else if (!/^[A-Za-z\s]*$/.test(value)) {
                            firstNameField.setCustomValidity('First name can only contain letters and spaces');
                        } else {
                            firstNameField.setCustomValidity('First name must be valid');
                        }
                        firstNameField.classList.add('is-invalid');
                        firstNameField.classList.remove('is-valid');
                        toggleFeedback(firstNameField, false);
                    } else {
                        firstNameField.setCustomValidity('');
                        firstNameField.classList.remove('is-invalid');
                        firstNameField.classList.add('is-valid');
                        toggleFeedback(firstNameField, true);
                    }
                };
                
                firstNameField.addEventListener('input', validateFirstName);
                // Validate on page load
                if (firstNameField.value) {
                    validateFirstName();
                }
            }
            
            // Last name validation
            if (lastNameField) {
                const validateLastName = function() {
                    const value = lastNameField.value;
                    if (!value) {
                        lastNameField.setCustomValidity('');
                        lastNameField.classList.remove('is-invalid', 'is-valid');
                        toggleFeedback(lastNameField, true);
                    } else if (!nameRegex.test(value)) {
                        if (value.length < 3) {
                            lastNameField.setCustomValidity('Last name must be at least 3 characters');
                        } else if (!/^[A-Za-z\s]*$/.test(value)) {
                            lastNameField.setCustomValidity('Last name can only contain letters and spaces');
                        } else {
                            lastNameField.setCustomValidity('Last name must be valid');
                        }
                        lastNameField.classList.add('is-invalid');
                        lastNameField.classList.remove('is-valid');
                        toggleFeedback(lastNameField, false);
                    } else {
                        lastNameField.setCustomValidity('');
                        lastNameField.classList.remove('is-invalid');
                        lastNameField.classList.add('is-valid');
                        toggleFeedback(lastNameField, true);
                    }
                };
                
                lastNameField.addEventListener('input', validateLastName);
                // Validate on page load
                if (lastNameField.value) {
                    validateLastName();
                }
            }
            
            // Middle name validation
            if (middleNameField) {
                const validateMiddleName = function() {
                    const value = middleNameField.value;
                    if (!value) {
                        middleNameField.setCustomValidity('');
                        middleNameField.classList.remove('is-invalid', 'is-valid');
                        toggleFeedback(middleNameField, true);
                    } else if (!anyLettersRegex.test(value)) {
                        middleNameField.setCustomValidity('Middle name can only contain letters and spaces');
                        middleNameField.classList.add('is-invalid');
                        middleNameField.classList.remove('is-valid');
                        toggleFeedback(middleNameField, false);
                    } else {
                        middleNameField.setCustomValidity('');
                        middleNameField.classList.remove('is-invalid');
                        middleNameField.classList.add('is-valid');
                        toggleFeedback(middleNameField, true);
                    }
                };
                
                middleNameField.addEventListener('input', validateMiddleName);
                // Validate on page load
                if (middleNameField.value) {
                    validateMiddleName();
                }
            }
            
            // Phone validation
            if (phoneField) {
                const validatePhone = function() {
                    const value = phoneField.value;
                    if (!value) {
                        phoneField.setCustomValidity('');
                        phoneField.classList.remove('is-invalid', 'is-valid');
                        toggleFeedback(phoneField, true);
                    } else if (!/^09\d{9}$/.test(value)) {
                        phoneField.setCustomValidity('Phone number must be in format 09XXXXXXXXX');
                        phoneField.classList.add('is-invalid');
                        phoneField.classList.remove('is-valid');
                        toggleFeedback(phoneField, false);
                    } else {
                        phoneField.setCustomValidity('');
                        phoneField.classList.remove('is-invalid');
                        phoneField.classList.add('is-valid');
                        toggleFeedback(phoneField, true);
                    }
                };
                
                phoneField.addEventListener('input', validatePhone);
                // Validate on page load
                if (phoneField.value) {
                    validatePhone();
                }
            }
            
            // Email validation
            const emailField = document.getElementById('email');
            if (emailField) {
                const validateEmail = function() {
                    const value = emailField.value;
                    if (!value) {
                        emailField.setCustomValidity('');
                        emailField.classList.remove('is-invalid', 'is-valid');
                        toggleFeedback(emailField, true);
                    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                        emailField.setCustomValidity('Please enter a valid email address');
                        emailField.classList.add('is-invalid');
                        emailField.classList.remove('is-valid');
                        toggleFeedback(emailField, false);
                    } else {
                        emailField.setCustomValidity('');
                        emailField.classList.remove('is-invalid');
                        emailField.classList.add('is-valid');
                        toggleFeedback(emailField, true);
                    }
                };
                
                emailField.addEventListener('input', validateEmail);
                // Validate on page load
                if (emailField.value) {
                    validateEmail();
                }
            }
            
            // Gender validation
            const genderField = document.getElementById('gender');
            if (genderField) {
                const validateGender = function() {
                    if (!genderField.value) {
                        genderField.setCustomValidity('Please select a gender');
                        genderField.classList.add('is-invalid');
                        genderField.classList.remove('is-valid');
                        toggleFeedback(genderField, false);
                    } else {
                        genderField.setCustomValidity('');
                        genderField.classList.remove('is-invalid');
                        genderField.classList.add('is-valid');
                        toggleFeedback(genderField, true);
                    }
                };
                
                genderField.addEventListener('change', validateGender);
                // Validate on page load
                if (genderField.value) {
                    validateGender();
                }
            }
            
            // Loop over them and prevent submission
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
        
        // Profile picture preview
        const profileUpload = document.getElementById('profile-upload');
        if (profileUpload) {
            profileUpload.addEventListener('change', function() {
                const preview = document.getElementById('profile-preview');
                if (preview && this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    </script>
</body>
</html> 