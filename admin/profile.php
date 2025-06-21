<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Get admin details
$sql = "SELECT a.*, u.username, u.last_login 
        FROM admins a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// Check if phone column exists, if not add it
$check_phone = "SHOW COLUMNS FROM admins LIKE 'phone'";
$phone_exists = $conn->query($check_phone);
if ($phone_exists->num_rows == 0) {
    $add_phone = "ALTER TABLE admins ADD COLUMN phone VARCHAR(20) DEFAULT NULL";
    $conn->query($add_phone);
}

// Check if profile_image column exists, if not add it
$check_column = "SHOW COLUMNS FROM admins LIKE 'profile_image'";
$column_exists = $conn->query($check_column);
if ($column_exists->num_rows == 0) {
    $add_column = "ALTER TABLE admins ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL";
    $conn->query($add_column);
}

// Handle profile update
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Validate inputs
    $errors = [];
    
    // Validate first name (at least 3 chars, letters only)
    if (empty($_POST['first_name'])) {
        $errors[] = "First name is required";
    } elseif (strlen($_POST['first_name']) < 3 || !preg_match('/^[A-Za-z\s]+$/', $_POST['first_name'])) {
        $errors[] = "First name must be at least 3 characters and contain only letters";
    }
    
    // Validate last name (at least 3 chars, letters only)
    if (empty($_POST['last_name'])) {
        $errors[] = "Last name is required";
    } elseif (strlen($_POST['last_name']) < 3 || !preg_match('/^[A-Za-z\s]+$/', $_POST['last_name'])) {
        $errors[] = "Last name must be at least 3 characters and contain only letters";
    }
    
    // Validate email
    if (empty($_POST['email'])) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    // Validate phone (must start with 09 and be 11 digits) - only if not empty
    if (!empty($_POST['phone']) && !preg_match('/^09\d{9}$/', $_POST['phone'])) {
        $errors[] = "Phone number must be in format 09XXXXXXXXX";
    }
    
    if (empty($errors)) {
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        
        // Update admin information
        $update_sql = "UPDATE admins SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $_SESSION['admin_id']);
        
        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully!";
            
            // Refresh admin data
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();
        } else {
            $error_message = "Error updating profile: " . $conn->error;
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $file = $_FILES['profile_image'];
    
    // Validate file type and size
    if (!in_array($file['type'], $allowed_types)) {
        $error_message = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
    } elseif ($file['size'] > $max_size) {
        $error_message = "File size too large. Maximum allowed size is 5MB.";
    } else {
        // Create uploads directory if it doesn't exist
        $upload_dir = '../uploads/profile_images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $filename = 'admin_' . $_SESSION['admin_id'] . '_' . time() . '_' . basename($file['name']);
        $target_file = $upload_dir . $filename;
        
        // Delete old profile image if exists
        if (!empty($admin['profile_image'])) {
            $old_image_path = '../' . $admin['profile_image'];
            if(file_exists($old_image_path)) {
                unlink($old_image_path);
            }
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $file_path = 'uploads/profile_images/' . $filename;
            
            // Update profile image in database
            $update_image_sql = "UPDATE admins SET profile_image = ? WHERE id = ?";
            $update_image_stmt = $conn->prepare($update_image_sql);
            $update_image_stmt->bind_param("si", $file_path, $_SESSION['admin_id']);
            
            if ($update_image_stmt->execute()) {
                $success_message = "Profile image updated successfully!";
                
                // Refresh admin data
                $stmt->execute();
                $result = $stmt->get_result();
                $admin = $result->fetch_assoc();
                
                // Force reload the page to refresh the image
                echo "<script>window.location = window.location.href;</script>";
                exit;
            } else {
                $error_message = "Error updating profile image: " . $conn->error;
            }
        } else {
            $error_message = "Error uploading file. Please try again.";
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Initialize errors array
    $errors = [];
    
    // Check if passwords are provided
    if (empty($current_password)) {
        $errors[] = "Current password is required";
    }
    
    if (empty($new_password)) {
        $errors[] = "New password is required";
    } else {
        // Validate password complexity
        if (strlen($new_password) < 8) {
            $errors[] = "Password must be at least 8 characters";
        }
        
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $new_password)) {
            $errors[] = "Password must contain at least one uppercase letter, one lowercase letter, and one number";
        }
    }
    
    // Check if new passwords match
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    if (empty($errors)) {
        // Verify current password
        $check_sql = "SELECT password FROM users WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $admin['user_id']);
        $check_stmt->execute();
        $password_result = $check_stmt->get_result()->fetch_assoc();
        
        if (password_verify($current_password, $password_result['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pwd_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_pwd_stmt = $conn->prepare($update_pwd_sql);
            $update_pwd_stmt->bind_param("si", $hashed_password, $admin['user_id']);
            
            if ($update_pwd_stmt->execute()) {
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Error changing password: " . $conn->error;
            }
        } else {
            $error_message = "Current password is incorrect";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

include 'includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-10">
            <!-- Page Header -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <h2 class="fw-bold text-primary mb-2">
                            <i class="fas fa-user-circle me-2"></i> Admin Profile
                        </h2>
                        <p class="text-muted mb-0">View and update your profile information</p>
                    </div>
                </div>
            </div>
            
            <?php if($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Profile Picture Card -->
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient-primary-to-secondary p-4 text-white">
                            <h5 class="fw-bold mb-0">
                                <i class="fas fa-camera me-2"></i> Profile Picture
                            </h5>
                        </div>
                        <div class="card-body p-4 text-center">
                            <div class="mb-4">
                                <?php 
                                $image_path = '';
                                $display_image = false;
                                
                                if(!empty($admin['profile_image'])) {
                                    // Set the image path for display
                                    if (strpos($admin['profile_image'], '/') === 0) {
                                        $image_path = $admin['profile_image'];
                                    } else {
                                        $image_path = '../' . $admin['profile_image'];
                                    }
                                    
                                    // Debug information
                                    echo "<!-- Profile image DB path: " . htmlspecialchars($admin['profile_image']) . " -->";
                                    echo "<!-- Profile image display path: " . htmlspecialchars($image_path) . " -->";
                                    
                                    // Always display the image if it exists in the database
                                    $display_image = true;
                                }
                                
                                if($display_image): 
                                ?>
                                    <div class="position-relative mx-auto mb-3" style="width: 150px; height: 150px;">
                                        <img src="<?php echo $image_path; ?>" alt="Admin Profile" 
                                             class="rounded-circle border shadow-sm" style="width: 150px; height: 150px; object-fit: cover;"
                                             onerror="this.onerror=null; this.src='/BSIT2C_AMS/assets/img/default-profile.png'; console.log('Image failed to load');">
                                        <button type="button" class="btn btn-sm btn-danger position-absolute" 
                                                style="bottom: 0; right: 0; border-radius: 50%;" id="removeProfileImage"
                                                data-admin-id="<?php echo $_SESSION['admin_id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="avatar-circle mx-auto mb-3 bg-primary text-white">
                                        <i class="fas fa-user-circle fa-4x"></i>
                                    </div>
                                <?php endif; ?>
                                <h4 class="mb-1"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></h4>
                                <p class="text-muted">Administrator</p>
                            </div>
                            
                            <form method="POST" action="" enctype="multipart/form-data" id="profileImageForm">
                                <div class="mb-3">
                                    <label for="profile_image" class="form-label">Upload New Image</label>
                                    <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/jpeg, image/png, image/gif">
                                    <div class="form-text">JPG, PNG or GIF. Max size 5MB.</div>
                                </div>
                                <button type="submit" class="btn btn-primary" id="uploadButton" disabled>
                                    <i class="fas fa-upload me-2"></i> Upload Image
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Information Card -->
                <div class="col-md-8 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-gradient-primary-to-secondary p-4 text-white">
                            <h5 class="fw-bold mb-0">
                                <i class="fas fa-id-card me-2"></i> Personal Information
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="" name="profile-form" class="needs-validation" novalidate>
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label required-field">First Name</label>
                                        <input type="text" class="form-control" name="first_name" 
                                               value="<?php echo htmlspecialchars($admin['first_name'] ?? ''); ?>" 
                                               pattern="^[A-Za-z\s]{3,}$" 
                                               title="First name must be at least 3 characters and contain only letters"
                                               required>
                                        <div class="invalid-feedback">
                                            First name must be at least 3 characters and contain only letters
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label required-field">Last Name</label>
                                        <input type="text" class="form-control" name="last_name" 
                                               value="<?php echo htmlspecialchars($admin['last_name'] ?? ''); ?>" 
                                               pattern="^[A-Za-z\s]{3,}$" 
                                               title="Last name must be at least 3 characters and contain only letters"
                                               required>
                                        <div class="invalid-feedback">
                                            Last name must be at least 3 characters and contain only letters
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label required-field">Email Address</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" 
                                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                                           title="Please enter a valid email address"
                                           required>
                                    <div class="invalid-feedback">
                                        Please enter a valid email address
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" 
                                           value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>"
                                           pattern="^09[0-9]{9}$"
                                           title="Phone number must be in format 09XXXXXXXXX">
                                    <div class="invalid-feedback">
                                        Phone number must be in format 09XXXXXXXXX
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" name="username" 
                                           value="<?php echo htmlspecialchars($admin['username'] ?? ''); ?>" readonly>
                                    <small class="text-muted">Username cannot be changed</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Last Login</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo date('M d, Y h:i A', strtotime($admin['last_login'])); ?>" readonly>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Change Password Card -->
                <div class="col-md-12 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient-primary-to-secondary p-4 text-white">
                            <h5 class="fw-bold mb-0">
                                <i class="fas fa-lock me-2"></i> Change Password
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="" id="passwordForm" class="needs-validation" novalidate>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> Strong passwords should be at least 8 characters long and include a mix of letters, numbers, and special characters.
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label required-field">Current Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="current_password" id="current_password" required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback">
                                            Current password is required
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label required-field">New Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="new_password" id="new_password"
                                                   minlength="8" 
                                                   pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$"
                                                   title="Password must be at least 8 characters with at least one uppercase letter, one lowercase letter, and one number"
                                                   required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback">
                                            Password must be at least 8 characters with at least one uppercase letter, one lowercase letter, and one number
                                        </div>
                                        <small class="text-muted">Password should be at least 8 characters with at least one uppercase letter, one lowercase letter, and one number</small>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label required-field">Confirm New Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback">
                                            Passwords do not match
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        <i class="fas fa-key me-2"></i> Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>

<script>
$(document).ready(function() {
    console.log("jQuery loaded and document ready");
    
    // Clear validation state on page load if there was a successful update
    <?php if($success_message): ?>
    clearAllValidationErrors();
    <?php endif; ?>
    
    // Toggle password visibility
    $('.toggle-password').click(function() {
        const targetId = $(this).data('target');
        const passwordField = $('#' + targetId);
        const fieldType = passwordField.attr('type');
        
        if (fieldType === 'password') {
            passwordField.attr('type', 'text');
            $(this).find('i').removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordField.attr('type', 'password');
            $(this).find('i').removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // Function to clear all validation errors
    function clearAllValidationErrors() {
        // Reset all form inputs validation state
        $('form input').removeClass('is-invalid is-valid');
        $('.invalid-feedback, .valid-feedback').hide();
        
        // Reset custom validity on all inputs
        $('form input').each(function() {
            this.setCustomValidity('');
        });
        
        // Remove was-validated class from forms
        $('form').removeClass('was-validated');
    }
    
    // Profile form validation
    $('form[name="profile-form"]').on('submit', function(e) {
        console.log("Form submission attempted");
        let isValid = true;
        
        // Clear previous errors first
        clearAllValidationErrors();
        
        // Validate first name
        const firstName = $('input[name="first_name"]');
        const firstNameValue = firstName.val().trim();
        const nameRegex = /^[A-Za-z\s]{3,}$/;
        
        if (firstNameValue === '') {
            showError(firstName, 'First name is required');
            isValid = false;
        } else if (!nameRegex.test(firstNameValue)) {
            showError(firstName, 'First name must be at least 3 characters and contain only letters');
            isValid = false;
        } else {
            removeError(firstName);
        }
        
        // Validate last name
        const lastName = $('input[name="last_name"]');
        const lastNameValue = lastName.val().trim();
        
        if (lastNameValue === '') {
            showError(lastName, 'Last name is required');
            isValid = false;
        } else if (!nameRegex.test(lastNameValue)) {
            showError(lastName, 'Last name must be at least 3 characters and contain only letters');
            isValid = false;
        } else {
            removeError(lastName);
        }
        
        // Validate email
        const email = $('input[name="email"]');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (email.val().trim() === '') {
            showError(email, 'Email is required');
            isValid = false;
        } else if (!emailRegex.test(email.val().trim())) {
            showError(email, 'Please enter a valid email address');
            isValid = false;
        } else {
            removeError(email);
        }
        
        // Validate phone (optional: must start with 09 and be 11 digits)
        const phone = $('input[name="phone"]');
        const phoneValue = phone.val().trim();
        const phoneRegex = /^09\d{9}$/;
        
        if (phoneValue === '') {
            // Phone is optional
            removeError(phone);
        } else if (!phoneRegex.test(phoneValue)) {
            showError(phone, 'Phone number must start with 09 and be 11 digits long');
            isValid = false;
        } else {
            removeError(phone);
        }
        
        if (!isValid) {
            console.log("Validation failed, preventing form submission");
            e.preventDefault();
        } else {
            console.log("Validation passed, form will be submitted");
            // Don't prevent the form submission - let it proceed
        }
    });
    
    // Password form validation
    $('#passwordForm').on('submit', function(e) {
        let isValid = true;
        
        // Validate current password
        const currentPassword = $('#current_password');
        if (currentPassword.val().trim() === '') {
            showError(currentPassword, 'Current password is required');
            isValid = false;
        } else {
            removeError(currentPassword);
        }
        
        // Validate new password
        const newPassword = $('#new_password');
        if (newPassword.val().trim() === '') {
            showError(newPassword, 'New password is required');
            isValid = false;
        } else if (newPassword.val().length < 8) {
            showError(newPassword, 'Password must be at least 8 characters long');
            isValid = false;
        } else {
            removeError(newPassword);
        }
        
        // Validate confirm password
        const confirmPassword = $('#confirm_password');
        if (confirmPassword.val().trim() === '') {
            showError(confirmPassword, 'Please confirm your new password');
            isValid = false;
        } else if (confirmPassword.val() !== newPassword.val()) {
            showError(confirmPassword, 'Passwords do not match');
            isValid = false;
        } else {
            removeError(confirmPassword);
        }
        
        // Return false to prevent form submission if validation fails
        if (!isValid) {
            e.preventDefault();
        }
    });
    
    // Check if passwords match as user types
    $('#new_password, #confirm_password').on('keyup', function() {
        const newPassword = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();
        
        if (confirmPassword.trim() !== '') {
            if (newPassword !== confirmPassword) {
                showError($('#confirm_password'), 'Passwords do not match');
            } else {
                removeError($('#confirm_password'));
                $('#confirm_password').next('.invalid-feedback').remove();
                $('<div class="valid-feedback d-block">Passwords match</div>').insertAfter('#confirm_password');
            }
        }
    });
    
    // Function to display error message under a field
    function showError(field, message) {
        // Remove any existing error message
        field.removeClass('is-valid').addClass('is-invalid');
        field.next('.invalid-feedback, .valid-feedback').remove();
        $('<div class="invalid-feedback">' + message + '</div>').insertAfter(field);
    }
    
    // Function to remove error message
    function removeError(field) {
        field.removeClass('is-invalid').addClass('is-valid');
        field.next('.invalid-feedback, .valid-feedback').remove();
    }
    
    // Enable upload button only when a file is selected
    $('#profile_image').on('change', function() {
        if ($(this).val()) {
            $('#uploadButton').prop('disabled', false);
        } else {
            $('#uploadButton').prop('disabled', true);
        }
    });
    
    // Remove profile image
    $('#removeProfileImage').on('click', function() {
        if (confirm('Are you sure you want to remove your profile picture?')) {
            const adminId = $(this).data('admin-id');
            
            $.ajax({
                url: 'remove_profile_image.php',
                type: 'POST',
                data: { admin_id: adminId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        }
    });
    
    // Enhanced form validation 
    $(document).ready(function() {
        // Add HTML5 validation to forms
        const forms = document.querySelectorAll('.needs-validation');
        
        // Name fields validation
        const firstNameField = $('input[name="first_name"]');
        const lastNameField = $('input[name="last_name"]');
        const nameRegex = /^[A-Za-z\s]{3,}$/;
        
        // Add custom validation for first name
        if (firstNameField.length) {
            firstNameField.on('input', function() {
                const value = $(this).val().trim();
                if (value === '') {
                    this.setCustomValidity('First name is required');
                } else if (!nameRegex.test(value)) {
                    if (value.length < 3) {
                        this.setCustomValidity('First name must be at least 3 characters');
                    } else if (!/^[A-Za-z\s]*$/.test(value)) {
                        this.setCustomValidity('First name can only contain letters and spaces');
                    } else {
                        this.setCustomValidity('First name must be valid');
                    }
                } else {
                    this.setCustomValidity('');
                    $(this).removeClass('is-invalid').addClass('is-valid');
                }
            });
        }
        
        // Add custom validation for last name
        if (lastNameField.length) {
            lastNameField.on('input', function() {
                const value = $(this).val().trim();
                if (value === '') {
                    this.setCustomValidity('Last name is required');
                } else if (!nameRegex.test(value)) {
                    if (value.length < 3) {
                        this.setCustomValidity('Last name must be at least 3 characters');
                    } else if (!/^[A-Za-z\s]*$/.test(value)) {
                        this.setCustomValidity('Last name can only contain letters and spaces');
                    } else {
                        this.setCustomValidity('Last name must be valid');
                    }
                } else {
                    this.setCustomValidity('');
                    $(this).removeClass('is-invalid').addClass('is-valid');
                }
            });
        }
        
        // Phone number validation
        const phoneField = $('input[name="phone"]');
        const phoneRegex = /^09[0-9]{9}$/;
        
        if (phoneField.length) {
            phoneField.on('input', function() {
                const value = $(this).val().trim();
                if (value === '') {
                    // Phone is optional
                    this.setCustomValidity('');
                    $(this).removeClass('is-invalid');
                } else if (!phoneRegex.test(value)) {
                    this.setCustomValidity('Phone number must be in format 09XXXXXXXXX');
                    $(this).addClass('is-invalid');
                } else {
                    this.setCustomValidity('');
                    $(this).removeClass('is-invalid').addClass('is-valid');
                }
            });
        }
        
        // Password validation for complexity
        const newPasswordField = $('#new_password');
        const confirmPasswordField = $('#confirm_password');
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
        
        if (newPasswordField.length) {
            newPasswordField.on('input', function() {
                const value = $(this).val().trim();
                if (value && !passwordRegex.test(value)) {
                    this.setCustomValidity('Password must be at least 8 characters with at least one uppercase letter, one lowercase letter, and one number');
                } else {
                    this.setCustomValidity('');
                    // Check if confirm password needs to be updated
                    if (confirmPasswordField.val().trim() !== '') {
                        confirmPasswordField.trigger('input');
                    }
                }
            });
        }
        
        // Confirm password matching validation
        if (confirmPasswordField.length && newPasswordField.length) {
            confirmPasswordField.on('input', function() {
                const confirmValue = $(this).val().trim();
                const passwordValue = newPasswordField.val().trim();
                
                if (confirmValue !== passwordValue) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
        
        // Apply HTML5 validation on form submission
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
        
        // Hide validation errors when clicking close button on success message
        $('.alert-success .btn-close').on('click', function() {
            clearAllValidationErrors();
        });
    });
});
</script>

<style>
    .avatar-circle {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Custom styles for validation */
    .invalid-feedback {
        display: none;
        font-size: 0.875em;
        color: #dc3545;
        margin-top: 0.25rem;
    }
    
    .valid-feedback {
        display: none;
        font-size: 0.875em;
        color: #198754;
        margin-top: 0.25rem;
    }
    
    .was-validated .invalid-feedback,
    .was-validated .valid-feedback,
    .is-invalid ~ .invalid-feedback,
    .is-valid ~ .valid-feedback {
        display: block;
    }
    
    .is-invalid {
        border-color: #dc3545;
    }
    
    .is-valid {
        border-color: #198754;
    }
    
    /* Required field indicator */
    .required-field::after {
        content: " *";
        color: #dc3545;
        font-weight: bold;
    }
</style> 