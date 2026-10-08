<?php
/**
 * StudentHub - Student Management: add (no id) or edit (?id=) a student
 * Admins only. Validates on the server, saves with MySQLi prepared statements,
 * then redirects to the list with a success message (Post/Redirect/Get).
 */

declare(strict_types=1);

require __DIR__ . '/../php/guard.php';
require __DIR__ . '/../php/students.php';
require __DIR__ . '/../php/views/admin-layout.php';

$user = require_role(['admin']);

try {
    $courses  = student_courses();
    $editId   = (int) ($_GET['id'] ?? 0);
    $existing = $editId ? student_find($editId) : null;
} catch (mysqli_sql_exception $e) {
    error_log('[StudentHub student form] ' . $e->getMessage());
    http_response_code(503);
    exit('The database is unavailable right now. Please make sure MySQL is running and try again.');
}

if ($editId && !$existing) {
    set_flash('error', 'That student no longer exists.');
    header('Location: admin-students.php', true, 303);
    exit;
}

$isEdit = $existing !== null;
$errors = [];

// Form values: what was posted, else the stored record, else defaults
$values = $isEdit ? [
    'full_name'     => $existing['full_name'],
    'username'      => $existing['username'],
    'email'         => $existing['email'],
    'mobile'        => $existing['mobile'],
    'gender'        => $existing['gender'],
    'course'        => $existing['course_code'],
    'year_of_study' => (int) $existing['year_of_study'],
    'semester'      => (int) $existing['semester'],
    'address'       => (string) $existing['address'],
    'status'        => $existing['status'],
] : [
    'full_name' => '', 'username' => '', 'email' => '', 'mobile' => '', 'gender' => '',
    'course' => '', 'year_of_study' => 1, 'semester' => 1, 'address' => '', 'status' => 'active',
];

/* ---------- Save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values   = student_input($_POST);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm  = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    if (!csrf_valid()) {
        $errors['form'] = 'Your form expired. Please submit it again.';
    } else {
        try {
            $errors = student_validate($values, $password, $confirm, $courses, $existing);

            if (!$errors) {
                $course = $courses[$values['course']];
                if ($isEdit) {
                    $changed = student_update($existing, $values, $password, $course, $user['id']);
                    audit_log('admin', $user['id'], 'student.update', 'students', $editId,
                        $changed ? 'Changed: ' . implode(', ', $changed) : 'No changes');
                    set_flash('success', $changed
                        ? "Saved changes to {$values['full_name']} ({$existing['enrollment_no']}): " . implode(', ', str_replace('_', ' ', $changed)) . '.'
                        : "No changes were made to {$values['full_name']}.");
                } else {
                    [$newId, $enrollmentNo] = student_create($values, $password, $course, $user['id']);
                    audit_log('admin', $user['id'], 'student.create', 'students', $newId, "Created {$enrollmentNo}");
                    set_flash('success', "Student {$values['full_name']} was added with enrollment number {$enrollmentNo}.");
                }
                header('Location: admin-students.php', true, 303);
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            error_log('[StudentHub student form] ' . $e->getMessage());
            // 1062 = a UNIQUE key caught a duplicate that slipped past the check above
            $errors['form'] = $e->getCode() === 1062
                ? 'Another student was saved with the same username, email or mobile at the same moment. Please check and try again.'
                : 'Database error: the student was not saved. Please try again.';
        }
    }
}

/** Bootstrap classes + message for one field. */
function field_state(array $errors, string $field): array
{
    return isset($errors[$field])
        ? [' is-invalid', '<div class="invalid-feedback">' . e($errors[$field]) . '</div>']
        : ['', ''];
}

