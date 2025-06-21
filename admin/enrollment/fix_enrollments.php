<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/admin_header.php';
?>

<style>
    .fix-card {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
    }
    .fix-card:hover {
        transform: translateY(-5px);
    }
    .log-container {
        max-height: 400px;
        overflow-y: auto;
        background-color: #f8f9fa;
        border-radius: 5px;
        padding: 15px;
        font-family: monospace;
    }
    .log-entry {
        margin-bottom: 5px;
    }
    .log-success {
        color: #28a745;
    }
    .log-error {
        color: #dc3545;
    }
    .log-warning {
        color: #ffc107;
    }
    .log-info {
        color: #17a2b8;
    }
    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
    }
    .status-badge i {
        margin-right: 5px;
    }
    .status-ok {
        background-color: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }
    .status-warning {
        background-color: rgba(255, 193, 7, 0.1);
        color: #ffc107;
    }
    .status-error {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white p-4">
                    <h2 class="mb-0"><i class="fas fa-tools me-2"></i> Fix Enrollment Issues</h2>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        This tool helps fix enrollment issues by creating missing timetable entries and checking for inconsistencies in the enrollment data.
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card fix-card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-calendar-plus text-primary me-2"></i> Create Missing Timetable Entries</h5>
                                    <p class="text-muted">Create timetable entries for all assignments without them.</p>
                                    <button id="createTimetableEntries" class="btn btn-primary"><i class="fas fa-sync me-2"></i> Create Entries</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card fix-card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-user-check text-success me-2"></i> Fix Enrollment Records</h5>
                                    <p class="text-muted">Update enrollment records to link with correct timetable entries.</p>
                                    <button id="fixEnrollmentRecords" class="btn btn-success"><i class="fas fa-wrench me-2"></i> Fix Enrollments</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-clipboard-check me-2"></i> System Status</h5>
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="text-muted mb-3">Assignments Without Timetable</h6>
                                    <span id="missingTimetableCount" class="status-badge status-warning">
                                        <i class="fas fa-sync fa-spin"></i> Checking...
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="text-muted mb-3">Enrollments Without Schedule</h6>
                                    <span id="missingScheduleCount" class="status-badge status-warning">
                                        <i class="fas fa-sync fa-spin"></i> Checking...
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="text-muted mb-3">Data Consistency</h6>
                                    <span id="dataConsistency" class="status-badge status-warning">
                                        <i class="fas fa-sync fa-spin"></i> Checking...
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-terminal me-2"></i> Log Output</h5>
                    <div class="log-container" id="logContainer">
                        <div class="log-entry log-info">[INFO] Starting system check...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

<script>
$(document).ready(function() {
    const logContainer = $('#logContainer');
    
    // Helper function to add log entries
    function addLog(message, type = 'info') {
        const logClass = `log-${type}`;
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = `<div class="log-entry ${logClass}">[${timestamp}] ${message}</div>`;
        logContainer.append(logEntry);
        logContainer.scrollTop(logContainer[0].scrollHeight);
    }
    
    // Function to update status badges
    function updateStatusBadge(element, count, isGood) {
        const badge = $(element);
        
        if (isGood) {
            badge.removeClass('status-warning status-error').addClass('status-ok');
            badge.html(`<i class="fas fa-check-circle"></i> ${count}`);
        } else {
            badge.removeClass('status-ok status-warning').addClass('status-error');
            badge.html(`<i class="fas fa-exclamation-triangle"></i> ${count}`);
        }
    }
    
    // Check system status on page load
    checkSystemStatus();
    
    function checkSystemStatus() {
        addLog('Checking system status...', 'info');
        
        $.ajax({
            url: 'check_system_status.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    addLog('System status check completed.', 'success');
                    
                    // Update missing timetable count
                    updateStatusBadge('#missingTimetableCount', response.missing_timetable_count, response.missing_timetable_count === 0);
                    if (response.missing_timetable_count > 0) {
                        addLog(`Found ${response.missing_timetable_count} assignments without timetable entries.`, 'warning');
                    } else {
                        addLog('All assignments have timetable entries.', 'success');
                    }
                    
                    // Update missing schedule count
                    updateStatusBadge('#missingScheduleCount', response.missing_schedule_count, response.missing_schedule_count === 0);
                    if (response.missing_schedule_count > 0) {
                        addLog(`Found ${response.missing_schedule_count} enrollments without proper schedule links.`, 'warning');
                    } else {
                        addLog('All enrollments have proper schedule links.', 'success');
                    }
                    
                    // Update data consistency
                    const consistencyStatus = response.inconsistencies === 0;
                    updateStatusBadge('#dataConsistency', 
                        consistencyStatus ? 'Good' : `${response.inconsistencies} issues`, 
                        consistencyStatus);
                    
                    if (response.inconsistencies > 0) {
                        addLog(`Found ${response.inconsistencies} data inconsistencies.`, 'warning');
                        
                        if (response.details && response.details.length > 0) {
                            addLog('Issues found:', 'info');
                            response.details.forEach(detail => {
                                addLog(`• ${detail}`, 'warning');
                            });
                        }
                    } else {
                        addLog('All data is consistent.', 'success');
                    }
                } else {
                    addLog(`Error checking system status: ${response.message}`, 'error');
                }
            },
            error: function(xhr, status, error) {
                addLog(`AJAX error: ${error}`, 'error');
                console.error('AJAX error:', xhr.responseText);
            }
        });
    }
    
    // Create timetable entries button
    $('#createTimetableEntries').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Creating...');
        
        addLog('Creating timetable entries...', 'info');
        
        $.ajax({
            url: 'create_timetable_entries.php',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    addLog('Timetable entries created successfully.', 'success');
                    addLog(`Created ${response.created_count} timetable entries.`, 'success');
                    
                    if (response.error_count > 0) {
                        addLog(`${response.error_count} errors occurred during the process.`, 'warning');
                        
                        if (response.errors && response.errors.length > 0) {
                            addLog('Error details:', 'info');
                            response.errors.forEach(error => {
                                addLog(`• ${error}`, 'error');
                            });
                        }
                    }
                    
                    // Refresh status after operation
                    checkSystemStatus();
                } else {
                    addLog(`Error: ${response.message}`, 'error');
                }
                
                btn.prop('disabled', false).html('<i class="fas fa-sync me-2"></i> Create Entries');
            },
            error: function(xhr, status, error) {
                addLog(`AJAX error: ${error}`, 'error');
                console.error('AJAX error:', xhr.responseText);
                btn.prop('disabled', false).html('<i class="fas fa-sync me-2"></i> Create Entries');
            }
        });
    });
    
    // Fix enrollment records button
    $('#fixEnrollmentRecords').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Fixing...');
        
        addLog('Fixing enrollment records...', 'info');
        
        $.ajax({
            url: 'fix_enrollment_records.php',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    addLog('Enrollment records fixed successfully.', 'success');
                    addLog(`Fixed ${response.fixed_count} enrollment records.`, 'success');
                    
                    if (response.error_count > 0) {
                        addLog(`${response.error_count} errors occurred during the process.`, 'warning');
                        
                        if (response.errors && response.errors.length > 0) {
                            addLog('Error details:', 'info');
                            response.errors.forEach(error => {
                                addLog(`• ${error}`, 'error');
                            });
                        }
                    }
                    
                    // Refresh status after operation
                    checkSystemStatus();
                } else {
                    addLog(`Error: ${response.message}`, 'error');
                }
                
                btn.prop('disabled', false).html('<i class="fas fa-wrench me-2"></i> Fix Enrollments');
            },
            error: function(xhr, status, error) {
                addLog(`AJAX error: ${error}`, 'error');
                console.error('AJAX error:', xhr.responseText);
                btn.prop('disabled', false).html('<i class="fas fa-wrench me-2"></i> Fix Enrollments');
            }
        });
    });
});
</script> 