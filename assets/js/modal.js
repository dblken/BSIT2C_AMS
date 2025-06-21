class Modal {
    constructor() {
        this.activeModal = null;
        this.init();
    }

    init() {
        // Close modal when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.closeActiveModal();
            }
        });

        // Close modal when pressing ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.activeModal) {
                this.closeActiveModal();
            }
        });
    }

    createModal(title, content) {
        const modalHTML = `
            <div class="modal-overlay">
                <div class="modal-container">
                    <div class="modal-header">
                        <h3>${title}</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                </div>
            </div>
        `;

        const modalElement = document.createRange().createContextualFragment(modalHTML);
        document.body.appendChild(modalElement);

        const modal = document.body.lastElementChild;
        const closeBtn = modal.querySelector('.modal-close');
        
        closeBtn.addEventListener('click', () => this.closeActiveModal());

        return modal;
    }

    openModal(modal) {
        if (this.activeModal) {
            this.closeActiveModal();
        }
        
        this.activeModal = modal;
        requestAnimationFrame(() => {
            modal.classList.add('active');
            modal.querySelector('.modal-container').classList.add('active');
        });
    }

    closeActiveModal() {
        if (!this.activeModal) return;

        const modalContainer = this.activeModal.querySelector('.modal-container');
        modalContainer.classList.remove('active');
        this.activeModal.classList.remove('active');

        setTimeout(() => {
            this.activeModal.remove();
            this.activeModal = null;
        }, 500);
    }
}

// Initialize modal handler
const modalHandler = new Modal();

// Function to create and open specific modals
function openAddSubjectModal() {
    const content = `
        <form id="addSubjectForm" onsubmit="handleFormSubmit(event, 'subject')">
            <div class="form-group">
                <label for="subject_code">Subject Code</label>
                <input type="text" class="form-control" id="subject_code" name="subject_code" required>
            </div>
            <div class="form-group">
                <label for="subject_name">Subject Name</label>
                <input type="text" class="form-control" id="subject_name" name="subject_name" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="modalHandler.closeActiveModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Subject</button>
            </div>
        </form>
    `;
    
    const modal = modalHandler.createModal('Add New Subject', content);
    modalHandler.openModal(modal);
}

function openAssignTeacherModal() {
    const content = `
        <form id="assignTeacherForm" onsubmit="handleFormSubmit(event, 'assign')">
            <div class="form-group">
                <label for="teacher_id">Select Teacher</label>
                <select class="form-control" id="teacher_id" name="teacher_id" required>
                    <!-- Populate with PHP -->
                </select>
            </div>
            <div class="form-group">
                <label for="subject_id">Select Subject</label>
                <select class="form-control" id="subject_id" name="subject_id" required>
                    <!-- Populate with PHP -->
                </select>
            </div>
            <div class="form-group">
                <label for="weekly_classes">Weekly Classes</label>
                <input type="number" class="form-control" id="weekly_classes" name="weekly_classes" required min="1">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="modalHandler.closeActiveModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Teacher</button>
            </div>
        </form>
    `;
    
    const modal = modalHandler.createModal('Assign Teacher to Subject', content);
    modalHandler.openModal(modal);
}

// Similar functions for register teacher and student modals... 