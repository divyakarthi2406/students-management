<?php
/**
 * Student Profile Management System
 * Secure File Upload Validation & Processing Utilities
 */

// Maximum file size in bytes (2 MB)
define('MAX_FILE_SIZE_BYTES', 2 * 1024 * 1024);

// Allowed MIME types and their matching extensions
const ALLOWED_MIME_TYPES = [
    'image/jpeg'      => ['jpg', 'jpeg'],
    'image/png'       => ['png'],
    'image/webp'      => ['webp'],
    'application/pdf' => ['pdf']
];

// Blocked dangerous extensions (defense-in-depth)
const BLOCKED_EXTENSIONS = [
    'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
    'exe', 'bat', 'cmd', 'sh', 'bash', 'bin', 'pl', 'py', 'cgi',
    'js', 'html', 'htm', 'shtml', 'svg', 'asp', 'aspx', 'jsp'
];

/**
 * Normalizes $_FILES array for multiple file inputs.
 *
 * @param array $fileInput
 * @return array Array of normalized single file arrays
 */
function normalizeFilesArray($fileInput) {
    $normalized = [];
    if (!isset($fileInput['name'])) {
        return $normalized;
    }

    if (is_array($fileInput['name'])) {
        $count = count($fileInput['name']);
        for ($i = 0; $i < $count; $i++) {
            // Skip empty file selections
            if ($fileInput['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $normalized[] = [
                'name'     => $fileInput['name'][$i],
                'type'     => $fileInput['type'][$i],
                'tmp_name' => $fileInput['tmp_name'][$i],
                'error'    => $fileInput['error'][$i],
                'size'     => $fileInput['size'][$i],
            ];
        }
    } else {
        if ($fileInput['error'] !== UPLOAD_ERR_NO_FILE) {
            $normalized[] = $fileInput;
        }
    }

    return $normalized;
}

/**
 * Validates an uploaded file for size, extension, and real MIME type using PHP fileinfo.
 *
 * @param array $file Normalized single file array
 * @param string|null $expectedCategory Optional restriction: 'image' or 'document' or null
 * @param string &$errorMessage Reference to store error message
 * @param string &$detectedMime Reference to store verified MIME type
 * @param string &$safeExt Reference to store verified file extension
 * @return bool
 */
function validateUploadedFile($file, $expectedCategory, &$errorMessage, &$detectedMime = '', &$safeExt = '') {
    // 1. Check upload error status
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = "File '{$file['name']}' exceeds the maximum allowed size of 2 MB.";
                return false;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = "File '{$file['name']}' was only partially uploaded. Please try again.";
                return false;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMessage = "Server configuration error: missing temporary folder.";
                return false;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMessage = "Server error: failed to write file to disk.";
                return false;
            default:
                $errorMessage = "Upload failed for '{$file['name']}' with error code {$file['error']}.";
                return false;
        }
    }

    // 2. Check file size
    if ($file['size'] <= 0) {
        $errorMessage = "File '{$file['name']}' is empty (0 bytes).";
        return false;
    }
    if ($file['size'] > MAX_FILE_SIZE_BYTES) {
        $errorMessage = "File '{$file['name']}' (" . round($file['size'] / 1024 / 1024, 2) . " MB) exceeds the 2 MB limit.";
        return false;
    }

    // 3. Validate filename and extension
    $originalName = $file['name'];
    $pathParts = pathinfo($originalName);
    $ext = strtolower($pathParts['extension'] ?? '');

    if (empty($ext)) {
        $errorMessage = "File '{$originalName}' has no file extension.";
        return false;
    }

    if (in_array($ext, BLOCKED_EXTENSIONS, true)) {
        $errorMessage = "Security Alert: Files with extension '.{$ext}' are strictly prohibited.";
        return false;
    }

    // Check against allowed extensions
    $allAllowedExts = [];
    foreach (ALLOWED_MIME_TYPES as $extList) {
        $allAllowedExts = array_merge($allAllowedExts, $extList);
    }
    if (!in_array($ext, $allAllowedExts, true)) {
        $errorMessage = "Invalid extension '.{$ext}'. Allowed formats: JPG, JPEG, PNG, WEBP, and PDF.";
        return false;
    }

    // 4. Verify file was uploaded via HTTP POST (in web environment)
    if (php_sapi_name() !== 'cli' && !is_uploaded_file($file['tmp_name'])) {
        $errorMessage = "Security error: '{$file['name']}' is not a valid uploaded file.";
        return false;
    }

    // 5. Verify real MIME type using PHP fileinfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        $errorMessage = "Internal server error: unable to initialize PHP fileinfo.";
        return false;
    }
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($mime, ALLOWED_MIME_TYPES)) {
        $errorMessage = "Security Alert: File '{$originalName}' contains unapproved content type ('{$mime}').";
        return false;
    }

    // Ensure MIME type matches the file extension
    $validExtensionsForMime = ALLOWED_MIME_TYPES[$mime];
    if (!in_array($ext, $validExtensionsForMime, true)) {
        $errorMessage = "File extension '.{$ext}' does not match its detected content type ('{$mime}').";
        return false;
    }

    // 6. Check category restriction if specified (e.g. Profile Image must be an image)
    if ($expectedCategory === 'image' && strpos($mime, 'image/') !== 0) {
        $errorMessage = "File '{$originalName}' is not a valid image. Please upload JPG, PNG, or WEBP.";
        return false;
    }

    $detectedMime = $mime;
    $safeExt = $ext;
    return true;
}

