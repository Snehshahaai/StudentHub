<?php
/**
 * StudentHub - Student Management: list, search, filter, approve, delete
 * Faculty can view and search. Only admins can add, edit, approve or delete.
 */

declare(strict_types=1);

require __DIR__ . '/../php/guard.php';
require __DIR__ . '/../php/students.php';
require __DIR__ . '/../php/views/admin-layout.php';

$user    = require_role(['faculty', 'admin']);
$isAdmin = $user['role'] === 'admin';

/* ---------- Actions (POST, then redirect back: Post/Redirect/Get) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only return to our own list page
    $back = is_string($_POST['back'] ?? null)
        && (str_starts_with($_POST['back'], 'admin-students.php') || $_POST['back'] === 'admin-dashboard.php')
        ? $_POST['back'] : 'admin-students.php';

    if (!$isAdmin) {
        set_flash('error', 'Only administrators can change student records.');
    } elseif (!csrf_valid()) {
        set_flash('error', 'Your form expired. Please try again.');
    } else {
        $id      = (int) ($_POST['student_id'] ?? 0);
        $action  = $_POST['action'] ?? '';
        try {
            $student = student_find($id);
            if (!$student) {
                set_flash('error', 'That student no longer exists.');
            } elseif ($action === 'approve') {
                if (student_approve($id, $user['id'])) {
                    audit_log('admin', $user['id'], 'student.approve', 'students', $id, "Approved {$student['enrollment_no']}");
                    set_flash('success', "{$student['full_name']} ({$student['enrollment_no']}) has been approved and can now log in.");
                } else {
                    set_flash('error', "{$student['full_name']} is not waiting for approval.");
                }
            } elseif ($action === 'delete') {
                if (student_delete($id)) {
                    audit_log('admin', $user['id'], 'student.delete', 'students', $id,
                        "Deleted {$student['enrollment_no']} {$student['full_name']}");
                    set_flash('success', "{$student['full_name']} ({$student['enrollment_no']}) and all of their records were deleted.");
                } else {
                    set_flash('error', 'The student could not be deleted. Please try again.');
                }
            } else {
                set_flash('error', 'Unknown action.');
            }
        } catch (mysqli_sql_exception $e) {
            error_log('[StudentHub students] ' . $e->getMessage());
            set_flash('error', 'Database error: the change was not saved. Please try again.');
        }
    }
    header('Location: ' . $back, true, 303);
    exit;
}

/* ---------- List ---------- */
try {
    $courses = student_courses();
    $filters = student_filters($_GET, $courses);
    $result  = student_search($filters);
    $counts  = student_status_counts();
} catch (mysqli_sql_exception $e) {
    error_log('[StudentHub students] ' . $e->getMessage());
    http_response_code(503);
    exit('The database is unavailable right now. Please make sure MySQL is running and try again.');
}
$filters['page'] = $result['page'];
$here = list_url($filters, ['page' => $result['page']]);
$filtered = $filters['q'] !== '' || $filters['course'] !== '' || $filters['year'] || $filters['status'] !== '' || $filters['gender'] !== '';
$first = $result['total'] ? ($result['page'] - 1) * $filters['per_page'] + 1 : 0;
$last  = min($result['total'], $result['page'] * $filters['per_page']);

