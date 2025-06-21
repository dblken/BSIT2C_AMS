// Edit Teacher functionality
document.addEventListener('DOMContentLoaded', function() {
    // Set up edit button click handlers
    document.querySelectorAll('.edit-btn').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const teacherId = this.getAttribute('data-id');
            console.log("Edit button clicked for teacher ID:", teacherId);
            
            // Fetch teacher data
            fetch('get_teacher.php?id=' + teacherId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const teacher = data.teacher;
                        console.log("Teacher data:", teacher);
                        
                        // Populate edit form with all fields
                        document.getElementById('edit_id').value = teacher.id;
                        document.getElementById('edit_teacher_id').value = teacher.teacher_id;
                        document.getElementById('edit_first_name').value = teacher.first_name;
                        document.getElementById('edit_middle_name').value = teacher.middle_name || '';
                        document.getElementById('edit_last_name').value = teacher.last_name;
                        document.getElementById('edit_gender').value = teacher.gender;
                        document.getElementById('edit_email').value = teacher.email;
                        document.getElementById('edit_phone').value = teacher.phone || '';
                        document.getElementById('edit_department').value = teacher.department;
                        
                        // Show the edit modal
                        const editModal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
                        editModal.show();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while fetching teacher data.');
                });
        });
    });
    
    // Handle edit form submission
    const editForm = document.getElementById('editTeacherForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
            
            // Get form data
            const formData = new FormData(this);
            
            // Send AJAX request
            fetch('update_teacher.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert(data.message);
                    
                    // Close modal and refresh page
                    const editModal = bootstrap.Modal.getInstance(document.getElementById('editTeacherModal'));
                    editModal.hide();
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the teacher');
            })
            .finally(() => {
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
    
    // Edit from View Modal
    const editFromViewBtn = document.querySelector('#viewTeacherModal .btn-primary');
    if (editFromViewBtn) {
        editFromViewBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Get the teacher ID from the data attribute
            const teacherElement = document.getElementById('view_teacher_id_display');
            const teacherId = teacherElement.getAttribute('data-id');
            
            // Close view modal
            const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewTeacherModal'));
            viewModal.hide();
            
            if (teacherId) {
                // Wait for modal to close then trigger edit
                setTimeout(() => {
                    // Fetch teacher data directly
                    fetch('get_teacher.php?id=' + teacherId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const teacher = data.teacher;
                                
                                // Populate edit form with all fields
                                document.getElementById('edit_id').value = teacher.id;
                                document.getElementById('edit_teacher_id').value = teacher.teacher_id;
                                document.getElementById('edit_first_name').value = teacher.first_name;
                                document.getElementById('edit_middle_name').value = teacher.middle_name || '';
                                document.getElementById('edit_last_name').value = teacher.last_name;
                                document.getElementById('edit_gender').value = teacher.gender;
                                document.getElementById('edit_email').value = teacher.email;
                                document.getElementById('edit_phone').value = teacher.phone || '';
                                document.getElementById('edit_department').value = teacher.department;
                                
                                // Show the edit modal
                                const editModal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
                                editModal.show();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while fetching teacher data.');
                        });
                }, 500);
            } else {
                console.error("Unable to find teacher ID for editing");
            }
        });
    }
});

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
    
    // Get the current URL path
    const currentPath = window.location.pathname;
    const basePath = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
    const editUrl = basePath + 'edit.php';
    
    // Fetch teacher data
    $.ajax({
        url: editUrl,
        type: 'GET',
        data: { id: teacherId },
        dataType: 'json',
        success: function(response) {
            // Remove loading indicator
            $('#loadingIndicator').remove();
            
            if (response.success) {
                const teacher = response.teacher;
                
                if (!teacher) {
                    alert('Error: Teacher data not found');
                    $('#editTeacherModal').modal('hide');
                    return;
                }
                
                // Populate form fields
                $('#edit_id').val(teacher.id);
                $('#edit_teacher_id').val(teacher.teacher_id);
                $('#edit_first_name').val(teacher.first_name);
                $('#edit_middle_name').val(teacher.middle_name || '');
                $('#edit_last_name').val(teacher.last_name);
                $('#edit_gender').val(teacher.gender);
                $('#edit_birthday').val(teacher.birthday || '');
                $('#edit_email').val(teacher.email);
                $('#edit_phone').val(teacher.phone || '');
                $('#edit_department').val('IT Department');
                
                // Log populated values for debugging
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
$('#saveEditTeacher').click(function() {
    // Validate form
    if (!validateEditTeacherForm(document.getElementById('editTeacherForm'))) {
        return;
    }
    
    // Show loading state
    $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
    $(this).prop('disabled', true);
    
    // Get form data
    const formData = $('#editTeacherForm').serialize();
    
    // Submit via AJAX
    $.ajax({
        url: 'edit.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Show success message
                $('#successModalMessage').text('Teacher updated successfully!');
                $('#credentialsInfo').hide();
                
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