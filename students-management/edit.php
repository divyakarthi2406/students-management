<?php
/**
 * Student Profile Management System
 * Edit Student Profile & Associated Documents
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/upload.php';

$studentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$studentId || $studentId <= 0) {
    setFlash('error', "Invalid student ID specified.");
    header("Location: students.php");
    exit;
}

// Fetch Existing Student
try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        setFlash('error', "Student profile with ID #{$studentId} was not found.");
        header("Location: students.php");
        exit;
    }

    // Fetch Existing Associated Files
    $filesStmt = $pdo->prepare("SELECT * FROM student_files WHERE student_id = ? ORDER BY id DESC");
    $filesStmt->execute([$studentId]);
    $existingFiles = $filesStmt->fetchAll();

} catch (PDOException $e) {
    die("Database query error: " . htmlspecialchars($e->getMessage()));
}

$errors = [];
$name = $student['name'];
$rollNumber = $student['roll_number'];
$email = $student['email'] ?? '';
$course = $student['course'];

// Separate existing files by category for clear presentation
$existingProfile = null;
$existingIdProof = null;
$existingCerts = [];

foreach ($existingFiles as $f) {
    if (!$existingProfile && strpos($f['stored_name'], 'profile_') === 0) {
        $existingProfile = $f;
    } elseif (!$existingIdProof && strpos($f['stored_name'], 'id_proof_') === 0) {
        $existingIdProof = $f;
    } else {
        $existingCerts[] = $f;
    }
}

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

    // 4. Check Unique Roll Number (excluding this student)
    if (empty($errors)) {
        try {
            $checkStmt = $pdo->prepare("SELECT id FROM students WHERE roll_number = ? AND id != ? LIMIT 1");
            $checkStmt->execute([$rollNumber, $studentId]);
            if ($checkStmt->fetch()) {
                $errors[] = "Roll Number '{$rollNumber}' is already assigned to another student. Roll numbers must be unique.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error while verifying roll number: " . $e->getMessage();
        }
    }

    // 5. Pre-validate any newly uploaded files
    $newProfileFile = (!empty($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) ? $_FILES['profile_image'] : null;
    $newIdProofFile = (!empty($_FILES['id_proof']) && $_FILES['id_proof']['error'] !== UPLOAD_ERR_NO_FILE) ? $_FILES['id_proof'] : null;
    $newCertFiles   = !empty($_FILES['certificates']) ? normalizeFilesArray($_FILES['certificates']) : [];

    if ($newProfileFile) {
        $errMsg = '';
        if (!validateUploadedFile($newProfileFile, 'image', $errMsg)) {
            $errors[] = "Replacement Profile Image: " . $errMsg;
        }
    }

    if ($newIdProofFile) {
        $errMsg = '';
        if (!validateUploadedFile($newIdProofFile, null, $errMsg)) {
            $errors[] = "Replacement ID Proof: " . $errMsg;
        }
    }

    foreach ($newCertFiles as $idx => $certFile) {
        $errMsg = '';
        if (!validateUploadedFile($certFile, null, $errMsg)) {
            $errors[] = "Additional Certificate #" . ($idx + 1) . ": " . $errMsg;
        }
    }

    // 6. Execute Updates in Database Transaction
    if (empty($errors)) {
        $savedDiskFiles = [];
        $filesToCleanOnCommit = []; // old files to remove only if replacement succeeds

        try {
            $pdo->beginTransaction();

            // Update Student Profile
            $updateStudentStmt = $pdo->prepare("
                UPDATE students
                SET name = ?, roll_number = ?, email = ?, course = ?
                WHERE id = ?
            ");
            $updateStudentStmt->execute([
                $name,
                $rollNumber,
                !empty($email) ? $email : null,
                $course,
                $studentId
            ]);

            $insertFileStmt = $pdo->prepare("
                INSERT INTO student_files (student_id, original_name, stored_name, file_path, file_type, file_size, uploaded_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            // Process Replacement Profile Image if provided
            if ($newProfileFile) {
                $savedData = [];
                $errMsg = '';
                if (!saveUploadedFile($newProfileFile, 'profile', $studentId, $savedData, $errMsg)) {
                    throw new Exception("Profile Image: " . $errMsg);
                }
                $savedDiskFiles[] = $savedData['full_path'];

                // Insert new profile record
                $insertFileStmt->execute([
                    $studentId,
                    $savedData['original_name'],
                    $savedData['stored_name'],
                    $savedData['file_path'],
                    $savedData['file_type'],
                    $savedData['file_size']
                ]);

                // Mark old profile file for removal if exists
                if ($existingProfile) {
                    $filesToCleanOnCommit[] = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $existingProfile['file_path']);
                    $deleteOldStmt = $pdo->prepare("DELETE FROM student_files WHERE id = ?");
                    $deleteOldStmt->execute([$existingProfile['id']]);
                }
            }

            // Process Replacement ID Proof if provided
            if ($newIdProofFile) {
                $savedData = [];
                $errMsg = '';
                if (!saveUploadedFile($newIdProofFile, 'id_proof', $studentId, $savedData, $errMsg)) {
                    throw new Exception("ID Proof: " . $errMsg);
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

                // Mark old ID proof file for removal if exists
                if ($existingIdProof) {
                    $filesToCleanOnCommit[] = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $existingIdProof['file_path']);
                    $deleteOldStmt = $pdo->prepare("DELETE FROM student_files WHERE id = ?");
                    $deleteOldStmt->execute([$existingIdProof['id']]);
                }
            }

            // Process Additional Certificates
            foreach ($newCertFiles as $idx => $certFile) {
                $savedData = [];
                $errMsg = '';
                if (!saveUploadedFile($certFile, 'cert', $studentId, $savedData, $errMsg)) {
                    throw new Exception("Certificate #" . ($idx + 1) . ": " . $errMsg);
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

            $pdo->commit();

            // Safely unlink replaced old files after successful commit
            cleanupFiles($filesToCleanOnCommit);

            setFlash('success', "Student profile '{$name}' was successfully updated.");
            header("Location: view.php?id=" . $studentId);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            cleanupFiles($savedDiskFiles);
            $errors[] = "Update failed: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit ' . $student['name'];
$activePage = 'students';
require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1 class="page-title">Edit Student Profile</h1>
        <p class="page-subtitle">Update student information or manage associated documentation</p>
    </div>
    <div class="page-actions">
        <a href="view.php?id=<?= (int)$student['id'] ?>" class="btn btn-outline">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Cancel &amp; View Profile
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
    <form method="POST" action="edit.php?id=<?= (int)$student['id'] ?>" enctype="multipart/form-data" class="student-form" id="editStudentForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">

        <!-- Section 1: Basic Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>1. Personal &amp; Academic Details</h3>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <!-- Full Name -->
                    <div class="form-group">
                        <label for="name" class="form-label required">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control" required maxlength="100" value="<?= e($name) ?>">
                        <span class="form-feedback" id="nameFeedback"></span>
                    </div>

                    <!-- Roll Number -->
                    <div class="form-group">
                        <label for="roll_number" class="form-label required">Roll Number</label>
                        <input type="text" id="roll_number" name="roll_number" class="form-control" required maxlength="30" value="<?= e($rollNumber) ?>">
                        <span class="form-hint">Must be unique across all students.</span>
                        <span class="form-feedback" id="rollFeedback"></span>
                    </div>

                    <!-- Email Address -->
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" maxlength="100" value="<?= e($email) ?>">
                        <span class="form-feedback" id="emailFeedback"></span>
                    </div>

                    <!-- Course -->
                    <div class="form-group">
                        <label for="course" class="form-label required">Course / Degree</label>
                        <input type="text" id="course" name="course" list="courseSuggestions" class="form-control" required maxlength="100" value="<?= e($course) ?>">
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
                        <span class="form-feedback" id="courseFeedback"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Manage Existing Files -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-header-title">
                    <h3>2. Current Uploaded Documents</h3>
                    <span class="badge badge-gray"><?= count($existingFiles) ?> files attached</span>
                </div>
                <div class="card-header-hint text-muted text-sm">
                    All existing files are preserved automatically. You may delete individual files below.
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($existingFiles)): ?>
                    <p class="p-4 text-muted text-center">No files are currently attached to this student profile.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Uploaded</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($existingFiles as $f): ?>
                                    <?php $catInfo = getFileCategoryInfo($f['stored_name']); ?>
                                    <tr>
                                        <td>
                                            <a href="download.php?id=<?= (int)$f['id'] ?>&preview=1" target="_blank" class="font-medium text-primary">
                                                <?= e($f['original_name']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge <?= e($catInfo['badge']) ?>"><?= e($catInfo['category']) ?></span>
                                        </td>
                                        <td><?= formatBytes($f['file_size']) ?></td>
                                        <td class="text-muted text-sm"><?= date('M d, Y', strtotime($f['uploaded_at'])) ?></td>
                                        <td class="text-right">
                                            <a href="download.php?id=<?= (int)$f['id'] ?>" class="btn btn-outline btn-xs mr-1" title="Download">Download</a>
                                            <button type="button" class="btn btn-danger btn-xs"
                                                    onclick="confirmDeleteFile(<?= (int)$f['id'] ?>, '<?= e(addslashes($f['original_name'])) ?>', <?= (int)$student['id'] ?>)">
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section 3: Upload Replacement or Additional Files -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-header-title">
                    <h3>3. Upload Replacements or Additional Documents</h3>
                    <span class="badge badge-info">Optional &bull; Max 2 MB / file</span>
                </div>
            </div>
            <div class="card-body">
                <div class="upload-grid">
                    <!-- Replace Profile Photo -->
                    <div class="upload-card">
                        <div class="upload-card-header">
                            <label class="form-label"><?= $existingProfile ? 'Replace Profile Photo' : 'Upload Profile Photo' ?></label>
                            <span class="badge badge-blue">JPG, PNG, WEBP</span>
                        </div>
                        <div class="avatar-preview-wrapper mb-3" id="profilePreviewContainer">
                            <?php if ($existingProfile): ?>
                                <img id="profileImagePreview" src="download.php?id=<?= (int)$existingProfile['id'] ?>&preview=1" alt="Current Photo" class="avatar-preview-img">
                                <div class="avatar-preview-placeholder" id="avatarPlaceholder" style="display: none;"></div>
                            <?php else: ?>
                                <div class="avatar-preview-placeholder" id="avatarPlaceholder">
                                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    <span>No current photo</span>
                                </div>
                                <img id="profileImagePreview" src="" alt="Profile Preview" class="avatar-preview-img" style="display: none;">
                            <?php endif; ?>
                        </div>
                        <input type="file" id="profile_image" name="profile_image" class="file-input" accept=".jpg,.jpeg,.png,.webp" data-max-size="2097152" data-type="image">
                        <span class="form-hint mt-2"><?= $existingProfile ? 'Select a new photo to replace current image. Leave empty to keep.' : 'Max 2 MB.' ?></span>
                    </div>

                    <!-- Replace ID Proof -->
                    <div class="upload-card">
                        <div class="upload-card-header">
                            <label class="form-label"><?= $existingIdProof ? 'Replace ID Proof' : 'Upload ID Proof' ?></label>
                            <span class="badge badge-purple">PDF, JPG, PNG</span>
                        </div>
                        <div class="file-drop-area" id="idProofDropArea">
                            <svg class="drop-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="2"></rect><line x1="7" y1="8" x2="17" y2="8"></line><line x1="7" y1="12" x2="13" y2="12"></line></svg>
                            <span class="drop-text"><?= $existingIdProof ? 'Current: ' . e($existingIdProof['original_name']) : 'Upload Aadhar, College ID, etc.' ?></span>
                            <div class="file-selected-info" id="idProofFileInfo" style="display: none;"></div>
                        </div>
                        <input type="file" id="id_proof" name="id_proof" class="file-input mt-2" accept=".pdf,.jpg,.jpeg,.png" data-max-size="2097152">
                        <span class="form-hint mt-2">Leave blank to retain current ID proof document.</span>
                    </div>

                    <!-- Add Additional Certificates -->
                    <div class="upload-card upload-card-full">
                        <div class="upload-card-header">
                            <label class="form-label">Add Additional Certificates &amp; Documents</label>
                            <span class="badge badge-green">Multiple Files Supported</span>
                        </div>
                        <div class="file-drop-area" id="certDropArea">
                            <svg class="drop-icon" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                            <span class="drop-text">Upload more degrees, marksheets or certificates</span>
                        </div>
                        <input type="file" id="certificates" name="certificates[]" multiple class="file-input mt-2" accept=".pdf,.jpg,.jpeg,.png,.webp" data-max-size="2097152">
                        <span class="form-hint mt-2">Any uploaded files here will be added to the student's existing collection.</span>
                        <div id="certFileList" class="selected-files-list mt-3"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-submit-bar">
            <a href="view.php?id=<?= (int)$student['id'] ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                Save Profile Changes
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