admin_page_start('Students', 'students', $user);
?>
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2><i class="fas fa-users text-primary me-2"></i> Student Management</h2>
                    <p class="text-muted mb-0">Search, filter and manage every student record.</p>
                </div>
                <?php if ($isAdmin): ?>
                    <a href="admin-student-form.php" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i> Add Student</a>
                <?php endif; ?>
            </div>

            <!-- Status chips (quick filter) -->
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="<?= e(list_url($filters, ['status' => '', 'page' => 1])) ?>"
                   class="btn btn-sm <?= $filters['status'] === '' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    All <span class="ms-1 opacity-75"><?= array_sum($counts) ?></span>
                </a>
                <?php foreach ($counts as $status => $n): ?>
                    <a href="<?= e(list_url($filters, ['status' => $status, 'page' => 1])) ?>"
                       class="btn btn-sm <?= $filters['status'] === $status ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <?= e(ucfirst($status)) ?> <span class="ms-1 opacity-75"><?= $n ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Search & filters (GET, so results can be bookmarked) -->
            <form method="get" action="admin-students.php" class="card p-3 mb-3" role="search">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-3">
                        <label for="q" class="form-label small fw-bold mb-1">Search</label>
                        <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>"
                               placeholder="Name, username, email, enrollment no. or mobile" maxlength="60">
                    </div>
                    <div class="col-6 col-lg-2">
                        <label for="course" class="form-label small fw-bold mb-1">Course</label>
                        <select class="form-select" id="course" name="course">
                            <option value="">All courses</option>
                            <?php foreach ($courses as $code => $c): ?>
                                <option value="<?= e($code) ?>"<?= $filters['course'] === $code ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-1">
                        <label for="year" class="form-label small fw-bold mb-1">Year</label>
                        <select class="form-select" id="year" name="year">
                            <option value="">All</option>
                            <?php for ($y = 1; $y <= 4; $y++): ?>
                                <option value="<?= $y ?>"<?= $filters['year'] === $y ? ' selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-1">
                        <label for="status" class="form-label small fw-bold mb-1">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All</option>
                            <?php foreach (STUDENT_STATUSES as $s): ?>
                                <option value="<?= e($s) ?>"<?= $filters['status'] === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-1">
                        <label for="gender" class="form-label small fw-bold mb-1">Gender</label>
                        <select class="form-select" id="gender" name="gender">
                            <option value="">All</option>
                            <?php foreach (STUDENT_GENDERS as $g): ?>
                                <option value="<?= e($g) ?>"<?= $filters['gender'] === $g ? ' selected' : '' ?>><?= e($g) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label for="sort" class="form-label small fw-bold mb-1">Sort</label>
                        <select class="form-select" id="sort" name="sort">
                            <?php foreach (STUDENT_SORTS as $key => [$label]): ?>
                                <option value="<?= e($key) ?>"<?= $filters['sort'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search me-1"></i> Apply</button>
                        <?php if ($filtered || $filters['sort'] !== 'newest'): ?>
                            <a href="admin-students.php" class="btn btn-outline-secondary" title="Clear search and filters" aria-label="Clear search and filters"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <input type="hidden" name="per_page" value="<?= $filters['per_page'] ?>">
            </form>

            <p class="text-muted small mb-2" aria-live="polite">
                <?php if ($result['total']): ?>
                    Showing <?= $first ?>–<?= $last ?> of <?= $result['total'] ?> student<?= $result['total'] === 1 ? '' : 's' ?><?= $filtered ? ' matching your search' : '' ?>
                <?php else: ?>
                    No students found<?= $filtered ? ' matching your search' : '' ?>.
                <?php endif; ?>
            </p>

            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-primary">
                            <tr>
                                <th>Enrollment No.</th>
                                <th>Name</th>
                                <th class="d-none d-md-table-cell">Email / Mobile</th>
                                <th>Course</th>
                                <th>Year</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$result['rows']): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="fas fa-search fs-3 d-block mb-2"></i>
                                    <?= $filtered ? 'No students match these filters.' : 'No students yet.' ?>
                                    <?php if ($filtered): ?><a href="admin-students.php">Clear filters</a><?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($result['rows'] as $s): ?>
                            <tr>
                                <td class="font-monospace fw-semibold"><?= e($s['enrollment_no']) ?></td>
                                <td>
                                    <a href="admin-student-view.php?id=<?= (int) $s['student_id'] ?>" class="fw-bold"><?= e($s['full_name']) ?></a>
                                    <div class="small text-muted">@<?= e($s['username']) ?> · <?= e($s['gender']) ?></div>
                                </td>
                                <td class="d-none d-md-table-cell small">
                                    <?= e($s['email']) ?><br><span class="text-muted"><?= e($s['mobile']) ?></span>
                                </td>
                                <td><?= e($s['course_code']) ?><div class="small text-muted"><?= e($s['department']) ?></div></td>
                                <td><?= (int) $s['year_of_study'] ?><div class="small text-muted">Sem <?= (int) $s['semester'] ?></div></td>
                                <td><?= status_badge($s['status']) ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="admin-student-view.php?id=<?= (int) $s['student_id'] ?>" class="btn btn-sm btn-outline-primary" title="View" aria-label="View <?= e($s['full_name']) ?>"><i class="fas fa-eye"></i></a>
                                    <?php if ($isAdmin): ?>
                                        <?php if ($s['status'] === 'pending'): ?>
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="student_id" value="<?= (int) $s['student_id'] ?>">
                                                <input type="hidden" name="back" value="<?= e($here) ?>">
                                                <button type="submit" class="btn btn-sm btn-success" title="Approve"><i class="fas fa-check"></i><span class="d-none d-xl-inline ms-1">Approve</span></button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="admin-student-form.php?id=<?= (int) $s['student_id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit" aria-label="Edit <?= e($s['full_name']) ?>"><i class="fas fa-edit"></i></a>
                                        <form method="post" class="d-inline"
                                              data-confirm="Delete <?= e($s['full_name']) ?> (<?= e($s['enrollment_no']) ?>)? Their attendance, submissions, results and event registrations will be deleted too. This cannot be undone.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="student_id" value="<?= (int) $s['student_id'] ?>">
                                            <input type="hidden" name="back" value="<?= e($here) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete <?= e($s['full_name']) ?>"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination + page size -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3">
                <form method="get" action="admin-students.php" class="d-flex align-items-center gap-2">
                    <?php foreach (['q', 'course', 'year', 'status', 'gender', 'sort'] as $key): ?>
                        <?php if ($filters[$key] !== '' && $filters[$key] !== 0): ?>
                            <input type="hidden" name="<?= $key ?>" value="<?= e($filters[$key]) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <label for="per_page" class="small text-muted text-nowrap">Rows per page</label>
                    <select class="form-select form-select-sm" id="per_page" name="per_page" onchange="this.form.submit()">
                        <?php foreach (STUDENT_PER_PAGE as $n): ?>
                            <option value="<?= $n ?>"<?= $filters['per_page'] === $n ? ' selected' : '' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button class="btn btn-sm btn-outline-primary">Go</button></noscript>
                </form>

                <?php if ($result['pages'] > 1): ?>
                    <nav aria-label="Student pages">
                        <ul class="pagination sh-pagination mb-0 flex-wrap">
                            <li class="page-item<?= $result['page'] === 1 ? ' disabled' : '' ?>">
                                <a class="page-link" href="<?= e(list_url($filters, ['page' => $result['page'] - 1])) ?>" aria-label="Previous page"><i class="fas fa-chevron-left"></i></a>
                            </li>
                            <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
                                <li class="page-item<?= $p === $result['page'] ? ' active' : '' ?>">
                                    <a class="page-link" href="<?= e(list_url($filters, ['page' => $p])) ?>"<?= $p === $result['page'] ? ' aria-current="page"' : '' ?>><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item<?= $result['page'] === $result['pages'] ? ' disabled' : '' ?>">
                                <a class="page-link" href="<?= e(list_url($filters, ['page' => $result['page'] + 1])) ?>" aria-label="Next page"><i class="fas fa-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
<?php admin_page_end();
