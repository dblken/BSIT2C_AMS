<?php
session_start();
require_once '../../config/database.php';

// Check if JSON format is requested
$return_json = isset($_GET['format']) && $_GET['format'] === 'json';

if (isset($_GET['student_id'])) {
    try {
        $student_id = $_GET['student_id'];
        
        // Get student name
        $student_query = "SELECT CONCAT(first_name, ' ', last_name) as student_name 
                         FROM students 
                         WHERE id = ?";
        $stmt = $conn->prepare($student_query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $student_result = $stmt->get_result();
        $student_name = $student_result->fetch_assoc()['student_name'] ?? 'Student';
        
        // First, check what columns are available in the timetable
        $timetable_structure = $conn->query("DESCRIBE timetable");
        $day_column = null;
        $room_column = null;
        $has_room = false;
        
        while ($col = $timetable_structure->fetch_assoc()) {
            if (in_array($col['Field'], ['day', 'preferred_day', 'day_of_week'])) {
                $day_column = $col['Field'];
            }
            if (in_array($col['Field'], ['room', 'location'])) {
                $room_column = $col['Field'];
                $has_room = true;
            }
        }
        
        if (!$day_column) $day_column = 'day'; // Default fallback
        // No fallback for room_column - we'll use NULL if it doesn't exist
        
        // Build the query with the correct column names and conditional location
        $location_select = $has_room ? "tt.{$room_column} as location_value," : "NULL as location_value,";
        
        $query = "SELECT 
            e.id,
            s.subject_code,
            s.subject_name,
            tt.{$day_column} as day_value,
            tt.time_start,
            tt.time_end,
            $location_select
            a.id as assignment_id,
            a.location as assignment_location,
            a.preferred_day as assignment_preferred_day,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name
            FROM enrollments e
            JOIN timetable tt ON e.schedule_id = tt.id
            JOIN subjects s ON tt.subject_id = s.id
            LEFT JOIN assignments a ON (a.subject_id = s.id 
                                   AND (
                                        -- Handle string preferred_day matching timetable numeric day
                                        a.preferred_day = CAST(tt.{$day_column} AS CHAR)
                                        -- Handle JSON array of days containing the timetable's day value
                                        OR JSON_CONTAINS(a.preferred_day, CONCAT('\"', CASE 
                                            WHEN tt.{$day_column} = 0 THEN 'Sunday'
                                            WHEN tt.{$day_column} = 1 THEN 'Monday'
                                            WHEN tt.{$day_column} = 2 THEN 'Tuesday'
                                            WHEN tt.{$day_column} = 3 THEN 'Wednesday'
                                            WHEN tt.{$day_column} = 4 THEN 'Thursday'
                                            WHEN tt.{$day_column} = 5 THEN 'Friday'
                                            WHEN tt.{$day_column} = 6 THEN 'Saturday'
                                            ELSE CAST(tt.{$day_column} AS CHAR)
                                        END, '\"'))
                                   )
                                   AND a.time_start = tt.time_start 
                                   AND a.time_end = tt.time_end)
            LEFT JOIN teachers t ON a.teacher_id = t.id
            WHERE e.student_id = ?
            ORDER BY tt.{$day_column}, tt.time_start";
            
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $enrollments = [];
        while ($row = $result->fetch_assoc()) {
            $day_value = $row['day_value'] ?? '';
            
            // Check if day_value is a valid JSON array and decode it
            $decoded_days = @json_decode($day_value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_days)) {
                // Multiple days in JSON format
                $day_names = [];
                foreach ($decoded_days as $day_code) {
                    $day_names[] = [
                        'M' => 'Monday',
                        'T' => 'Tuesday',
                        'W' => 'Wednesday',
                        'TH' => 'Thursday',
                        'F' => 'Friday',
                        'SAT' => 'Saturday',
                        'SUN' => 'Sunday',
                        '1' => 'Monday',
                        '2' => 'Tuesday',
                        '3' => 'Wednesday',
                        '4' => 'Thursday',
                        '5' => 'Friday',
                        '6' => 'Saturday',
                        '0' => 'Sunday',
                        '7' => 'Sunday'
                    ][$day_code] ?? $day_code;
                }
                $day_name = implode(', ', $day_names);
            } else {
                // Single day format - handle numeric days (0=Sunday, 1=Monday, etc.)
                $numeric_days = [
                    0 => 'Sunday',
                    1 => 'Monday',
                    2 => 'Tuesday',
                    3 => 'Wednesday',
                    4 => 'Thursday',
                    5 => 'Friday',
                    6 => 'Saturday',
                ];
                
                // If day_value is numeric, use the numeric mapping
                if (is_numeric($day_value)) {
                    $day_name = $numeric_days[(int)$day_value] ?? $day_value;
                } else {
                    // Otherwise use the string mapping
                    $day_name = [
                        'M' => 'Monday',
                        'T' => 'Tuesday',
                        'W' => 'Wednesday',
                        'TH' => 'Thursday',
                        'F' => 'Friday',
                        'SAT' => 'Saturday',
                        'SUN' => 'Sunday',
                        '1' => 'Monday',
                        '2' => 'Tuesday',
                        '3' => 'Wednesday',
                        '4' => 'Thursday',
                        '5' => 'Friday',
                        '6' => 'Saturday',
                        '0' => 'Sunday',
                        '7' => 'Sunday'
                    ][$day_value] ?? $day_value;
                }
            }
            
            // Prefer assignment location if available, fall back to timetable location
            $location = !empty($row['assignment_location']) ? $row['assignment_location'] : 
                      (!empty($row['location_value']) ? $row['location_value'] : 'TBA');
            
            $enrollments[] = [
                'id' => $row['id'],
                'subject_code' => $row['subject_code'],
                'subject_name' => $row['subject_name'],
                'teacher' => $row['teacher_name'] ?: 'Not Assigned',
                'schedule' => $day_name . ', ' . 
                            date('h:i A', strtotime($row['time_start'])) . ' - ' .
                            date('h:i A', strtotime($row['time_end'])),
                'location' => $location,
                'status' => 'Enrolled' // Default status since the column doesn't exist
            ];
        }
        
        if ($return_json) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'enrollments' => $enrollments
            ]);
        } else {
            // Return HTML content
            ?>
            <div class="mb-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-info text-white me-3">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($student_name); ?></h5>
                        <p class="text-muted mb-0">Enrollment Record</p>
                    </div>
                </div>
                
                <div class="alert alert-light border mb-4">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-info-circle fa-2x text-info"></i>
                        </div>
                        <div>
                            <strong>Enrollment Summary</strong>
                            <p class="mb-0 small">This student is enrolled in <?php echo count($enrollments); ?> subject(s) for the current school year.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
                // Set student ID attribute for refreshing the modal
                document.getElementById('viewModalContent').setAttribute('data-student-id', '<?php echo $student_id; ?>');
            </script>
            
            <?php if (empty($enrollments)): ?>
                <div class="alert alert-warning">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                        </div>
                        <div>
                            <strong>No Enrollments Found</strong>
                            <p class="mb-0 small">This student is not enrolled in any subjects yet.</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover enrollment-table">
                        <thead class="table-light">
                            <tr>
                                <th width="22%">Subject</th>
                                <th width="18%">Teacher</th>
                                <th width="25%">Schedule</th>
                                <th width="15%">Location</th>
                                <th width="10%">Status</th>
                                <th width="10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrollments as $enrollment): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-primary border mb-1"><?php echo htmlspecialchars($enrollment['subject_code']); ?></span><br>
                                    <span class="small"><?php echo htmlspecialchars($enrollment['subject_name']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($enrollment['teacher']); ?></td>
                                <td><?php echo htmlspecialchars($enrollment['schedule']); ?></td>
                                <td><?php echo htmlspecialchars($enrollment['location']); ?></td>
                                <td><span class="badge bg-success">Enrolled</span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-danger w-100" 
                                           onclick="deleteEnrollment(<?php echo $enrollment['id']; ?>, '<?php echo htmlspecialchars(addslashes($enrollment['subject_code'] . ' - ' . $enrollment['subject_name']), ENT_QUOTES); ?>')"
                                           title="Remove this enrollment">
                                        <i class="fas fa-trash-alt me-1"></i> Remove
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <style>
                .avatar-circle {
                    width: 30px;
                    height: 30px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 0.8rem;
                    font-weight: bold;
                }
                
                .icon-circle {
                    width: 45px;
                    height: 45px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.2rem;
                }
                
                .enrollment-table {
                    width: 100%;
                    table-layout: fixed;
                }
                
                .enrollment-table th, 
                .enrollment-table td {
                    padding: 0.75rem 0.5rem;
                    vertical-align: middle;
                }
                
                .enrollment-table th {
                    font-weight: 600;
                }
                
                .enrollment-table td {
                    white-space: normal;
                    word-break: break-word;
                }
                
                .enrollment-table .badge {
                    font-size: 85%;
                    padding: 0.35em 0.65em;
                }
                
                @media (max-width: 768px) {
                    .enrollment-table {
                        min-width: 800px;
                    }
                }
                
                /* Give more room to see the content */
                .table-responsive {
                    padding: 0.5rem;
                    border-radius: 0.25rem;
                    overflow-x: auto;
                }
            </style>
            <?php
        }
        
    } catch (Exception $e) {
        if ($return_json) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error loading enrollments: ' . $e->getMessage()
            ]);
        } else {
            echo '<div class="alert alert-danger">
                   <i class="fas fa-exclamation-triangle me-2"></i>
                   Error loading enrollments: ' . htmlspecialchars($e->getMessage()) . '
                  </div>';
        }
    }
} else {
    if ($return_json) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Student ID is required'
        ]);
    } else {
        echo '<div class="alert alert-warning">
               <i class="fas fa-exclamation-circle me-2"></i>
               Student ID is required
              </div>';
    }
}
?> 