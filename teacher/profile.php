<?php
session_start();
require_once '../config/database.php';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: login.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'];
$success_message = '';
$error_message = '';

// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/profile_images/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Check if profile_picture column exists, if not add it
try {
    $check_column = "SHOW COLUMNS FROM teachers LIKE 'profile_picture'";
    $column_result = $conn->query($check_column);
    
    if ($column_result->num_rows == 0) {
        // Column doesn't exist, add it
        $add_column = "ALTER TABLE teachers ADD COLUMN profile_picture VARCHAR(255) NULL";
        $conn->query($add_column);
    }
    
    // Check if phone column exists, if not add it (may have been called phone_number in some instances)
    $check_phone_column = "SHOW COLUMNS FROM teachers LIKE 'phone'";
    $phone_column_result = $conn->query($check_phone_column);
    
    if ($phone_column_result->num_rows == 0) {
        // Check if phone_number exists
        $check_phone_number = "SHOW COLUMNS FROM teachers LIKE 'phone_number'";
        $phone_number_result = $conn->query($check_phone_number);
        
        if ($phone_number_result->num_rows > 0) {
            // Rename phone_number to phone
            $rename_column = "ALTER TABLE teachers CHANGE phone_number phone VARCHAR(20)";
            $conn->query($rename_column);
        } else {
            // Neither column exists, add phone column
            $add_column = "ALTER TABLE teachers ADD COLUMN phone VARCHAR(20) NULL";
            $conn->query($add_column);
        }
    }
} catch (Exception $e) {
    // Log error but continue
    error_log("Error checking/adding columns: " . $e->getMessage());
}

// Get teacher information
$query = "SELECT t.*, u.username
          FROM teachers t
          JOIN users u ON t.user_id = u.id
          WHERE t.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $error_message = "Teacher not found";
    $teacher = null;
} else {
    $teacher = $result->fetch_assoc();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate inputs
        $errors = [];
        
        // Validate name fields (at least 3 chars, letters only)
        if (empty($_POST['first_name']) || strlen($_POST['first_name']) < 3 || !preg_match('/^[A-Za-z\s]+$/', $_POST['first_name'])) {
            $errors[] = "First name must be at least 3 characters and contain only letters";
        }
        
        if (empty($_POST['last_name']) || strlen($_POST['last_name']) < 3 || !preg_match('/^[A-Za-z\s]+$/', $_POST['last_name'])) {
            $errors[] = "Last name must be at least 3 characters and contain only letters";
        }
        
        if (!empty($_POST['middle_name']) && !preg_match('/^[A-Za-z\s]+$/', $_POST['middle_name'])) {
            $errors[] = "Middle name must contain only letters";
        }
        
        // Validate email
        if (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address";
        }
        
        // Validate phone (if provided)
        if (!empty($_POST['phone']) && !preg_match('/^09[0-9]{9}$/', $_POST['phone'])) {
            $errors[] = "Phone number must be in format 09XXXXXXXXX";
        }
        
        // Validate password if provided
        if (!empty($_POST['new_password'])) {
            if (strlen($_POST['new_password']) < 8) {
                $errors[] = "Password must be at least 8 characters";
            }
            
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $_POST['new_password'])) {
                $errors[] = "Password must contain at least one uppercase letter, one lowercase letter, and one number";
            }
            
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                $errors[] = "Password and confirmation do not match";
            }
        }
        
        // If there are errors, throw exception
        if (!empty($errors)) {
            throw new Exception(implode("<br>", $errors));
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        // Handle profile picture upload
        $profile_picture = $teacher['profile_picture']; // Keep existing image by default
        
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            // Validate file type and size
            if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
                throw new Exception("Only JPG, PNG and GIF images are allowed");
            }
            
            if ($_FILES['profile_image']['size'] > $max_size) {
                throw new Exception("Image size should be less than 2MB");
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'teacher_' . $teacher_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                // Delete old image if exists
                if (!empty($teacher['profile_picture']) && file_exists($upload_dir . $teacher['profile_picture'])) {
                    unlink($upload_dir . $teacher['profile_picture']);
                }
                
                $profile_picture = $new_filename;
            } else {
                throw new Exception("Failed to upload image");
            }
        }
        
        // Update teachers table
        $update_teacher = "UPDATE teachers SET 
                          first_name = ?,
                          middle_name = ?,
                          last_name = ?,
                          gender = ?,
                          email = ?,
                          phone = ?,
                          address = ?,
                          department = ?,
                          profile_picture = ?
                          WHERE id = ?";
        
        $stmt = $conn->prepare($update_teacher);
        $stmt->bind_param(
            "sssssssssi",
            $_POST['first_name'],
            $_POST['middle_name'],
            $_POST['last_name'],
            $_POST['gender'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['address'],
            $_POST['department'],
            $profile_picture,
            $teacher_id
        );
        $stmt->execute();
        
        // Update username in users table if it was changed
        if (!empty($_POST['username']) && $_POST['username'] !== $teacher['username']) {
            // Check if username is already taken
            $check_username = "SELECT id FROM users WHERE username = ? AND id != ?";
            $stmt = $conn->prepare($check_username);
            $stmt->bind_param("si", $_POST['username'], $teacher['user_id']);
            $stmt->execute();
            $username_check = $stmt->get_result();
            
            if ($username_check->num_rows > 0) {
                throw new Exception("Username already taken. Please choose another one.");
            }
            
            $update_user = "UPDATE users SET username = ? WHERE id = ?";
            $stmt = $conn->prepare($update_user);
            $stmt->bind_param("si", $_POST['username'], $teacher['user_id']);
            $stmt->execute();
        }
        
        // Update password if provided
        if (!empty($_POST['new_password'])) {
            $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $update_password = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($update_password);
            $stmt->bind_param("si", $hashed_password, $teacher['user_id']);
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Profile updated successfully!";
        
        // Refresh teacher data
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $teacher = $result->fetch_assoc();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
    }
}

