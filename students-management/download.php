<?php
/**
 * Student Profile Management System
 * Secure File-Serving and Download Endpoint
 *
 * Verifies that the requested file belongs to an existing student,
 * prevents directory traversal attacks, and serves files safely.
 */

require_once __DIR__ . '/db.php';

// Validate file ID from query string
$fileId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$fileId || $fileId <= 0) {
    http_response_code(400);
    die("Invalid file ID requested.");
}

try {
    // Verify file exists and belongs to an existing student record
    $stmt = $pdo->prepare("
        SELECT sf.*, s.name AS student_name, s.roll_number
        FROM student_files sf
        INNER JOIN students s ON sf.student_id = s.id
        WHERE sf.id = ?
        LIMIT 1
    ");
    $stmt->execute([$fileId]);
    $fileRecord = $stmt->fetch();

    if (!$fileRecord) {
        http_response_code(404);
        die("File not found or the associated student record does not exist.");
    }

    // Secure Path Verification (Prevent Path Traversal)
    $normalizedRelPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fileRecord['file_path']);
    $expectedPath = __DIR__ . DIRECTORY_SEPARATOR . $normalizedRelPath;
    $realFilePath = realpath($expectedPath);
    $uploadsDir   = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');

    if (!$realFilePath || !$uploadsDir || strpos($realFilePath, $uploadsDir) !== 0 || !is_file($realFilePath)) {
        http_response_code(404);
        die("Physical file missing from server storage.");
    }

    // Determine disposition mode: inline (preview) vs attachment (download)
    $isPreview = isset($_GET['preview']) && in_array($fileRecord['file_type'], [
        'image/jpeg', 'image/png', 'image/webp', 'application/pdf'
    ], true);

    $disposition = $isPreview ? 'inline' : 'attachment';

    // Sanitize filename for HTTP header
    $downloadName = preg_replace('/[^\w\.\-_ ()]/', '_', basename($fileRecord['original_name']));
    if (empty($downloadName)) {
        $downloadName = 'document_' . $fileRecord['id'];
    }

    // Clear output buffer to prevent corrupted file downloads
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Send HTTP Security and Content Headers
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . $fileRecord['file_type']);
    header('Content-Length: ' . filesize($realFilePath));
    header('Content-Disposition: ' . $disposition . '; filename="' . $downloadName . '"');
    header('Cache-Control: private, max-age=3600');
    header('Pragma: private');

    // Read and output the file securely
    readfile($realFilePath);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    die("Database error while retrieving file.");
}