/**
 * Saves a validated uploaded file to the uploads directory.
 *
 * @param array $file Single file array
 * @param string $prefix ('profile', 'id_proof', 'cert')
 * @param int|string $studentId
 * @param array &$savedData Reference to store file metadata for DB insertion
 * @param string &$errorMessage Reference to store error message
 * @return bool
 */
function saveUploadedFile($file, $prefix, $studentId, &$savedData, &$errorMessage) {
    $category = ($prefix === 'profile') ? 'image' : null;
    $detectedMime = '';
    $safeExt = '';

    if (!validateUploadedFile($file, $category, $errorMessage, $detectedMime, $safeExt)) {
        return false;
    }

    $uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadsDir)) {
        if (!mkdir($uploadsDir, 0755, true)) {
            $errorMessage = "Failed to create uploads directory on server.";
            return false;
        }
    }

    // Generate unique, unpredictable server-side filename
    // Format: {prefix}_{studentId}_{randomHex}_{timestamp}.{ext}
    $randomToken = bin2hex(random_bytes(8));
    $timestamp = time();
    $storedName = "{$prefix}_{$studentId}_{$randomToken}_{$timestamp}.{$safeExt}";
    $destination = $uploadsDir . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $errorMessage = "Failed to save uploaded file '{$file['name']}' to server storage.";
        return false;
    }

    // Sanitize original filename (keep only printable safe chars)
    $cleanOriginalName = preg_replace('/[^\w\s\.\-_()]/u', '', basename($file['name']));
    if (empty($cleanOriginalName)) {
        $cleanOriginalName = 'document.' . $safeExt;
    }

    $savedData = [
        'original_name' => $cleanOriginalName,
        'stored_name'   => $storedName,
        'file_path'     => 'uploads/' . $storedName,
        'file_type'     => $detectedMime,
        'file_size'     => (int)$file['size'],
        'full_path'     => $destination
    ];

    return true;
}

/**
 * Safely unlinks a list of files from server storage.
 *
 * @param array $filePaths Array of absolute filesystem paths
 */
function cleanupFiles(array $filePaths) {
    foreach ($filePaths as $path) {
        if (!empty($path) && file_exists($path) && is_file($path)) {
            @unlink($path);
        }
    }
}
