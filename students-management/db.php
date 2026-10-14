<?php
/**
 * Student Profile Management System
 * Database Configuration & Helper Utilities
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'student_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Establish PDO Database Connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Graceful error display in development/assignment environment
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

/**
 * Escapes HTML characters to prevent XSS attacks.
 *
 * @param mixed $data
 * @return string
 */
function e($data) {
    return htmlspecialchars((string)($data ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Sets a flash message to be displayed on the next request.
 *
 * @param string $type ('success'|'error'|'warning'|'info')
 * @param string $message
 */
function setFlash($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Retrieves and clears all flash messages.
 *
 * @return array
 */
function getFlashes() {
    $flashes = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $flashes;
}

/**
 * Generates or retrieves existing CSRF token.
 *
 * @return string
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifies a provided CSRF token against session token.
 *
 * @param string $token
 * @return bool
 */
function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Formats bytes into human-readable string (KB, MB).
 *
 * @param int $bytes
 * @param int $precision
 * @return string
 */
function formatBytes($bytes, $precision = 1) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Categorizes a file based on its stored_name prefix.
 *
 * @param string $storedName
 * @return array ['category' => string, 'badge' => string, 'icon' => string]
 */
function getFileCategoryInfo($storedName) {
    if (strpos($storedName, 'profile_') === 0) {
        return [
            'category' => 'Profile Photo',
            'badge' => 'badge-blue',
            'icon' => 'user'
        ];
    } elseif (strpos($storedName, 'id_proof_') === 0) {
        return [
            'category' => 'ID Proof',
            'badge' => 'badge-purple',
            'icon' => 'id-card'
        ];
    } elseif (strpos($storedName, 'cert_') === 0) {
        return [
            'category' => 'Certificate',
            'badge' => 'badge-green',
            'icon' => 'award'
        ];
    }
    return [
        'category' => 'Document',
        'badge' => 'badge-gray',
        'icon' => 'file'
    ];
}

/**
 * Returns initials from a student's full name.
 *
 * @param string $name
 * @return string
 */
function getStudentInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) {
            $initials .= mb_substr($w, 0, 1);
            if (mb_strlen($initials) >= 2) break;
        }
    }
    return strtoupper($initials ?: 'ST');
}

/**
 * Retrieves the profile picture file record for a student if exists.
 *
 * @param PDO $pdo
 * @param int $studentId
 * @return array|null
 */
function getStudentProfileFile($pdo, $studentId) {
    $stmt = $pdo->prepare("SELECT * FROM student_files WHERE student_id = ? AND stored_name LIKE 'profile_%' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$studentId]);
    return $stmt->fetch() ?: null;
}
