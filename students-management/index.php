<?php
/**
 * Student Profile Management System
 * Admin Dashboard
 */

require_once __DIR__ . '/db.php';

$pageTitle = 'Dashboard';
$activePage = 'dashboard';

// Fetch Statistics
try {
    // Total Students
    $totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

    // Total Files
    $totalFiles = (int)$pdo->query("SELECT COUNT(*) FROM student_files")->fetchColumn();

    // Total Courses
    $totalCourses = (int)$pdo->query("SELECT COUNT(DISTINCT course) FROM students WHERE course IS NOT NULL AND course != ''")->fetchColumn();

    // Total Storage Used
    $totalStorageBytes = (int)$pdo->query("SELECT COALESCE(SUM(file_size), 0) FROM student_files")->fetchColumn();

    // Course Distribution
    $courseStmt = $pdo->query("
        SELECT course, COUNT(*) AS count
        FROM students
        WHERE course IS NOT NULL AND course != ''
        GROUP BY course
        ORDER BY count DESC
        LIMIT 6
    ");
    $courseDistribution = $courseStmt->fetchAll();

    // Recent 5 Students
    $recentStmt = $pdo->query("
        SELECT s.*,
               (SELECT COUNT(*) FROM student_files WHERE student_id = s.id) AS file_count,
               (SELECT id FROM student_files WHERE student_id = s.id AND stored_name LIKE 'profile_%' ORDER BY id DESC LIMIT 1) AS profile_file_id
        FROM students s
        ORDER BY s.id DESC
        LIMIT 5
    ");
    $recentStudents = $recentStmt->fetchAll();

} catch (PDOException $e) {
    die("Database query error: " . htmlspecialchars($e->getMessage()));
}

require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1 class="page-title">Admin Dashboard</h1>
        <p class="page-subtitle">Overview of student profiles, enrollments, and document records</p>
    </div>
    <div class="page-actions">
        <a href="add.php" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add New Student
        </a>
        <a href="students.php" class="btn btn-outline">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
            View Directory
        </a>
    </div>
</div>

<!-- KPI Summary Cards -->
<section class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-blue">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Total Students</span>
            <span class="stat-value"><?= number_format($totalStudents) ?></span>
            <span class="stat-hint">Active registered profiles</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper stat-green">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Documents Stored</span>
            <span class="stat-value"><?= number_format($totalFiles) ?></span>
            <span class="stat-hint">Photos, IDs &amp; certificates</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper stat-purple">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Courses</span>
            <span class="stat-value"><?= number_format($totalCourses) ?></span>
            <span class="stat-hint">Active academic programs</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper stat-amber">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Storage Used</span>
            <span class="stat-value"><?= formatBytes($totalStorageBytes) ?></span>
            <span class="stat-hint">Max 2 MB limit per file</span>
        </div>
    </div>
</section>

<!-- Dashboard Grid (Recent Students & Quick Course Overview) -->
<div class="dashboard-grid">
    <!-- Recent Students Table Card -->
    <div class="card recent-students-card">
        <div class="card-header">
            <div class="card-header-title">
                <h3>Recent Enrollments</h3>
                <span class="badge badge-gray"><?= count($recentStudents) ?> displayed</span>
            </div>
            <a href="students.php" class="link-arrow">View All Students &rarr;</a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($recentStudents)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    </div>
                    <h4>No student records found</h4>
                    <p>Get started by adding your first student profile to the database.</p>
                    <a href="add.php" class="btn btn-primary btn-sm">+ Add First Student</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Roll Number</th>
                                <th>Course</th>
                                <th>Files</th>
                                <th>Enrolled Date</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentStudents as $student): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar-sm">
                                                <?php if (!empty($student['profile_file_id'])): ?>
                                                    <img src="download.php?id=<?= (int)$student['profile_file_id'] ?>&preview=1" alt="<?= e($student['name']) ?>" loading="lazy">
                                                <?php else: ?>
                                                    <div class="avatar-fallback"><?= e(getStudentInitials($student['name'])) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="user-meta">
                                                <a href="view.php?id=<?= (int)$student['id'] ?>" class="user-name"><?= e($student['name']) ?></a>
                                                <span class="user-email"><?= e($student['email'] ?: 'No email') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="code-badge"><?= e($student['roll_number']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-course"><?= e($student['course']) ?></span>
                                    </td>
                                    <td>
                                        <a href="view.php?id=<?= (int)$student['id'] ?>" class="badge badge-files" title="View attached documents">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                                            <?= (int)$student['file_count'] ?> <?= $student['file_count'] == 1 ? 'file' : 'files' ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?= date('M d, Y', strtotime($student['created_at'])) ?></span>
                                    </td>
                                    <td class="text-right">
                                        <div class="action-buttons">
                                            <a href="view.php?id=<?= (int)$student['id'] ?>" class="btn-icon btn-icon-view" title="View Profile">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            </a>
                                            <a href="edit.php?id=<?= (int)$student['id'] ?>" class="btn-icon btn-icon-edit" title="Edit Student">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </a>
                                            <button type="button" class="btn-icon btn-icon-delete"
                                                    onclick="confirmDeleteStudent(<?= (int)$student['id'] ?>, '<?= e(addslashes($student['name'])) ?>', '<?= e(addslashes($student['roll_number'])) ?>', <?= (int)$student['file_count'] ?>)"
                                                    title="Delete Student">
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

    <!-- Side Panel: Course Breakdown & System Info -->
    <div class="side-panel">
        <!-- Courses Breakdown Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Course Distribution</h3>
            </div>
            <div class="card-body">
                <?php if (empty($courseDistribution)): ?>
                    <p class="text-muted text-sm">No course enrollment data yet.</p>
                <?php else: ?>
                    <ul class="course-list">
                        <?php foreach ($courseDistribution as $c): ?>
                            <li class="course-item">
                                <span class="course-name"><?= e($c['course']) ?></span>
                                <span class="badge badge-primary"><?= (int)$c['count'] ?> students</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Security & Status Card -->
        <div class="card">
            <div class="card-header">
                <h3>System &amp; Security Status</h3>
            </div>
            <div class="card-body">
                <ul class="system-status-list">
                    <li>
                        <span class="status-indicator status-online"></span>
                        <span class="status-title">Apache &amp; MySQL</span>
                        <span class="status-value">Active</span>
                    </li>
                    <li>
                        <span class="status-indicator status-online"></span>
                        <span class="status-title">PHP Fileinfo</span>
                        <span class="status-value">Enabled</span>
                    </li>
                    <li>
                        <span class="status-indicator status-online"></span>
                        <span class="status-title">Folder Protection</span>
                        <span class="status-value">.htaccess Active</span>
                    </li>
                    <li>
                        <span class="status-indicator status-online"></span>
                        <span class="status-title">File Serving</span>
                        <span class="status-value">Secure Proxy</span>
                    </li>
                    <li>
                        <span class="status-indicator status-info"></span>
                        <span class="status-title">Max Upload Size</span>
                        <span class="status-value">2 MB / file</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
