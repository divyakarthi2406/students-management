<?php
/**
 * Student Profile Management System
 * Secure Deletion Handler (Students & Files)
 *
 * Implements transaction-safe deletion, CSRF protection,
 * and safe physical file removal.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/upload.php';

$action = $_POST['action'] ?? $_GET['action'] ?? 'student';

// ==========================================
// SCENARIO 1: DELETE INDIVIDUAL FILE
// ==========================================
if ($action === 'file') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($csrfToken)) {
            setFlash('error', "Security verification failed. Please try again.");
            header("Location: students.php");
            exit;
        }

        $fileId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $returnStudentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);

        if (!$fileId || $fileId <= 0) {
            setFlash('error', "Invalid file ID specified.");
            header("Location: students.php");
            exit;
        }

        try {
            // Retrieve file info before deletion
            $stmt = $pdo->prepare("SELECT * FROM student_files WHERE id = ? LIMIT 1");
            $stmt->execute([$fileId]);
            $file = $stmt->fetch();

            if (!$file) {
                setFlash('error', "File record not found in database.");
                $redirectUrl = $returnStudentId ? "view.php?id={$returnStudentId}" : "students.php";
                header("Location: " . $redirectUrl);
                exit;
            }

            $actualStudentId = $file['student_id'];
            $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file['file_path']);
            $physicalPath = __DIR__ . DIRECTORY_SEPARATOR . $normalizedPath;

            // Delete database record first inside transaction
            $pdo->beginTransaction();
            $delStmt = $pdo->prepare("DELETE FROM student_files WHERE id = ?");
            $delStmt->execute([$fileId]);
            $pdo->commit();

            // Safely delete physical file from disk
            if (file_exists($physicalPath) && is_file($physicalPath)) {
                @unlink($physicalPath);
            }

            setFlash('success', "File '{$file['original_name']}' was permanently removed.");
            header("Location: view.php?id=" . $actualStudentId);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', "Failed to delete file: " . $e->getMessage());
            $redirectUrl = $returnStudentId ? "view.php?id={$returnStudentId}" : "students.php";
            header("Location: " . $redirectUrl);
            exit;
        }
    } else {
        // Direct GET request for file deletion confirmation
        $fileId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$fileId || $fileId <= 0) {
            setFlash('error', "Invalid file ID.");
            header("Location: students.php");
            exit;
        }

        $stmt = $pdo->prepare("SELECT sf.*, s.name as student_name FROM student_files sf JOIN students s ON sf.student_id = s.id WHERE sf.id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch();

        if (!$file) {
            setFlash('error', "File not found.");
            header("Location: students.php");
            exit;
        }

        $pageTitle = 'Confirm Delete File';
        require_once __DIR__ . '/header.php';
        ?>
        <div class="confirm-container">
            <div class="card confirm-card">
                <div class="card-body text-center p-5">
                    <div class="confirm-icon confirm-icon-danger">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </div>
                    <h2>Delete Document?</h2>
                    <p class="text-muted mt-2">
                        Are you sure you want to permanently delete <strong><?= e($file['original_name']) ?></strong> for student <strong><?= e($file['student_name']) ?></strong>?
                    </p>
                    <p class="text-danger text-sm mt-1">This action cannot be undone and will remove the file from storage.</p>

                    <form method="POST" action="delete.php" class="mt-4">
                        <input type="hidden" name="action" value="file">
                        <input type="hidden" name="id" value="<?= (int)$file['id'] ?>">
                        <input type="hidden" name="student_id" value="<?= (int)$file['student_id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                        <div class="confirm-actions">
                            <a href="view.php?id=<?= (int)$file['student_id'] ?>" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-danger">Yes, Delete File</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        require_once __DIR__ . '/footer.php';
        exit;
    }
}

// ==========================================
// SCENARIO 2: DELETE STUDENT & ALL FILES
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        setFlash('error', "Security verification failed. Please try again.");
        header("Location: students.php");
        exit;
    }

    $studentId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$studentId || $studentId <= 0) {
        setFlash('error', "Invalid student ID specified.");
        header("Location: students.php");
        exit;
    }

    try {
        // Fetch student record
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();

        if (!$student) {
            setFlash('error', "Student record not found or already deleted.");
            header("Location: students.php");
            exit;
        }

        // Fetch all associated files strictly belonging to this student
        $filesStmt = $pdo->prepare("SELECT id, file_path, original_name FROM student_files WHERE student_id = ?");
        $filesStmt->execute([$studentId]);
        $studentFiles = $filesStmt->fetchAll();

        // Prepare physical file paths to delete
        $diskPathsToDelete = [];
        $uploadsDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');

        foreach ($studentFiles as $f) {
            $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $f['file_path']);
            $absPath = realpath(__DIR__ . DIRECTORY_SEPARATOR . $normalized);
            // Verify path belongs inside uploads directory
            if ($absPath && $uploadsDir && strpos($absPath, $uploadsDir) === 0 && file_exists($absPath)) {
                $diskPathsToDelete[] = $absPath;
            }
        }

        // Transaction: Delete file metadata first, then student record
        $pdo->beginTransaction();

        $delFilesStmt = $pdo->prepare("DELETE FROM student_files WHERE student_id = ?");
        $delFilesStmt->execute([$studentId]);

        $delStudentStmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $delStudentStmt->execute([$studentId]);

        $pdo->commit();

        // Safely remove files from disk
        cleanupFiles($diskPathsToDelete);

        setFlash('success', "Student '{$student['name']}' (Roll: {$student['roll_number']}) and " . count($diskPathsToDelete) . " associated file(s) were permanently deleted.");
        header("Location: students.php");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', "Failed to delete student: " . $e->getMessage());
        header("Location: students.php");
        exit;
    }
}

// Direct GET request for Student Deletion Confirmation Page
$studentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$studentId || $studentId <= 0) {
    setFlash('error', "Invalid student ID.");
    header("Location: students.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        setFlash('error', "Student record not found.");
        header("Location: students.php");
        exit;
    }

    $filesStmt = $pdo->prepare("SELECT * FROM student_files WHERE student_id = ?");
    $filesStmt->execute([$studentId]);
    $files = $filesStmt->fetchAll();

} catch (PDOException $e) {
    die("Database query error: " . htmlspecialchars($e->getMessage()));
}

$pageTitle = 'Confirm Delete - ' . $student['name'];
require_once __DIR__ . '/header.php';
?>

<div class="confirm-container">
    <div class="card confirm-card">
        <div class="card-body text-center p-5">
            <div class="confirm-icon confirm-icon-danger">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <h2>Confirm Student Deletion</h2>
            <p class="text-muted mt-2">
                Are you sure you want to permanently delete the profile of:
            </p>
            <div class="confirm-target-box mt-3 mb-3">
                <div class="font-bold text-lg"><?= e($student['name']) ?></div>
                <div class="text-sm text-muted">Roll Number: <span class="code-badge"><?= e($student['roll_number']) ?></span> &bull; Course: <?= e($student['course']) ?></div>
                <div class="mt-2 text-danger text-sm">
                    <strong><?= count($files) ?></strong> attached file(s) will also be deleted from disk.
                </div>
            </div>

            <?php if (!empty($files)): ?>
                <div class="confirm-files-list mb-4">
                    <div class="text-xs font-semibold text-muted text-uppercase mb-2">Files Scheduled For Permanent Removal:</div>
                    <ul class="file-tag-list">
                        <?php foreach ($files as $f): ?>
                            <li><?= e($f['original_name']) ?> (<?= formatBytes($f['file_size']) ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <p class="text-danger text-sm font-semibold">
                Warning: This action is permanent and cannot be undone!
            </p>

            <form method="POST" action="delete.php" class="mt-4">
                <input type="hidden" name="id" value="<?= (int)$student['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                <div class="confirm-actions">
                    <a href="view.php?id=<?= (int)$student['id'] ?>" class="btn btn-secondary">Cancel &amp; Keep Student</a>
                    <button type="submit" class="btn btn-danger btn-lg">Yes, Delete Student Permanently</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
