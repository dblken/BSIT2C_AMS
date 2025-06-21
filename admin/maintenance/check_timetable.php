<?php
require_once '../../config/database.php';
session_start();

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
    .maintenance-card {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white p-4">
                    <h2 class="mb-0"><i class="fas fa-tools me-2"></i> Timetable Maintenance</h2>
                </div>
                <div class="card-body p-4">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card maintenance-card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-check-circle text-success me-2"></i> Check Timetable</h5>
                                    <p class="text-muted">Check for missing or invalid timetable entries for assignments.</p>
                                    <button id="checkTimetable" class="btn btn-primary"><i class="fas fa-search me-2"></i> Check Now</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card maintenance-card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-sync text-warning me-2"></i> Fix Timetable Entries</h5>
                                    <p class="text-muted">Recreate timetable entries for all assignments.</p>
                                    <button id="fixTimetable" class="btn btn-warning"><i class="fas fa-wrench me-2"></i> Fix Entries</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="log-container" id="logContainer">
                        <div class="log-entry log-info">[INFO] Ready to check timetable entries. Click on a button above to begin.</div>
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
    
    // Check timetable button
    $('#checkTimetable').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Checking...');
        
        addLog('Starting timetable check...', 'info');
        
        $.ajax({
            url: 'check_timetable_ajax.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    addLog('Timetable check completed successfully.', 'success');
                    
                    if (response.missing_entries > 0) {
                        addLog(`Found ${response.missing_entries} assignments without timetable entries.`, 'warning');
                    } else {
                        addLog('All assignments have timetable entries.', 'success');
                    }
                    
                    if (response.inconsistent_entries > 0) {
                        addLog(`Found ${response.inconsistent_entries} inconsistent timetable entries.`, 'warning');
                    } else {
                        addLog('All timetable entries are consistent with their assignments.', 'success');
                    }
                    
                    if (response.orphaned_entries > 0) {
                        addLog(`Found ${response.orphaned_entries} orphaned timetable entries.`, 'warning');
                    } else {
                        addLog('No orphaned timetable entries found.', 'success');
                    }
                    
                    // Add detailed logs if available
                    if (response.details && response.details.length > 0) {
                        addLog('Detailed issues:', 'info');
                        response.details.forEach(detail => {
                            addLog(`• ${detail}`, 'warning');
                        });
                    }
                    
                } else {
                    addLog(`Error: ${response.message}`, 'error');
                }
                
                btn.prop('disabled', false).html('<i class="fas fa-search me-2"></i> Check Now');
            },
            error: function(xhr, status, error) {
                addLog(`AJAX error: ${error}`, 'error');
                console.error('AJAX error:', xhr.responseText);
                btn.prop('disabled', false).html('<i class="fas fa-search me-2"></i> Check Now');
            }
        });
    });
    
    // Fix timetable button
    $('#fixTimetable').click(function() {
        if (!confirm('This will recreate timetable entries for all assignments. Continue?')) {
            return;
        }
        
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Fixing...');
        
        addLog('Starting timetable fix...', 'info');
        
        $.ajax({
            url: 'fix_timetable_ajax.php',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    addLog('Timetable fix completed successfully.', 'success');
                    addLog(`${response.updated_count} assignments updated with timetable entries.`, 'success');
                    
                    if (response.deleted_count > 0) {
                        addLog(`${response.deleted_count} orphaned timetable entries deleted.`, 'info');
                    }
                    
                    if (response.error_count > 0) {
                        addLog(`${response.error_count} errors occurred during the process.`, 'warning');
                        
                        if (response.errors && response.errors.length > 0) {
                            addLog('Error details:', 'info');
                            response.errors.forEach(error => {
                                addLog(`• ${error}`, 'error');
                            });
                        }
                    }
                } else {
                    addLog(`Error: ${response.message}`, 'error');
                }
                
                btn.prop('disabled', false).html('<i class="fas fa-wrench me-2"></i> Fix Entries');
            },
            error: function(xhr, status, error) {
                addLog(`AJAX error: ${error}`, 'error');
                console.error('AJAX error:', xhr.responseText);
                btn.prop('disabled', false).html('<i class="fas fa-wrench me-2"></i> Fix Entries');
            }
        });
    });
});
</script> 