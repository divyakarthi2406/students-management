/**
 * Student Profile Management System
 * Interactive Client-Side Logic & Form Validation
 */

document.addEventListener('DOMContentLoaded', function () {
    initNavigation();
    initFileUploaders();
    initFormValidation();
    initAlertAutoDismiss();
});

// ==========================================================================
// 1. Mobile Navigation Menu Toggle
// ==========================================================================
function initNavigation() {
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');

    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function () {
            navMenu.classList.toggle('is-open');
        });

        // Close menu when clicking outside
        document.addEventListener('click', function (e) {
            if (!navToggle.contains(e.target) && !navMenu.contains(e.target)) {
                navMenu.classList.remove('is-open');
            }
        });
    }
}

// ==========================================================================
// 2. Client-Side File Upload Validation & Previews
// ==========================================================================
const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2 MB
const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

function formatBytesJs(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function initFileUploaders() {
    // ----------------------------------------------------------------------
    // A. Profile Image Preview & Validation
    // ----------------------------------------------------------------------
    const profileInput = document.getElementById('profile_image');
    const profilePreview = document.getElementById('profileImagePreview');
    const avatarPlaceholder = document.getElementById('avatarPlaceholder');

    if (profileInput) {
        profileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            // Validate Size
            if (file.size > MAX_FILE_SIZE) {
                alert(`File "${file.name}" exceeds the 2 MB limit (${formatBytesJs(file.size)}). Please choose a smaller file.`);
                this.value = '';
                return;
            }

            // Validate Image Extension
            const ext = file.name.split('.').pop().toLowerCase();
            if (!IMAGE_EXTENSIONS.includes(ext)) {
                alert(`Invalid file format ".${ext}". Profile image must be JPG, JPEG, PNG, or WEBP.`);
                this.value = '';
                return;
            }

            // Render Preview
            if (profilePreview) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    profilePreview.src = e.target.result;
                    profilePreview.style.display = 'block';
                    if (avatarPlaceholder) {
                        avatarPlaceholder.style.display = 'none';
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // ----------------------------------------------------------------------
    // B. ID Proof Validation & Dropzone
    // ----------------------------------------------------------------------
    const idProofInput = document.getElementById('id_proof');
    const idProofDropArea = document.getElementById('idProofDropArea');
    const idProofFileInfo = document.getElementById('idProofFileInfo');

    if (idProofInput) {
        idProofInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            if (file.size > MAX_FILE_SIZE) {
                alert(`File "${file.name}" exceeds the 2 MB limit (${formatBytesJs(file.size)}).`);
                this.value = '';
                if (idProofFileInfo) idProofFileInfo.style.display = 'none';
                return;
            }

            const ext = file.name.split('.').pop().toLowerCase();
            if (!ALLOWED_EXTENSIONS.includes(ext)) {
                alert(`Invalid format ".${ext}". Allowed formats: PDF, JPG, JPEG, PNG.`);
                this.value = '';
                if (idProofFileInfo) idProofFileInfo.style.display = 'none';
                return;
            }

            if (idProofFileInfo) {
                idProofFileInfo.textContent = `Selected: ${file.name} (${formatBytesJs(file.size)})`;
                idProofFileInfo.style.display = 'block';
            }
        });

        // Click on dropzone triggers file picker
        if (idProofDropArea) {
            idProofDropArea.addEventListener('click', () => idProofInput.click());
            setupDragAndDrop(idProofDropArea, idProofInput);
        }
    }

    // ----------------------------------------------------------------------
    // C. Certificates (Multiple Files) Validation & Dropzone
    // ----------------------------------------------------------------------
    const certInput = document.getElementById('certificates');
    const certDropArea = document.getElementById('certDropArea');
    const certFileList = document.getElementById('certFileList');

    if (certInput) {
        certInput.addEventListener('change', function () {
            validateAndListCertificates(this, certFileList);
        });

        if (certDropArea) {
            certDropArea.addEventListener('click', () => certInput.click());
            setupDragAndDrop(certDropArea, certInput);
        }
    }

    // ----------------------------------------------------------------------
    // D. Extra Files Quick Upload on View Page
    // ----------------------------------------------------------------------
    const extraFilesInput = document.getElementById('extra_files');
    if (extraFilesInput) {
        extraFilesInput.addEventListener('change', function () {
            const files = Array.from(this.files);
            for (const file of files) {
                if (file.size > MAX_FILE_SIZE) {
                    alert(`File "${file.name}" exceeds the 2 MB limit (${formatBytesJs(file.size)}).`);
                    this.value = '';
                    return;
                }
                const ext = file.name.split('.').pop().toLowerCase();
                if (!ALLOWED_EXTENSIONS.includes(ext)) {
                    alert(`Invalid file extension ".${ext}". Allowed: PDF, JPG, PNG, WEBP.`);
                    this.value = '';
                    return;
                }
            }
        });
    }
}

/**
 * Validates multiple certificate files and displays an itemized preview list.
 */
function validateAndListCertificates(inputElement, listContainer) {
    if (!listContainer) return;
    listContainer.innerHTML = '';

    const files = Array.from(inputElement.files);
    let hasError = false;

    for (const file of files) {
        if (file.size > MAX_FILE_SIZE) {
            alert(`File "${file.name}" exceeds the 2 MB limit (${formatBytesJs(file.size)}).`);
            hasError = true;
            break;
        }

        const ext = file.name.split('.').pop().toLowerCase();
        if (!ALLOWED_EXTENSIONS.includes(ext)) {
            alert(`File "${file.name}" has invalid format ".${ext}". Allowed: PDF, JPG, PNG, WEBP.`);
            hasError = true;
            break;
        }

        // Render preview item
        const item = document.createElement('div');
        item.className = 'file-item-preview';
        item.innerHTML = `
            <span><strong>${escapeHtml(file.name)}</strong></span>
            <span class="badge badge-gray">${formatBytesJs(file.size)}</span>
        `;
        listContainer.appendChild(item);
    }

    if (hasError) {
        inputElement.value = '';
        listContainer.innerHTML = '';
    }
}

/**
 * Attaches drag & drop events to a dropzone container.
 */
function setupDragAndDrop(dropArea, inputElement) {
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropArea.classList.add('is-dragover');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropArea.classList.remove('is-dragover');
        });
    });

    dropArea.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        if (dt && dt.files && dt.files.length > 0) {
            inputElement.files = dt.files;
            inputElement.dispatchEvent(new Event('change'));
        }
    });
}

