<?php
/**
 * Student Profile Management System
 * View Student Details & Associated Files
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/upload.php';

$studentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$studentId || $studentId <= 0) {
    setFlash('error', "Invalid student ID specified.");
    header("Location: students.php");
    exit;
}

// Handle Quick Document Upload from View Page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_extra') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        setFlash('error', "Security verification failed. Please try again.");
        header("Location: view.php?id=" . $studentId);
        exit;
    }

    $uploadType = $_POST['file_category'] ?? 'cert';
    if (!in_array($uploadType, ['profile', 'id_proof', 'cert'], true)) {
        $uploadType = 'cert';
    }

    $extraFiles = !empty($_FILES['extra_files']) ? normalizeFilesArray($_FILES['extra_files']) : [];
    if (empty($extraFiles)) {
        setFlash('warning', "No files were selected for upload.");
        header("Location: view.php?id=" . $studentId);
        exit;
    }

    $uploadErrors = [];
    $savedDiskFiles = [];

    // Pre-validate
    foreach ($extraFiles as $idx => $f) {
        $errMsg = '';
        $category = ($uploadType === 'profile') ? 'image' : null;
        if (!validateUploadedFile($f, $category, $errMsg)) {
            $uploadErrors[] = $errMsg;
        }
    }

    if (empty($uploadErrors)) {
        try {
            $pdo->beginTransaction();

            $insertFileStmt = $pdo->prepare("
                INSERT INTO student_files (student_id, original_name, stored_name, file_path, file_type, file_size, uploaded_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            foreach ($extraFiles as $f) {
                $savedData = [];
                $errMsg = '';
                if (!saveUploadedFile($f, $uploadType, $studentId, $savedData, $errMsg)) {
                    throw new Exception($errMsg);
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
            setFlash('success', count($savedDiskFiles) . " file(s) successfully added to the student's profile.");
            header("Location: view.php?id=" . $studentId);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            cleanupFiles($savedDiskFiles);
            setFlash('error', "Upload failed: " . $e->getMessage());
            header("Location: view.php?id=" . $studentId);
            exit;
        }
    } else {
        setFlash('error', implode(" ", $uploadErrors));
        header("Location: view.php?id=" . $studentId);
        exit;
    }
}

// Fetch Student Profile
try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        setFlash('error', "Student profile with ID #{$studentId} was not found.");
        header("Location: students.php");
        exit;
    }

    // Fetch all associated files
    $filesStmt = $pdo->prepare("SELECT * FROM student_files WHERE student_id = ? ORDER BY id DESC");
    $filesStmt->execute([$studentId]);
    $files = $filesStmt->fetchAll();

    // Calculate file stats
    $totalStorage = 0;
    $profilePhoto = null;
    foreach ($files as $file) {
        $totalStorage += (int)$file['file_size'];
        if (!$profilePhoto && strpos($file['stored_name'], 'profile_') === 0) {
            $profilePhoto = $file;
        }
    }

} catch (PDOException $e) {
    die("Database query error: " . htmlspecialchars($e->getMessage()));
}

$pageTitle = $student['name'] . ' - Profile';
$activePage = 'students';
require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1 class="page-title"><?= e($student['name']) ?></h1>
        <p class="page-subtitle">Student Profile &bull; Roll No: <strong><?= e($student['roll_number']) ?></strong></p>
    </div>
    <div class="page-actions">
        <a href="students.php" class="btn btn-outline">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            All Students
        </a>
        <a href="edit.php?id=<?= (int)$student['id'] ?>" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            Edit Profile
        </a>
        <button type="button" class="btn btn-danger"
                onclick="confirmDeleteStudent(<?= (int)$student['id'] ?>, '<?= e(addslashes($student['name'])) ?>', '<?= e(addslashes($student['roll_number'])) ?>', <?= count($files) ?>)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            Delete
        </button>
    </div>
</div>

<div class="profile-layout">
    <!-- Left Column: Student Profile Information Card -->
    <div class="profile-sidebar">
        <div class="card profile-card">
            <div class="card-body text-center">
                <!-- Large Avatar Photo -->
                <div class="profile-avatar-lg">
                    <?php if ($profilePhoto): ?>
                        <a href="download.php?id=<?= (int)$profilePhoto['id'] ?>&preview=1" target="_blank" title="Click to view full photo">
                            <img src="download.php?id=<?= (int)$profilePhoto['id'] ?>&preview=1" alt="<?= e($student['name']) ?>" class="profile-avatar-img">
                        </a>
                    <?php else: ?>
                        <div class="profile-avatar-fallback">
                            <?= e(getStudentInitials($student['name'])) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <h2 class="profile-name"><?= e($student['name']) ?></h2>
                <div class="profile-roll"><span class="code-badge"><?= e($student['roll_number']) ?></span></div>
                <div class="profile-course mt-2"><span class="badge badge-course"><?= e($student['course']) ?></span></div>

                <hr class="divider">

                <ul class="profile-details-list">
                    <li>
                        <span class="detail-label">Email Address</span>
                        <span class="detail-value">
                            <?php if (!empty($student['email'])): ?>
                                <a href="mailto:<?= e($student['email']) ?>"><?= e($student['email']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">Not provided</span>
                            <?php endif; ?>
                        </span>
                    </li>
                    <li>
                        <span class="detail-label">Enrolled On</span>
                        <span class="detail-value"><?= date('F d, Y - h:i A', strtotime($student['created_at'])) ?></span>
                    </li>
                    <li>
                        <span class="detail-label">Total Documents</span>
                        <span class="detail-value font-bold"><?= count($files) ?> files</span>
                    </li>
                    <li>
                        <span class="detail-label">Storage Used</span>
                        <span class="detail-value font-bold"><?= formatBytes($totalStorage) ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Quick Upload Additional Documents Widget -->
        <div class="card mt-4">
            <div class="card-header">
                <h3>Upload Additional Files</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="view.php?id=<?= (int)$student['id'] ?>" enctype="multipart/form-data" id="quickUploadForm">
                    <input type="hidden" name="action" value="upload_extra">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">

                    <div class="form-group mb-3">
                        <label for="file_category" class="form-label">Document Type</label>
                        <select name="file_category" id="file_category" class="form-control" required>
                            <option value="cert">Certificate / Academic Record</option>
                            <option value="id_proof">Government / College ID Proof</option>
                            <option value="profile">Profile Photo</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label for="extra_files" class="form-label">Select Files (Max 2 MB each)</label>
                        <input type="file" name="extra_files[]" id="extra_files" multiple class="file-input" required accept=".pdf,.jpg,.jpeg,.png,.webp" data-max-size="2097152">
                        <span class="form-hint mt-1">Accepts JPG, PNG, WEBP, and PDF.</span>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        Upload Documents
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Associated Files & Records -->
    <div class="profile-content">
        <div class="card">
            <div class="card-header">
                <div class="card-header-title">
                    <h3>Uploaded Files &amp; Documents</h3>
                    <span class="badge badge-gray"><?= count($files) ?> total</span>
                </div>
                <div class="card-header-actions">
                    <a href="edit.php?id=<?= (int)$student['id'] ?>" class="btn btn-outline btn-sm">Manage / Replace Files</a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($files)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                        </div>
                        <h4>No files uploaded yet</h4>
                        <p>No documents or photos have been uploaded for this student.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>File Name</th>
                                    <th>Category</th>
                                    <th>Format</th>
                                    <th>Size</th>
                                    <th>Uploaded</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $file): ?>
                                    <?php
                                        $catInfo = getFileCategoryInfo($file['stored_name']);
                                        $isPdf = ($file['file_type'] === 'application/pdf');
                                        $isImage = (strpos($file['file_type'], 'image/') === 0);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="file-cell">
                                                <div class="file-icon <?= $isPdf ? 'file-icon-pdf' : 'file-icon-img' ?>">
                                                    <?php if ($isPdf): ?>
                                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                                    <?php else: ?>
                                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="file-info">
                                                    <span class="file-name" title="<?= e($file['original_name']) ?>"><?= e($file['original_name']) ?></span>
                                                    <span class="file-stored-name text-muted text-xs">Secure ID: #<?= (int)$file['id'] ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= e($catInfo['badge']) ?>">
                                                <?= e($catInfo['category']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="mime-tag"><?= e(strtoupper(pathinfo($file['original_name'], PATHINFO_EXTENSION))) ?></span>
                                        </td>
                                        <td>
                                            <span class="file-size"><?= formatBytes($file['file_size']) ?></span>
                                        </td>
                                        <td>
                                            <span class="text-muted text-sm"><?= date('d M Y, h:i A', strtotime($file['uploaded_at'])) ?></span>
                                        </td>
                                        <td class="text-right">
                                            <div class="action-buttons">
                                                <!-- Preview Button -->
                                                <a href="download.php?id=<?= (int)$file['id'] ?>&preview=1" target="_blank" class="btn-icon btn-icon-view" title="Preview Document">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                </a>
                                                <!-- Download Button -->
                                                <a href="download.php?id=<?= (int)$file['id'] ?>" class="btn-icon btn-icon-download" title="Download File">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                                </a>
                                                <!-- Delete Single File Button -->
                                                <button type="button" class="btn-icon btn-icon-delete"
                                                        onclick="confirmDeleteFile(<?= (int)$file['id'] ?>, '<?= e(addslashes($file['original_name'])) ?>', <?= (int)$student['id'] ?>)"
                                                        title="Delete File">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
