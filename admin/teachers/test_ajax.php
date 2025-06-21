<!DOCTYPE html>
<html>
<head>
    <title>Test Teacher Edit AJAX</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>Test Teacher Edit AJAX</h1>
    
    <div>
        <label for="teacher_id">Enter Teacher ID to Test:</label>
        <input type="text" id="teacher_id" value="1">
        <button id="test_btn">Test Fetch Teacher</button>
    </div>
    
    <hr>
    
    <h2>Results:</h2>
    <div id="results" style="white-space: pre; font-family: monospace; background: #eee; padding: 10px;"></div>
    
    <hr>
    
    <h2>Form Values After Population:</h2>
    
    <form id="test_form">
        <div>
            <label>ID:</label>
            <input type="text" id="edit_id" name="id">
        </div>
        <div>
            <label>Teacher ID:</label>
            <input type="text" id="edit_teacher_id" name="teacher_id">
        </div>
        <div>
            <label>First Name:</label>
            <input type="text" id="edit_first_name" name="first_name">
        </div>
        <div>
            <label>Middle Name:</label>
            <input type="text" id="edit_middle_name" name="middle_name">
        </div>
        <div>
            <label>Last Name:</label>
            <input type="text" id="edit_last_name" name="last_name">
        </div>
        <div>
            <label>Gender:</label>
            <select id="edit_gender" name="gender">
                <option value="">Select Gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>
        </div>
        <div>
            <label>Email:</label>
            <input type="email" id="edit_email" name="email">
        </div>
        <div>
            <label>Phone:</label>
            <input type="text" id="edit_phone" name="phone">
        </div>
        <div>
            <label>Department:</label>
            <input type="text" id="edit_department" name="department">
        </div>
    </form>
    
    <script>
        $(document).ready(function() {
            $('#test_btn').click(function() {
                const teacherId = $('#teacher_id').val();
                $('#results').html('Fetching data for teacher ID: ' + teacherId + '...\n');
                
                $.ajax({
                    url: 'edit.php',
                    type: 'GET',
                    data: { id: teacherId },
                    dataType: 'json',
                    success: function(response) {
                        $('#results').append('Response received:\n' + JSON.stringify(response, null, 2) + '\n');
                        
                        if (response.success) {
                            const teacher = response.teacher;
                            $('#results').append('Populating form with teacher data...\n');
                            
                            // Populate test form
                            $('#edit_id').val(teacher.id);
                            $('#edit_teacher_id').val(teacher.teacher_id);
                            $('#edit_first_name').val(teacher.first_name);
                            $('#edit_middle_name').val(teacher.middle_name);
                            $('#edit_last_name').val(teacher.last_name);
                            $('#edit_gender').val(teacher.gender);
                            $('#edit_email').val(teacher.email);
                            $('#edit_phone').val(teacher.phone);
                            $('#edit_department').val(teacher.department);
                            
                            $('#results').append('Form populated successfully!\n');
                        } else {
                            $('#results').append('Error: ' + (response.message || 'Unknown error') + '\n');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#results').append('AJAX Error:\n');
                        $('#results').append('Status: ' + status + '\n');
                        $('#results').append('Error: ' + error + '\n');
                        $('#results').append('Response Text: ' + xhr.responseText + '\n');
                    }
                });
            });
        });
    </script>
</body>
</html> 