// ==========================================================================
// 3. Form Validation (Client-Side)
// ==========================================================================
function initFormValidation() {
    const studentForms = document.querySelectorAll('#studentForm, #editStudentForm');

    studentForms.forEach(form => {
        form.addEventListener('submit', function (e) {
            let isValid = true;

            // Name validation
            const nameInput = form.querySelector('#name');
            if (nameInput) {
                if (!nameInput.value.trim()) {
                    setFieldError(nameInput, 'Student name is required.');
                    isValid = false;
                } else if (nameInput.value.trim().length > 100) {
                    setFieldError(nameInput, 'Name cannot exceed 100 characters.');
                    isValid = false;
                } else {
                    clearFieldError(nameInput);
                }
            }

            // Roll Number validation
            const rollInput = form.querySelector('#roll_number');
            if (rollInput) {
                if (!rollInput.value.trim()) {
                    setFieldError(rollInput, 'Roll number is required.');
                    isValid = false;
                } else if (rollInput.value.trim().length > 30) {
                    setFieldError(rollInput, 'Roll number cannot exceed 30 characters.');
                    isValid = false;
                } else {
                    clearFieldError(rollInput);
                }
            }

            // Email validation (optional but validated if entered)
            const emailInput = form.querySelector('#email');
            if (emailInput && emailInput.value.trim()) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailInput.value.trim())) {
                    setFieldError(emailInput, 'Please provide a valid email address.');
                    isValid = false;
                } else {
                    clearFieldError(emailInput);
                }
            }

            // Course validation
            const courseInput = form.querySelector('#course');
            if (courseInput) {
                if (!courseInput.value.trim()) {
                    setFieldError(courseInput, 'Please specify the student\'s course.');
                    isValid = false;
                } else {
                    clearFieldError(courseInput);
                }
            }

            if (!isValid) {
                e.preventDefault();
                // Scroll to first invalid field
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });
    });
}

