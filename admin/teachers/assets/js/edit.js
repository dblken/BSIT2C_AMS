// Function to edit teacher
function editTeacher(teacherId) {
    console.log('Edit teacher function called with ID:', teacherId);
    
    // Show loading state in the modal
    $('#editTeacherModal .modal-body').prepend(
        '<div id="loadingIndicator" class="text-center py-4">' +
        '<div class="spinner-border text-primary" role="status">' +
        '<span class="visually-hidden">Loading...</span>' +
        '</div>' +
        '<p class="mt-2 text-muted">Loading teacher information...</p>' +
        '</div>'
    );
    
    // Show the modal immediately with loading state
    $('#editTeacherModal').modal('show');
    
    // Fetch teacher data
    $.ajax({
        url: 'edit.php',
        type: 'GET',
        data: { id: teacherId },
        dataType: 'json',
        success: function(response) {
            // Remove loading indicator
            $('#loadingIndicator').remove();
            
            console.log('Teacher data received:', response);
            if (response.success) {
                const teacher = response.teacher;
                console.log('Teacher data to populate:', teacher);
                
                if (!teacher) {
                    alert('Error: Teacher data not found in response');
                    return;
                }
                
                // Populate edit form with all fields
                $('#edit_id').val(teacher.id);
                $('#edit_teacher_id').val(teacher.teacher_id);
                $('#edit_first_name').val(teacher.first_name);
                $('#edit_middle_name').val(teacher.middle_name || '');
                $('#edit_last_name').val(teacher.last_name);
                $('#edit_gender').val(teacher.gender);
                
                // Debug birthday field
                console.log('Birthday value from server:', teacher.birthday);
                const birthdayField = $('#edit_birthday');
                birthdayField.val(teacher.birthday || '');
                console.log('Birthday field value after setting:', birthdayField.val());
                
                $('#edit_email').val(teacher.email);
                $('#edit_phone').val(teacher.phone || '');
                $('#edit_department').val('IT Department');
                
                console.log('Form populated with values:', {
                    id: $('#edit_id').val(),
                    teacher_id: $('#edit_teacher_id').val(),
                    first_name: $('#edit_first_name').val(),
                    middle_name: $('#edit_middle_name').val(),
                    last_name: $('#edit_last_name').val(),
                    gender: $('#edit_gender').val(),
                    birthday: $('#edit_birthday').val(),
                    email: $('#edit_email').val(),
                    phone: $('#edit_phone').val(),
                    department: $('#edit_department').val()
                });
            } else {
                alert('Error: ' + (response.message || 'Failed to fetch teacher data'));
                $('#editTeacherModal').modal('hide');
            }
        },
        error: function(xhr, status, error) {
            // Remove loading indicator
            $('#loadingIndicator').remove();
            
            console.error('Error fetching teacher data:', error);
            console.error('Response:', xhr.responseText);
            alert('An error occurred while fetching teacher data');
            $('#editTeacherModal').modal('hide');
        }
    });
}

// Save edited teacher data
$(document).ready(function() {
    $('#saveEditTeacher').click(function() {
        // Get form data
        const formData = $('#editTeacherForm').serialize();
        console.log('Edit form data being submitted:', formData);
        
        // Show loading state
        $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
        $(this).prop('disabled', true);
        
        // Submit via AJAX
        $.ajax({
            url: 'edit.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                console.log('Server response:', response);
                if (response.success) {
                    // Show success message
                    $('#successModalMessage').text('Teacher updated successfully!');
                    
                    // Close edit modal
                    $('#editTeacherModal').modal('hide');
                    
                    // Show success modal
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    successModal.show();
                    
                    // Reload page when success modal is closed
                    $('#successModal').on('hidden.bs.modal', function () {
                        location.reload();
                    });
                } else {
                    alert('Error: ' + (response.message || 'Failed to update teacher'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Error updating teacher:', error);
                console.error('Response:', xhr.responseText);
                alert('An error occurred while updating the teacher');
            },
            complete: function() {
                // Reset button state
                $('#saveEditTeacher').html('<i class="fas fa-save me-2"></i> Save Changes');
                $('#saveEditTeacher').prop('disabled', false);
            }
        });
    });
}); 