        </div>
    </div>
</div>

<!-- Bootstrap JS and dependencies -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Performance Optimization Script -->
<script>
    // Performance monitoring and optimization
    document.addEventListener('DOMContentLoaded', function() {
        // Cache DOM lookups for better performance
        const forms = document.querySelectorAll('form');
        
        // Optimize all forms to prevent double submission
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                // Store the submit button
                const submitBtn = form.querySelector('button[type="submit"]');
                
                // Skip if already submitting
                if (submitBtn && submitBtn.getAttribute('data-submitting') === 'true') {
                    e.preventDefault();
                    return false;
                }
                
                // Mark as submitting
                if (submitBtn) {
                    submitBtn.setAttribute('data-submitting', 'true');
                    
                    // Reset after 10 seconds as a failsafe
                    setTimeout(() => {
                        submitBtn.removeAttribute('data-submitting');
                    }, 10000);
                }
            });
        });
        
        // Optimize modal dialogs to improve performance
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            // Show loading state when modal opens for better UX
            modal.addEventListener('show.bs.modal', function() {
                const modalForm = modal.querySelector('form');
                if (modalForm) {
                    // Reset form when modal is opened
                    modalForm.reset();
                }
                
                // Preload data if needed
                if (modal.id === 'addAssignmentModal') {
                    // Focus on first input to improve usability
                    setTimeout(() => {
                        const firstInput = modal.querySelector('input:not([type="hidden"]):not([disabled])');
                        if (firstInput) firstInput.focus();
                    }, 500);
                }
            });
        });
        
        // Performance logging for development
        if (localStorage.getItem('dev_mode') === 'true') {
            console.log('Page load time:', performance.now(), 'ms');
        }
    });
</script>
</body>
</html> 