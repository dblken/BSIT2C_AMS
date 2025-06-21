// Updated email validation regex for validateTeacherForm function
const emailValidationRegex = /^[^\s@]+@[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*\.(com|net|org|edu|gov|mil|int|info|biz|name|pro|museum|coop|aero|xxx|idv|ac|edu)$/;

// Updated code for validateTeacherForm function
const validateTeacherFormEmailCode = `
    // Email validation
    const email = form.querySelector('[name="email"]');
    if (!email.value.trim()) {
        showError(email, 'Please enter an email address.');
        isValid = false;
    } else if (!/^[^\s@]+@[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*\.(com|net|org|edu|gov|mil|int|info|biz|name|pro|museum|coop|aero|xxx|idv|ac|edu)$/.test(email.value.trim())) {
        showError(email, 'Please enter a valid email address with a properly formatted domain name and common extension (.com, .net, .org, etc.).');
        isValid = false;
    } else if (/^[^\s@]+@[^\s@]+\.[^.\s@]+\.[^.\s@]+/.test(email.value.trim())) {
        showError(email, 'Invalid email domain. Multiple extensions are not allowed.');
        isValid = false;
    } else {
        // For form submission, we'll use a synchronous approach to check email
        let emailValid = true;
        $.ajax({
            url: 'check_teacher_email.php',
            type: 'POST',
            data: { email: email.value.trim() },
            dataType: 'json',
            async: false, // Make this synchronous for form submission
            success: function(response) {
                if (response.exists) {
                    showError(email, 'This email is already in use. Please use a different email address.');
                    emailValid = false;
                }
            }
        });
        
        if (!emailValid) {
            isValid = false;
        }
    }
`;

// Updated code for email case in switch statement
const switchEmailCode = `
            case 'email':
                if (!fieldValue) {
                    showError(this, 'Please enter an email address.');
                } else if (!/^[^\s@]+@[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*\.(com|net|org|edu|gov|mil|int|info|biz|name|pro|museum|coop|aero|xxx|idv|ac|edu)$/.test(fieldValue)) {
                    showError(this, 'Please enter a valid email address with a properly formatted domain name and common extension (.com, .net, .org, etc.).');
                } else if (/^[^\s@]+@[^\s@]+\.[^.\s@]+\.[^.\s@]+/.test(fieldValue)) {
                    showError(this, 'Invalid email domain. Multiple extensions are not allowed.');
                } else {
                    // Remove any existing error messages before AJAX call
                    $(this).removeClass('is-invalid').addClass('is-valid');
                    
                    // Check if email already exists using AJAX
                    const inputElement = this;
                    
                    // Abort any previous request
                    if (emailValidationXhr && emailValidationXhr.readyState !== 4) {
                        emailValidationXhr.abort();
                    }
                    
                    // Start new request
                    emailValidationXhr = $.ajax({
                        url: 'check_teacher_email.php',
                        type: 'POST',
                        data: { email: fieldValue },
                        dataType: 'json',
                        success: function(response) {
                            if (response.exists) {
                                showError(inputElement, 'This email is already in use. Please use a different email address.');
                            } else {
                                $(inputElement).addClass('is-valid');
                            }
                        },
                        error: function(xhr) {
                            // Only handle if not aborted
                            if (xhr.statusText !== 'abort') {
                                // If error occurs, we'll assume it's valid and let server-side validation catch any issues
                                $(inputElement).addClass('is-valid');
                            }
                        }
                    });
                }
                break;
`;

// Updated code for validateEditTeacherForm function
const validateEditTeacherFormEmailCode = `
    // Email validation
    const email = form.querySelector('[name="email"]');
    if (!email.value.trim()) {
        window.showError(email, 'Please enter an email address.');
        isValid = false;
    } else if (!/^[^\s@]+@[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*\.(com|net|org|edu|gov|mil|int|info|biz|name|pro|museum|coop|aero|xxx|idv|ac|edu)$/.test(email.value.trim())) {
        window.showError(email, 'Please enter a valid email address with a properly formatted domain name and common extension (.com, .net, .org, etc.).');
        isValid = false;
    } else if (/^[^\s@]+@[^\s@]+\.[^.\s@]+\.[^.\s@]+/.test(email.value.trim())) {
        window.showError(email, 'Invalid email domain. Multiple extensions are not allowed.');
        isValid = false;
    } else {
        // For edit form, we need to check if the email is used by another teacher
        let emailValid = true;
        const teacherId = form.querySelector('[name="id"]').value;
        
        $.ajax({
            url: 'check_teacher_email_edit.php',
            type: 'POST',
            data: { 
                email: email.value.trim(),
                teacher_id: teacherId 
            },
            dataType: 'json',
            async: false, // Make this synchronous for form submission
            success: function(response) {
                if (response.exists) {
                    window.showError(email, 'This email is already in use by another teacher. Please use a different email address.');
                    emailValid = false;
                } else {
                    email.classList.add('is-valid');
                }
            }
        });
        
        if (!emailValid) {
            isValid = false;
        }
    }
`; 