$title = $isEdit ? "Edit {$existing['full_name']}" : 'Add Student';
admin_page_start($title, 'students', $user);
?>
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item"><a href="admin-students.php">Students</a></li>
                    <?php if ($isEdit): ?>
                        <li class="breadcrumb-item"><a href="admin-student-view.php?id=<?= $editId ?>"><?= e($existing['enrollment_no']) ?></a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Edit' : 'Add' ?></li>
                </ol>
            </nav>

            <h2 class="mb-1"><i class="fas <?= $isEdit ? 'fa-user-edit' : 'fa-user-plus' ?> text-primary me-2"></i> <?= e($title) ?></h2>
            <p class="text-muted mb-4">
                <?= $isEdit ? 'Enrollment number ' . e($existing['enrollment_no']) . ' stays the same.' : 'An enrollment number is generated automatically from the course.' ?>
            </p>

            <?php if ($errors): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= isset($errors['form']) ? e($errors['form']) : 'The student was not saved. Please fix the ' . count($errors) . ' highlighted field' . (count($errors) === 1 ? '' : 's') . '.' ?>
                </div>
            <?php endif; ?>

            <form method="post" class="card p-4 shadow-sm" id="studentForm" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <?php [$cls, $msg] = field_state($errors, 'full_name'); ?>
                    <div class="col-md-6">
                        <label for="full_name" class="form-label fw-bold">Full Name</label>
                        <input type="text" class="form-control<?= $cls ?>" id="full_name" name="full_name" value="<?= e($values['full_name']) ?>"
                               maxlength="50" pattern="[A-Za-z]+( [A-Za-z]+)*" required>
                        <?= $msg ?>
                    </div>
                    <?php [$cls, $msg] = field_state($errors, 'username'); ?>
                    <div class="col-md-6">
                        <label for="username" class="form-label fw-bold">Username</label>
                        <input type="text" class="form-control<?= $cls ?>" id="username" name="username" value="<?= e($values['username']) ?>"
                               maxlength="20" autocapitalize="none" spellcheck="false" required>
                        <?= $msg ?>
                    </div>

                    <?php [$cls, $msg] = field_state($errors, 'email'); ?>
                    <div class="col-md-6">
                        <label for="email" class="form-label fw-bold">Email Address</label>
                        <input type="email" class="form-control<?= $cls ?>" id="email" name="email" value="<?= e($values['email']) ?>" maxlength="100" required>
                        <?= $msg ?>
                    </div>
                    <?php [$cls, $msg] = field_state($errors, 'mobile'); ?>
                    <div class="col-md-6">
                        <label for="mobile" class="form-label fw-bold">Mobile Number</label>
                        <input type="tel" class="form-control<?= $cls ?>" id="mobile" name="mobile" value="<?= e($values['mobile']) ?>"
                               maxlength="10" pattern="[6-9][0-9]{9}" inputmode="numeric" required>
                        <?= $msg ?>
                    </div>

                    <?php [$cls, $msg] = field_state($errors, 'course'); ?>
                    <div class="col-md-6">
                        <label for="course" class="form-label fw-bold">Course</label>
                        <select class="form-select<?= $cls ?>" id="course" name="course" required>
                            <option value="">Choose course...</option>
                            <?php foreach ($courses as $code => $c): ?>
                                <option value="<?= e($code) ?>" data-years="<?= (int) $c['duration_years'] ?>"<?= $values['course'] === $code ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $msg ?>
                    </div>
                    <?php [$cls, $msg] = field_state($errors, 'year_of_study'); ?>
                    <div class="col-6 col-md-3">
                        <label for="year_of_study" class="form-label fw-bold">Year</label>
                        <select class="form-select<?= $cls ?>" id="year_of_study" name="year_of_study" required>
                            <?php for ($y = 1; $y <= 6; $y++): ?>
                                <option value="<?= $y ?>"<?= (int) $values['year_of_study'] === $y ? ' selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <?= $msg ?>
                    </div>
                    <?php [$cls, $msg] = field_state($errors, 'semester'); ?>
                    <div class="col-6 col-md-3">
                        <label for="semester" class="form-label fw-bold">Semester</label>
                        <select class="form-select<?= $cls ?>" id="semester" name="semester" required>
                            <?php for ($sem = 1; $sem <= 12; $sem++): ?>
                                <option value="<?= $sem ?>"<?= (int) $values['semester'] === $sem ? ' selected' : '' ?>><?= $sem ?></option>
                            <?php endfor; ?>
                        </select>
                        <?= $msg ?>
                    </div>

                    <?php [$cls, $msg] = field_state($errors, 'gender'); ?>
                    <div class="col-md-6">
                        <span class="form-label fw-bold d-block">Gender</span>
                        <?php foreach (STUDENT_GENDERS as $g): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input<?= $cls ?>" type="radio" name="gender" id="gender<?= e($g) ?>" value="<?= e($g) ?>"<?= $values['gender'] === $g ? ' checked' : '' ?> required>
                                <label class="form-check-label" for="gender<?= e($g) ?>"><?= e($g) ?></label>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($msg): ?><div class="invalid-feedback d-block"><?= e($errors['gender']) ?></div><?php endif; ?>
                    </div>
                    <?php [$cls, $msg] = field_state($errors, 'status'); ?>
                    <div class="col-md-6">
                        <label for="status" class="form-label fw-bold">Account Status</label>
                        <select class="form-select<?= $cls ?>" id="status" name="status" required>
                            <?php foreach (STUDENT_STATUSES as $s): ?>
                                <option value="<?= e($s) ?>"<?= $values['status'] === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Only active students can log in.</div>
                        <?= $msg ?>
                    </div>

                    <?php [$cls, $msg] = field_state($errors, 'address'); ?>
                    <div class="col-12">
                        <label for="address" class="form-label fw-bold">Address <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea class="form-control<?= $cls ?>" id="address" name="address" rows="2" maxlength="255"><?= e($values['address']) ?></textarea>
                        <?= $msg ?>
                    </div>

                    <div class="col-12"><hr class="my-1"></div>

                    <?php [$cls, $msg] = field_state($errors, 'password'); ?>
                    <div class="col-md-6">
                        <label for="password" class="form-label fw-bold"><?= $isEdit ? 'New Password' : 'Password' ?></label>
                        <input type="password" class="form-control<?= $cls ?>" id="password" name="password" autocomplete="new-password"<?= $isEdit ? '' : ' required' ?>>
                        <div class="form-text"><?= $isEdit ? 'Leave blank to keep the current password.' : '8+ characters with uppercase, lowercase, number and special character.' ?></div>
                        <?= $msg ?>
                    </div>
                    <?php [$cls, $msg] = field_state($errors, 'confirm_password'); ?>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label fw-bold">Confirm Password</label>
                        <input type="password" class="form-control<?= $cls ?>" id="confirm_password" name="confirm_password" autocomplete="new-password"<?= $isEdit ? '' : ' required' ?>>
                        <?= $msg ?>
                    </div>

                    <div class="col-12 d-flex justify-content-between flex-wrap gap-2 mt-3">
                        <a href="<?= $isEdit ? 'admin-student-view.php?id=' . $editId : 'admin-students.php' ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> <?= $isEdit ? 'Save Changes' : 'Add Student' ?>
                        </button>
                    </div>
                </div>
            </form>

            <!-- Keep the semester options in line with the chosen year (server checks this too) -->
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const course = document.getElementById('course');
                    const year = document.getElementById('year_of_study');
                    const semester = document.getElementById('semester');

                    function sync() {
                        const maxYears = Number(course.selectedOptions[0]?.dataset.years || 6);
                        [...year.options].forEach(o => { o.hidden = o.disabled = Number(o.value) > maxYears; });
                        if (Number(year.value) > maxYears) year.value = String(maxYears);

                        const y = Number(year.value);
                        [...semester.options].forEach(o => {
                            const s = Number(o.value);
                            o.hidden = o.disabled = s !== y * 2 - 1 && s !== y * 2;
                        });
                        if (semester.selectedOptions[0]?.disabled) semester.value = String(y * 2 - 1);
                    }
                    course.addEventListener('change', sync);
                    year.addEventListener('change', sync);
                    sync();

                    // Digits only in the mobile field
                    const mobile = document.getElementById('mobile');
                    mobile.addEventListener('input', () => { mobile.value = mobile.value.replace(/\D/g, ''); });
                });
            </script>
<?php admin_page_end();