// Function to get profile image URL
function getProfileImageUrl($teacher) {
    $upload_dir = '../uploads/profile_images/';
    
    if (!empty($teacher['profile_picture']) && file_exists($upload_dir . $teacher['profile_picture'])) {
        return $upload_dir . $teacher['profile_picture'];
    }
    
    return "https://ui-avatars.com/api/?name=" . urlencode($teacher['first_name'] . '+' . $teacher['last_name']) . "&background=random&size=150";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Profile - BSIT 2C AMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #021F3F;
            --secondary-color: #C8A77E;
            --primary-dark: #011327;
            --secondary-light: #d8b78e;
        }
        
        .profile-header {
            padding: 2rem 0;
            background-color: var(--primary-color);
            color: white;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
        }
        
        .profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid var(--secondary-color);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .profile-img-container {
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .profile-img-edit {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background-color: var(--secondary-color);
            color: var(--primary-color);
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.2s;
        }
        
        .profile-img-edit:hover {
            background-color: var(--secondary-light);
            transform: scale(1.1);
        }
        
        .required-field::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
        
        .form-label {
            font-weight: 500;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
        }
        
        #image-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 5px;
            display: none;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-outline-secondary {
            color: var(--primary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--secondary-color);
            color: var(--primary-color);
            border-color: var(--secondary-color);
        }
        
        a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        a:hover {
            color: var(--secondary-color);
        }
        
        .text-muted {
            color: #6c757d !important;
        }
        
        .alert-success {
            background-color: rgba(200, 167, 126, 0.2);
            border-color: var(--secondary-color);
            color: #6b5942;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-4">
        <div class="row">
            <div class="col-md-12 mb-4">
                <h2><i class="bi bi-person-circle"></i> My Profile</h2>
                <p class="text-muted">View and edit your profile information</p>
            </div>
        </div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($teacher): ?>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-person"></i> Profile Summary
                    </div>
                    <div class="card-body text-center">
                        <div class="profile-img-container">
                            <img src="<?= getProfileImageUrl($teacher) ?>" class="profile-img mb-3" alt="<?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?>">
                            <div class="profile-img-edit" title="Change profile picture" id="change-profile-image">
                                <i class="bi bi-camera"></i>
                            </div>
                        </div>
                        <h4><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?></h4>
                        <p class="text-muted"><?= htmlspecialchars(($teacher['teacher_id'] ?? false) ? 'Teacher ID: ' . $teacher['teacher_id'] : 'No ID assigned') ?></p>
                        <p class="mb-1"><i class="bi bi-building"></i> <?= htmlspecialchars(($teacher['department'] ?? false) ? $teacher['department'] : 'No Department') ?></p>
                        <p class="mb-1"><i class="bi bi-envelope"></i> <?= htmlspecialchars(($teacher['email'] ?? false) ? $teacher['email'] : 'No Email') ?></p>
                        <p class="mb-1"><i class="bi bi-telephone"></i> <?= htmlspecialchars(($teacher['phone'] ?? false) ? $teacher['phone'] : 'No Phone Number') ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-pencil-square"></i> Edit Profile Information
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <!-- Hidden file input for profile image -->
                            <input type="file" id="profile_image" name="profile_image" accept="image/*" style="display: none;">
                            <div id="image-preview-container" style="display: none;" class="mb-3">
                                <label class="form-label">Profile Image Preview</label>
                                <div class="text-center">
                                    <img id="image-preview" src="#" alt="Preview">
                                </div>
                                <div class="text-center mt-2">
                                    <small class="text-muted d-block mb-2">Click "Save Changes" at the bottom of the form to update your profile with this image</small>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="cancel-image">Cancel</button>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <h5>Account Information</h5>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label required-field">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($teacher['username'] ?? '') ?>" required>
                                    <div class="invalid-feedback">Please enter your username</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="teacher_id" class="form-label">Teacher ID</label>
                                    <input type="text" class="form-control" id="teacher_id" value="<?= htmlspecialchars($teacher['teacher_id'] ?? '') ?>" disabled readonly>
                                    <small class="text-muted">Teacher ID cannot be changed</small>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <h5 class="mt-4">Personal Information</h5>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="first_name" class="form-label required-field">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($teacher['first_name'] ?? '') ?>" 
                                           required
                                           pattern="^[A-Za-z\s]{3,}$" 
                                           title="First name must be at least 3 characters and contain only letters">
                                    <div class="invalid-feedback">First name must be at least 3 characters and contain only letters</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                           value="<?= htmlspecialchars($teacher['middle_name'] ?? '') ?>"
                                           pattern="^[A-Za-z\s]*$"
                                           title="Middle name must contain only letters">
                                    <div class="invalid-feedback">Middle name must contain only letters</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="last_name" class="form-label required-field">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($teacher['last_name'] ?? '') ?>" 
                                           required
                                           pattern="^[A-Za-z\s]{3,}$"
                                           title="Last name must be at least 3 characters and contain only letters">
                                    <div class="invalid-feedback">Last name must be at least 3 characters and contain only letters</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="gender" class="form-label required-field">Gender</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="Male" <?= ($teacher['gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($teacher['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= ($teacher['gender'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                    <div class="invalid-feedback">Please select your gender</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="email" class="form-label required-field">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($teacher['email'] ?? '') ?>" 
                                           required
                                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                                    <div class="invalid-feedback">Please enter a valid email address</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($teacher['phone'] ?? '') ?>"
                                           pattern="^09[0-9]{9}$"
                                           title="Phone number must be in format 09XXXXXXXXX">
                                    <div class="invalid-feedback">Phone number must be in format 09XXXXXXXXX</div>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <input type="text" class="form-control" id="address" name="address" value="<?= htmlspecialchars($teacher['address'] ?? '') ?>">
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <h5 class="mt-4">Professional Information</h5>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label for="department" class="form-label required-field">Department</label>
                                    <input type="text" class="form-control" id="department" name="department" value="<?= htmlspecialchars($teacher['department'] ?? '') ?>" required>
                                    <div class="invalid-feedback">Please enter your department</div>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <h5 class="mt-4">Change Password</h5>
                                    <p class="text-muted small">Leave blank if you don't want to change your password</p>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$">
                                    <div class="invalid-feedback">Password should be at least 8 characters with at least one uppercase letter, one lowercase letter, and one number</div>
                                    <small class="text-muted">Password should be at least 8 characters with at least one uppercase letter, one lowercase letter, and one number</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    <div class="invalid-feedback">Passwords do not match</div>
                                </div>
                                
                                <div class="col-md-12 mt-4">
                                    <hr>
                                    <button type="submit" class="btn btn-primary btn-lg" style="background-color: var(--primary-color); border-color: var(--primary-color); transition: all 0.3s;">
                                        <i class="bi bi-save"></i> Save All Changes
                                    </button>
                                    <a href="dashboard.php" class="btn btn-outline-secondary btn-lg ms-2" style="color: var(--primary-color); border-color: var(--secondary-color); transition: all 0.3s;">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </a>
                                    <small class="d-block text-muted mt-2">This will save all changes including your profile picture, if changed.</small>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            
            // Fetch all the forms we want to apply custom validation to
            var forms = document.querySelectorAll('.needs-validation');
            
            // Password fields
            const newPasswordField = document.getElementById('new_password');
            const confirmPasswordField = document.getElementById('confirm_password');
            
            // Add real-time password matching validation
            if (newPasswordField && confirmPasswordField) {
                const validatePasswordMatch = function() {
                    if (newPasswordField.value !== confirmPasswordField.value) {
                        confirmPasswordField.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPasswordField.setCustomValidity('');
                    }
                };
                
                newPasswordField.addEventListener('input', validatePasswordMatch);
                confirmPasswordField.addEventListener('input', validatePasswordMatch);
            }
            
            // Loop over forms and prevent submission if not valid
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    // Additional password confirmation check for better UX
                    if (newPasswordField && confirmPasswordField && 
                        newPasswordField.value !== '' && 
                        newPasswordField.value !== confirmPasswordField.value) {
                        event.preventDefault();
                        alert('New password and confirmation do not match!');
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
            
            // Profile image handling
            document.getElementById('change-profile-image').addEventListener('click', function() {
                document.getElementById('profile_image').click();
            });
            
            document.getElementById('profile_image').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Show image preview
                    const reader = new FileReader();
                    const preview = document.getElementById('image-preview');
                    
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                        document.getElementById('image-preview-container').style.display = 'block';
                    }
                    
                    reader.readAsDataURL(file);
                }
            });
            
            document.getElementById('cancel-image').addEventListener('click', function() {
                document.getElementById('profile_image').value = '';
                document.getElementById('image-preview').src = '';
                document.getElementById('image-preview-container').style.display = 'none';
            });
            
            // Name fields validation
            const firstNameField = document.getElementById('first_name');
            const lastNameField = document.getElementById('last_name');
            const middleNameField = document.getElementById('middle_name');
            
            // Validate names contain only letters and spaces
            const nameRegex = /^[A-Za-z\s]{3,}$/;
            const anyLettersRegex = /^[A-Za-z\s]*$/;
            
            if (firstNameField) {
                firstNameField.addEventListener('input', function() {
                    const value = firstNameField.value;
                    if (value && !nameRegex.test(value)) {
                        if (value.length < 3) {
                            firstNameField.setCustomValidity('First name must be at least 3 characters');
                        } else if (!/^[A-Za-z\s]*$/.test(value)) {
                            firstNameField.setCustomValidity('First name can only contain letters and spaces');
                        } else {
                            firstNameField.setCustomValidity('First name must be valid');
                        }
                    } else {
                        firstNameField.setCustomValidity('');
                    }
                });
            }
            
            if (lastNameField) {
                lastNameField.addEventListener('input', function() {
                    const value = lastNameField.value;
                    if (value && !nameRegex.test(value)) {
                        if (value.length < 3) {
                            lastNameField.setCustomValidity('Last name must be at least 3 characters');
                        } else if (!/^[A-Za-z\s]*$/.test(value)) {
                            lastNameField.setCustomValidity('Last name can only contain letters and spaces');
                        } else {
                            lastNameField.setCustomValidity('Last name must be valid');
                        }
                    } else {
                        lastNameField.setCustomValidity('');
                    }
                });
            }
            
            if (middleNameField) {
                middleNameField.addEventListener('input', function() {
                    const value = middleNameField.value;
                    if (value && !anyLettersRegex.test(value)) {
                        middleNameField.setCustomValidity('Middle name can only contain letters and spaces');
                    } else {
                        middleNameField.setCustomValidity('');
                    }
                });
            }
            
            // Phone number validation
            const phoneField = document.getElementById('phone');
            const phoneRegex = /^09[0-9]{9}$/;
            
            if (phoneField) {
                phoneField.addEventListener('input', function() {
                    const value = phoneField.value;
                    if (value && !phoneRegex.test(value)) {
                        phoneField.setCustomValidity('Phone number must be in format 09XXXXXXXXX');
                    } else {
                        phoneField.setCustomValidity('');
                    }
                });
            }
        })();
    </script>
</body>
</html> 