function setFieldError(input, message) {
    input.classList.add('is-invalid');
    const feedback = input.parentElement.querySelector('.form-feedback');
    if (feedback) {
        feedback.textContent = message;
        feedback.classList.add('is-visible');
    }
}

function clearFieldError(input) {
    input.classList.remove('is-invalid');
    const feedback = input.parentElement.querySelector('.form-feedback');
    if (feedback) {
        feedback.textContent = '';
        feedback.classList.remove('is-visible');
    }
}

// ==========================================================================
// 4. Global Confirmation Modal Dialog
// ==========================================================================
function confirmDeleteStudent(studentId, studentName, rollNumber, fileCount) {
    const modal = document.getElementById('confirmModal');
    if (!modal) return;

    document.getElementById('modalTitle').textContent = 'Delete Student Profile?';
    document.getElementById('modalMessage').innerHTML = `
        Are you sure you want to delete student <strong>${escapeHtml(studentName)}</strong> (Roll: <strong>${escapeHtml(rollNumber)}</strong>)?
    `;

    const detailsBox = document.getElementById('modalDetails');
    detailsBox.style.display = 'block';
    detailsBox.innerHTML = `
        <span class="text-danger"><strong>Warning:</strong> All <strong>${fileCount}</strong> uploaded document(s) and profile photos associated with this student will be permanently deleted from the server.</span>
    `;

    const form = document.getElementById('modalActionForm');
    form.action = 'delete.php';
    document.getElementById('modalTargetId').value = studentId;

    // Reset action mode to student
    let actionInput = form.querySelector('input[name="action"]');
    if (actionInput) actionInput.remove();

    document.getElementById('modalConfirmBtn').textContent = 'Delete Student Permanently';
    openModal();
}

function confirmDeleteFile(fileId, fileName, studentId) {
    const modal = document.getElementById('confirmModal');
    if (!modal) return;

    document.getElementById('modalTitle').textContent = 'Delete Attached File?';
    document.getElementById('modalMessage').innerHTML = `
        Are you sure you want to permanently delete document <strong>${escapeHtml(fileName)}</strong>?
    `;

    const detailsBox = document.getElementById('modalDetails');
    detailsBox.style.display = 'block';
    detailsBox.innerHTML = `
        <span class="text-danger">This file will be permanently removed from server disk storage and database metadata.</span>
    `;

    const form = document.getElementById('modalActionForm');
    form.action = 'delete.php';
    document.getElementById('modalTargetId').value = fileId;

    // Add action=file input
    let actionInput = form.querySelector('input[name="action"]');
    if (!actionInput) {
        actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        form.appendChild(actionInput);
    }
    actionInput.value = 'file';

    // Add student_id return input
    let returnInput = form.querySelector('input[name="student_id"]');
    if (!returnInput) {
        returnInput = document.createElement('input');
        returnInput.type = 'hidden';
        returnInput.name = 'student_id';
        form.appendChild(returnInput);
    }
    returnInput.value = studentId;

    document.getElementById('modalConfirmBtn').textContent = 'Delete File';
    openModal();
}

function openModal() {
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.classList.add('is-active');
        modal.setAttribute('aria-hidden', 'false');
    }
}

function closeModal() {
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.classList.remove('is-active');
        modal.setAttribute('aria-hidden', 'true');
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Close modal when clicking outside dialog
document.addEventListener('click', function (e) {
    const modal = document.getElementById('confirmModal');
    if (modal && e.target === modal) {
        closeModal();
    }
});

// ==========================================================================
// 5. Alert Auto Dismiss
// ==========================================================================
function initAlertAutoDismiss() {
    const alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
