<?php
/**
 * Student Profile Management System
 * Add New Student Page
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/upload.php';

$pageTitle = 'Add New Student';
$activePage = 'add';

$errors = [];
$name = '';
$rollNumber = '';
$email = '';
$course = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $errors[] = "Security check failed. Please refresh and try submitting again.";
    }

    // 2. Sanitize & Retrieve Input
    $name = trim($_POST['name'] ?? '');
    $rollNumber = trim($_POST['roll_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course = trim($_POST['course'] ?? '');

    // 3. Validate Inputs
    if (empty($name)) {
        $errors[] = "Student Full Name is required.";
    } elseif (mb_strlen($name) > 100) {
        $errors[] = "Student Full Name cannot exceed 100 characters.";
    }

    if (empty($rollNumber)) {
        $errors[] = "Roll Number is required.";
    } elseif (mb_strlen($rollNumber) > 30) {
        $errors[] = "Roll Number cannot exceed 30 characters.";
    }

    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please provide a valid email address.";
        } elseif (mb_strlen($email) > 100) {
            $errors[] = "Email cannot exceed 100 characters.";
        }
    }

    if (empty($course)) {
        $errors[] = "Course name is required.";
    } elseif (mb_strlen($course) > 100) {
        $errors[] = "Course cannot exceed 100 characters.";
    }

    // 4. Check for Unique Roll Number in Database
    if (empty($errors)) {
        try {
            $checkStmt = $pdo->prepare("SELECT id FROM students WHERE roll_number = ? LIMIT 1");
            $checkStmt->execute([$rollNumber]);
            if ($checkStmt->fetch()) {
                $errors[] = "A student with Roll Number '{$rollNumber}' is already registered. Roll number must be unique.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error while verifying roll number: " . $e->getMessage();
        }
    }

    // 5. Pre-validate Uploaded Files before saving
    $profileFile = (!empty($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) ? $_FILES['profile_image'] : null;
    $idProofFile = (!empty($_FILES['id_proof']) && $_FILES['id_proof']['error'] !== UPLOAD_ERR_NO_FILE) ? $_FILES['id_proof'] : null;
    $certFiles   = !empty($_FILES['certificates']) ? normalizeFilesArray($_FILES['certificates']) : [];

    if ($profileFile) {
        $errMsg = '';
        if (!validateUploadedFile($profileFile, 'image', $errMsg)) {
            $errors[] = "Profile Image: " . $errMsg;
        }
    }

    if ($idProofFile) {
        $errMsg = '';
        if (!validateUploadedFile($idProofFile, null, $errMsg)) {
            $errors[] = "ID Proof: " . $errMsg;
        }
    }

    foreach ($certFiles as $idx => $certFile) {
        $errMsg = '';
        if (!validateUploadedFile($certFile, null, $errMsg)) {
            $errors[] = "Certificate #" . ($idx + 1) . ": " . $errMsg;
        }
    }

    // 6. Process Database Insertion & File Storage in a Transaction
    if (empty($errors)) {
        $savedDiskFiles = [];
        try {
            $pdo->beginTransaction();

            // Insert Student Record
            $insertStudentStmt = $pdo->prepare("
                INSERT INTO students (name, roll_number, email, course, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $insertStudentStmt->execute([
                $name,
                $rollNumber,
                !empty($email) ? $email : null,
                $course
            ]);
            $studentId = (int)$pdo->lastInsertId();

            // Prepare File Record Statement
            $insertFileStmt = $pdo->prepare("
                INSERT INTO student_files (student_id, original_name, stored_name, file_path, file_type, file_size, uploaded_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            // Process Profile Image
            if ($profileFile) {
                $savedData = [];
                $fileErrMsg = '';
                if (!saveUploadedFile($profileFile, 'profile', $studentId, $savedData, $fileErrMsg)) {
                    throw new Exception("Profile Image: " . $fileErrMsg);
                }
                $savedDiskFiles[] = $savedData['full_path'];
                $insertFileStmt->execute([
                    $studentId,
                    $savedData['original_name'],
                    $savedData['stored_name'],
                    $savedData['file_path'],
                    $savedData['file_type'],
                    $savedData['file_size']
                ]);
            }

            // Process ID Proof
            if ($idProofFile) {
                $savedData = [];
                $fileErrMsg = '';
                if (!saveUploadedFile($idProofFile, 'id_proof', $studentId, $savedData, $fileErrMsg)) {
                    throw new Exception("ID Proof: " . $fileErrMsg);
                }
                $savedDiskFiles[] = $savedData['full_path'];
                $insertFileStmt->execute([
                    $studentId,
                    $savedData['original_name'],
                    $savedData['stored_name'],
                    $savedData['file_path'],
                    $savedData['file_type'],
                    $savedData['file_size']
                ]);
            }

            // Process Multiple Certificates
            foreach ($certFiles as $idx => $certFile) {
                $savedData = [];
                $fileErrMsg = '';
                if (!saveUploadedFile($certFile, 'cert', $studentId, $savedData, $fileErrMsg)) {
                    throw new Exception("Certificate #" . ($idx + 1) . ": " . $fileErrMsg);
                }
                $savedDiskFiles[] = $savedData['full_path'];
                $insertFileStmt->execute([
                    $studentId,
                    $savedData['original_name'],
                    $savedData['stored_name'],
                    $savedData['file_path'],
                    $savedData['file_type'],
                    $savedData['file_size']
                ]);
            }

            // Commit Transaction
            $pdo->commit();

            setFlash('success', "Student '{$name}' was successfully enrolled with " . count($savedDiskFiles) . " uploaded file(s).");
            header("Location: view.php?id=" . $studentId);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            cleanupFiles($savedDiskFiles);
            $errors[] = "Enrollment failed: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1 class="page-title">Register New Student</h1>
        <p class="page-subtitle">Fill in personal details and upload required academic documentation</p>
    </div>
    <div class="page-actions">
        <a href="students.php" class="btn btn-outline">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Directory
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error mb-4">
        <div class="alert-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        </div>
        <div class="alert-message">
            <strong>Please correct the following errors:</strong>
            <ul class="error-list mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="form-container">
    <form method="POST" action="add.php" enctype="multipart/form-data" class="student-form" id="studentForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">

        <!-- Section 1: Basic Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>1. Student Information</h3>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <!-- Full Name -->
                    <div class="form-group">
                        <label for="name" class="form-label required">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control" required maxlength="100" placeholder="e.g. Rahul Sharma" value="<?= e($name) ?>">
                        <span class="form-feedback" id="nameFeedback"></span>
                    </div>

                    <!-- Roll Number -->
                    <div class="form-group">
                        <label for="roll_number" class="form-label required">Roll Number</label>
                        <input type="text" id="roll_number" name="roll_number" class="form-control" required maxlength="30" placeholder="e.g. CS2026-042" value="<?= e($rollNumber) ?>">
                        <span class="form-hint">Must be unique for each student.</span>
                        <span class="form-feedback" id="rollFeedback"></span>
                    </div>

                    <!-- Email Address -->
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" maxlength="100" placeholder="e.g. rahul.sharma@example.com" value="<?= e($email) ?>">
                        <span class="form-hint">Optional, but recommended for communication.</span>
                        <span class="form-feedback" id="emailFeedback"></span>
                    </div>

                    <!-- Course -->
                    <div class="form-group">
                        <label for="course" class="form-label required">Course / Degree</label>
                        <input type="text" id="course" name="course" list="courseSuggestions" class="form-control" required maxlength="100" placeholder="Select or type course..." value="<?= e($course) ?>">
                        <datalist id="courseSuggestions">
                            <option value="B.Tech Computer Science &amp; Engineering">
                            <option value="B.Tech Information Technology">
                            <option value="BCA (Bachelor of Computer Applications)">
                            <option value="B.Sc Computer Science">
                            <option value="MCA (Master of Computer Applications)">
                            <option value="M.Tech Computer Science">
                            <option value="MBA Information Systems">
                            <option value="B.Tech Electronics &amp; Communication">
                        </datalist>
                        <span class="form-hint">Choose from recommendations or enter custom degree.</span>
                        <span class="form-feedback" id="courseFeedback"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Document Uploads -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-header-title">
                    <h3>2. Documents &amp; Attachments</h3>
                    <span class="badge badge-info">Max 2 MB / file &bull; JPG, PNG, WEBP, PDF</span>
                </div>
            </div>
            <div class="card-body">
                <div class="upload-grid">
                    <!-- Profile Photo Upload -->
                    <div class="upload-card">
                        <div class="upload-card-header">
                            <label class="form-label">Profile Image</label>
                            <span class="badge badge-blue">JPG, PNG, WEBP</span>
                        </div>
                        <div class="avatar-preview-wrapper mb-3" id="profilePreviewContainer">
                            <div class="avatar-preview-placeholder" id="avatarPlaceholder">
                                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                <span>No image selected</span>
                            </div>
                            <img id="profileImagePreview" src="" alt="Profile Preview" class="avatar-preview-img" style="display: none;">
                        </div>
                        <input type="file" id="profile_image" name="profile_image" class="file-input" accept=".jpg,.jpeg,.png,.webp" data-max-size="2097152" data-type="image">
                        <span class="form-hint mt-2">Square photo recommended. Max 2 MB.</span>
                    </div>

                    <!-- ID Proof Upload -->
                    <div class="upload-card">
                        <div class="upload-card-header">
                            <label class="form-label">Student ID Proof</label>
                            <span class="badge badge-purple">PDF, JPG, PNG</span>
                        </div>
                        <div class="file-drop-area" id="idProofDropArea">
                            <svg class="drop-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="2"></rect><line x1="7" y1="8" x2="17" y2="8"></line><line x1="7" y1="12" x2="13" y2="12"></line></svg>
                            <span class="drop-text">Aadhar, College ID, or Govt ID</span>
                            <div class="file-selected-info" id="idProofFileInfo" style="display: none;"></div>
                        </div>
                        <input type="file" id="id_proof" name="id_proof" class="file-input mt-2" accept=".pdf,.jpg,.jpeg,.png" data-max-size="2097152">
                        <span class="form-hint mt-2">Single document. Max 2 MB.</span>
                    </div>

                    <!-- Certificates Upload (Multiple) -->
                    <div class="upload-card upload-card-full">
                        <div class="upload-card-header">
                            <label class="form-label">Academic Certificates &amp; Documents</label>
                            <span class="badge badge-green">Multiple Files Supported &bull; PDF, Images</span>
                        </div>
                        <div class="file-drop-area" id="certDropArea">
                            <svg class="drop-icon" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                            <span class="drop-text">Select one or multiple certificates (Mark sheets, degrees, diplomas)</span>
                        </div>
                        <input type="file" id="certificates" name="certificates[]" multiple class="file-input mt-2" accept=".pdf,.jpg,.jpeg,.png,.webp" data-max-size="2097152">
                        <span class="form-hint mt-2">Hold Ctrl / Cmd to pick multiple files. Max 2 MB each.</span>
                        <div id="certFileList" class="selected-files-list mt-3"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-submit-bar">
            <a href="students.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                Enroll Student Record
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
