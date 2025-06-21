<?php
// Start session
session_start();
require_once '../../config/database.php';
require_once '../../includes/session_protection.php';

// Use the verify_session function to check admin session
verify_session('admin');

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=teacher_assignments_'.date('Y-m-d').'.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel encoding issues
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Report header
fputcsv($output, ['Report Type: Teacher Subject Assignments', date('Y-m-d h:i A')]);
fputcsv($output, ['']);

// Build the query for teacher assignments with student counts
$query = "
    SELECT 
        t.id as teacher_id,
        t.first_name,
        t.last_name,
        CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
        COUNT(DISTINCT a.id) as assignment_count
    FROM 
        teachers t
    LEFT JOIN 
        assignments a ON t.id = a.teacher_id
    GROUP BY 
        t.id, t.first_name, t.last_name
    ORDER BY 
        t.last_name, t.first_name
";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $teachers_result = $stmt->get_result();
    
    if ($teachers_result->num_rows > 0) {
        $teacher_number = 1;
        $total_teachers = $teachers_result->num_rows;
        $total_assignments = 0;
        
        while ($teacher = $teachers_result->fetch_assoc()) {
            // Add separator line between teachers
            if ($teacher_number > 1) {
                fputcsv($output, ['']);
                fputcsv($output, ['--------------------------------------------------']);
                fputcsv($output, ['']);
            }
            
            // Output teacher information
            fputcsv($output, ['TEACHER INFORMATION']);
            fputcsv($output, ['Teacher ID:', $teacher['teacher_id']]);
            fputcsv($output, ['Teacher Name:', $teacher['teacher_name']]);
            fputcsv($output, ['Assignment Count:', $teacher['assignment_count']]);
            fputcsv($output, ['']);
            
            // Get assignments for this teacher
            $assignments_query = "
                SELECT 
                    a.id as assignment_id,
                    s.subject_code,
                    s.subject_name,
                    CASE 
                        WHEN a.preferred_day REGEXP '^[0-9]+$' THEN 
                            CASE a.preferred_day
                                WHEN '1' THEN 'Monday'
                                WHEN '2' THEN 'Tuesday'
                                WHEN '3' THEN 'Wednesday'
                                WHEN '4' THEN 'Thursday'
                                WHEN '5' THEN 'Friday'
                                WHEN '6' THEN 'Saturday'
                                WHEN '7' THEN 'Sunday'
                                ELSE a.preferred_day
                            END
                        ELSE a.preferred_day
                    END as schedule_day,
                    TIME_FORMAT(a.time_start, '%h:%i %p') as start_time,
                    TIME_FORMAT(a.time_end, '%h:%i %p') as end_time,
                    a.location,
                    COUNT(DISTINCT e.student_id) as student_count
                FROM 
                    assignments a
                LEFT JOIN 
                    subjects s ON a.subject_id = s.id
                LEFT JOIN 
                    timetable tt ON s.id = tt.subject_id
                LEFT JOIN 
                    enrollments e ON tt.id = e.schedule_id
                WHERE 
                    a.teacher_id = ?
                GROUP BY 
                    a.id, s.subject_code, s.subject_name, schedule_day, a.time_start, a.time_end, a.location
                ORDER BY 
                    schedule_day, a.time_start, s.subject_code
            ";
            
            $assignments_stmt = $conn->prepare($assignments_query);
            $assignments_stmt->bind_param("i", $teacher['teacher_id']);
            $assignments_stmt->execute();
            $assignments_result = $assignments_stmt->get_result();
            
            if ($assignments_result->num_rows > 0) {
                // Output assignments table header
                fputcsv($output, ['SUBJECT ASSIGNMENTS']);
                fputcsv($output, ['#', 'Subject Code', 'Subject Name', 'Schedule', 'Location', 'Students']);
                
                $assignment_number = 1;
                while ($assignment = $assignments_result->fetch_assoc()) {
                    // Format schedule
                    $schedule = $assignment['schedule_day'] . ' ' . 
                                $assignment['start_time'] . ' - ' . 
                                $assignment['end_time'];
                    
                    // Write assignment row
                    fputcsv($output, [
                        $assignment_number,
                        $assignment['subject_code'],
                        $assignment['subject_name'],
                        $schedule,
                        $assignment['location'],
                        $assignment['student_count']
                    ]);
                    
                    $assignment_number++;
                    $total_assignments++;
                }
            } else {
                fputcsv($output, ['No subject assignments found for this teacher.']);
            }
            
            $teacher_number++;
        }
        
        // Write summary at the end
        fputcsv($output, ['']);
        fputcsv($output, ['--------------------------------------------------']);
        fputcsv($output, ['']);
        fputcsv($output, ['SUMMARY']);
        fputcsv($output, ['Total Teachers:', $total_teachers]);
        fputcsv($output, ['Total Assignments:', $total_assignments]);
        fputcsv($output, ['Average Assignments Per Teacher:', $total_teachers > 0 ? round($total_assignments / $total_teachers, 2) : 0]);
        fputcsv($output, ['Report Generated On:', date('Y-m-d h:i A')]);
        
    } else {
        // No data found - write a message
        fputcsv($output, ['No teacher assignments found in the system.']);
        fputcsv($output, ['Report Generated On:', date('Y-m-d h:i A')]);
    }
} catch (Exception $e) {
    // Write error to CSV
    fputcsv($output, ['Error generating report: ' . $e->getMessage()]);
    fputcsv($output, ['Report Generated On:', date('Y-m-d h:i A')]);
}

// Close the output stream
fclose($output);
exit;
?> 