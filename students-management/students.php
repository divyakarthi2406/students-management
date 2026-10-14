<?php
/**
 * Student Profile Management System
 * Students Directory & List Page
 */

require_once __DIR__ . '/db.php';

$pageTitle = 'View Students';
$activePage = 'students';

// Search and Filter Parameters
$search = trim($_GET['search'] ?? '');
$courseFilter = trim($_GET['course'] ?? '');
$sortBy = trim($_GET['sort'] ?? 'date_desc');

// Build query with prepared statements
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(s.name LIKE :search OR s.roll_number LIKE :search OR s.email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($courseFilter)) {
    $whereClauses[] = "s.course = :course";
    $params[':course'] = $courseFilter;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Handle sorting
$orderBy = match ($sortBy) {
    'name_asc'   => 's.name ASC',
    'name_desc'  => 's.name DESC',
    'roll_asc'   => 's.roll_number ASC',
    'roll_desc'  => 's.roll_number DESC',
    'date_asc'   => 's.created_at ASC',
    default      => 's.created_at DESC',
};

try {
    // Distinct Courses list for filter dropdown
    $coursesList = $pdo->query("SELECT DISTINCT course FROM students WHERE course IS NOT NULL AND course != '' ORDER BY course ASC")->fetchAll(PDO::FETCH_COLUMN);

    // Fetch Students with attached file counts and profile image file id
    $query = "
        SELECT s.*,
               (SELECT COUNT(*) FROM student_files WHERE student_id = s.id) AS file_count,
               (SELECT id FROM student_files WHERE student_id = s.id AND stored_name LIKE 'profile_%' ORDER BY id DESC LIMIT 1) AS profile_file_id,
               (SELECT COUNT(*) FROM student_files WHERE student_id = s.id AND stored_name LIKE 'id_proof_%') AS id_proof_count,
               (SELECT COUNT(*) FROM student_files WHERE student_id = s.id AND stored_name LIKE 'cert_%') AS cert_count
        FROM students s
        {$whereSql}
        ORDER BY {$orderBy}
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database query error: " . htmlspecialchars($e->getMessage()));
}

require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1 class="page-title">Students Directory</h1>
        <p class="page-subtitle">Manage, search, and browse all registered student profiles</p>
    </div>
    <div class="page-actions">
        <a href="add.php" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add Student
        </a>
    </div>
</div>

<!-- Search & Filter Controls -->
<div class="card filter-card mb-4">
    <form method="GET" action="students.php" class="filter-form">
        <div class="filter-row">
            <!-- Search Input -->
            <div class="filter-group search-input-group">
                <label for="searchInput" class="filter-label">Search</label>
                <div class="input-with-icon">
                    <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="searchInput" name="search" class="form-control" placeholder="Search by name, roll no, or email..." value="<?= e($search) ?>">
                </div>
            </div>

            <!-- Course Filter -->
            <div class="filter-group">
                <label for="courseSelect" class="filter-label">Course</label>
                <select id="courseSelect" name="course" class="form-control">
                    <option value="">All Courses</option>
                    <?php foreach ($coursesList as $courseName): ?>
                        <option value="<?= e($courseName) ?>" <?= $courseFilter === $courseName ? 'selected' : '' ?>>
                            <?= e($courseName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sort By -->
            <div class="filter-group">
                <label for="sortSelect" class="filter-label">Sort By</label>
                <select id="sortSelect" name="sort" class="form-control">
                    <option value="date_desc" <?= $sortBy === 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                    <option value="date_asc" <?= $sortBy === 'date_asc' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="name_asc" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>>Name (A &rarr; Z)</option>
                    <option value="name_desc" <?= $sortBy === 'name_desc' ? 'selected' : '' ?>>Name (Z &rarr; A)</option>
                    <option value="roll_asc" <?= $sortBy === 'roll_asc' ? 'selected' : '' ?>>Roll Number (Asc)</option>
                    <option value="roll_desc" <?= $sortBy === 'roll_desc' ? 'selected' : '' ?>>Roll Number (Desc)</option>
                </select>
            </div>

            <!-- Filter Buttons -->
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if (!empty($search) || !empty($courseFilter) || $sortBy !== 'date_desc'): ?>
                    <a href="students.php" class="btn btn-outline" title="Reset all filters">Reset</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Student Records Table -->
<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <h3>Student Profiles</h3>
            <span class="badge badge-primary"><?= count($students) ?> <?= count($students) === 1 ? 'record' : 'records' ?> found</span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($students)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </div>
                <h4>No matching students found</h4>
                <p>Try adjusting your search criteria or register a new student.</p>
                <div class="mt-3">
                    <?php if (!empty($search) || !empty($courseFilter)): ?>
                        <a href="students.php" class="btn btn-outline btn-sm">Clear Search Filters</a>
                    <?php endif; ?>
                    <a href="add.php" class="btn btn-primary btn-sm">+ Add Student</a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="studentsTable">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Roll Number</th>
                            <th>Course</th>
                            <th>Email Address</th>
                            <th>Uploaded Documents</th>
                            <th>Registered</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="avatar-sm">
                                            <?php if (!empty($student['profile_file_id'])): ?>
                                                <a href="download.php?id=<?= (int)$student['profile_file_id'] ?>&preview=1" target="_blank" title="View Full Photo">
                                                    <img src="download.php?id=<?= (int)$student['profile_file_id'] ?>&preview=1" alt="<?= e($student['name']) ?>" loading="lazy">
                                                </a>
                                            <?php else: ?>
                                                <div class="avatar-fallback"><?= e(getStudentInitials($student['name'])) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-meta">
                                            <a href="view.php?id=<?= (int)$student['id'] ?>" class="user-name"><?= e($student['name']) ?></a>
                                            <span class="user-subtext">ID #<?= (int)$student['id'] ?></span>
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
                                    <span class="text-body"><?= e($student['email'] ?: '—') ?></span>
                                </td>
                                <td>
                                    <div class="doc-tags">
                                        <a href="view.php?id=<?= (int)$student['id'] ?>" class="badge badge-files" title="Total attachments">
                                            <?= (int)$student['file_count'] ?> files
                                        </a>
                                        <?php if (!empty($student['id_proof_count'])): ?>
                                            <span class="badge badge-purple" title="Has ID proof">ID Proof</span>
                                        <?php endif; ?>
                                        <?php if (!empty($student['cert_count'])): ?>
                                            <span class="badge badge-green" title="Has certificates"><?= (int)$student['cert_count'] ?> Certs</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted text-sm"><?= date('d M Y', strtotime($student['created_at'])) ?></span>
                                </td>
                                <td class="text-right">
                                    <div class="action-buttons">
                                        <a href="view.php?id=<?= (int)$student['id'] ?>" class="btn-icon btn-icon-view" title="View Full Profile &amp; Files">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </a>
                                        <a href="edit.php?id=<?= (int)$student['id'] ?>" class="btn-icon btn-icon-edit" title="Edit Student Profile">
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

<?php require_once __DIR__ . '/footer.php'; ?>
