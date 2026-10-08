<?php
/**
 * StudentHub - Student Management: one student's full record
 * Faculty and admins can view; only admins see edit / approve / delete.
 */

declare(strict_types=1);

require __DIR__ . '/../php/guard.php';
require __DIR__ . '/../php/students.php';
require __DIR__ . '/../php/views/admin-layout.php';

$user    = require_role(['faculty', 'admin']);
$isAdmin = $user['role'] === 'admin';
$id      = (int) ($_GET['id'] ?? 0);

try {
    $student = student_find($id);
    if ($student) {
        $related    = student_related_counts($id);
        $attendance = db_all(
            'SELECT subject_code, subject_name, lectures_attended, total_conducted, percentage, status
               FROM v_attendance_summary WHERE student_id = ? ORDER BY subject_code',
            [$id]
        );
        $results = db_all(
            'SELECT semester, sgpa, cgpa, result_status, published_on
               FROM semester_results WHERE student_id = ? ORDER BY semester',
            [$id]
        );
        $events = db_all(
            'SELECT e.title, e.event_date, r.team_name, r.status
               FROM event_registrations r JOIN events e ON e.event_id = r.event_id
              WHERE r.student_id = ? ORDER BY e.event_date DESC',
            [$id]
        );
        $approvedBy = $student['approved_by']
            ? db_value('SELECT full_name FROM faculty WHERE faculty_id = ?', [(int) $student['approved_by']])
            : null;
    }
} catch (mysqli_sql_exception $e) {
    error_log('[StudentHub student view] ' . $e->getMessage());
    http_response_code(503);
    exit('The database is unavailable right now. Please make sure MySQL is running and try again.');
}

if (!$student) {
    set_flash('error', 'That student does not exist or has been deleted.');
    header('Location: admin-students.php', true, 303);
    exit;
}

function show_date(?string $value, string $format = 'd M Y, h:i A'): string
{
    return $value ? e(date($format, strtotime($value))) : '<span class="text-muted">Never</span>';
}

admin_page_start($student['full_name'], 'students', $user);
?>
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item"><a href="admin-students.php">Students</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($student['enrollment_no']) ?></li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="fs-1 text-primary"><i class="fas fa-user-graduate"></i></div>
                    <div>
                        <h2 class="mb-1"><?= e($student['full_name']) ?> <?= status_badge($student['status']) ?></h2>
                        <p class="text-muted mb-0">
                            <span class="font-monospace fw-semibold"><?= e($student['enrollment_no']) ?></span> · @<?= e($student['username']) ?> ·
                            <?= e($student['course_name']) ?> · Year <?= (int) $student['year_of_study'] ?>, Sem <?= (int) $student['semester'] ?>
                        </p>
                    </div>
                </div>

                <?php if ($isAdmin): ?>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if ($student['status'] === 'pending'): ?>
                            <form method="post" action="admin-students.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="student_id" value="<?= $id ?>">
                                <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i> Approve</button>
                            </form>
                        <?php endif; ?>
                        <a href="admin-student-form.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit me-1"></i> Edit</a>
                        <form method="post" action="admin-students.php"
                              data-confirm="Delete <?= e($student['full_name']) ?> (<?= e($student['enrollment_no']) ?>)? This also deletes <?= (int) $related['attendance'] ?> attendance records, <?= (int) $related['submissions'] ?> submissions, <?= (int) $related['results'] ?> results and <?= (int) $related['event_registrations'] ?> event registrations. This cannot be undone.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="student_id" value="<?= $id ?>">
                            <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash-alt me-1"></i> Delete</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row g-4">
                <!-- Personal & account details -->
                <div class="col-lg-5">
                    <div class="card p-4 h-100">
                        <h5 class="mb-3"><i class="fas fa-id-card text-primary me-2"></i> Details</h5>
                        <dl class="row mb-0 small">
                            <dt class="col-5">Email</dt>        <dd class="col-7"><?= e($student['email']) ?></dd>
                            <dt class="col-5">Mobile</dt>       <dd class="col-7">+91 <?= e($student['mobile']) ?></dd>
                            <dt class="col-5">Gender</dt>       <dd class="col-7"><?= e($student['gender']) ?></dd>
                            <dt class="col-5">Department</dt>   <dd class="col-7"><?= e($student['department']) ?></dd>
                            <dt class="col-5">Address</dt>      <dd class="col-7"><?= $student['address'] !== null ? nl2br(e($student['address'])) : '<span class="text-muted">Not given</span>' ?></dd>
                            <dt class="col-5">Registered</dt>   <dd class="col-7"><?= show_date($student['created_at']) ?></dd>
                            <dt class="col-5">Approved</dt>     <dd class="col-7"><?= show_date($student['approved_at']) ?><?= $approvedBy ? ' by ' . e($approvedBy) : '' ?></dd>
                            <dt class="col-5">Last login</dt>   <dd class="col-7"><?= show_date($student['last_login_at']) ?></dd>
                            <dt class="col-5">Last updated</dt> <dd class="col-7"><?= show_date($student['updated_at']) ?></dd>
                        </dl>
                    </div>
                </div>

                <!-- Attendance -->
                <div class="col-lg-7">
                    <div class="card p-4 h-100">
                        <h5 class="mb-3"><i class="fas fa-user-check text-primary me-2"></i> Attendance</h5>
                        <?php if ($attendance): ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead><tr><th>Subject</th><th class="text-end">Attended</th><th class="text-end">%</th><th>Status</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($attendance as $a): ?>
                                        <tr>
                                            <td><span class="font-monospace"><?= e($a['subject_code']) ?></span> <?= e($a['subject_name']) ?></td>
                                            <td class="text-end"><?= (int) $a['lectures_attended'] ?> / <?= (int) $a['total_conducted'] ?></td>
                                            <td class="text-end"><?= e($a['percentage']) ?>%</td>
                                            <td><span class="badge bg-<?= $a['status'] === 'Good' ? 'success' : 'danger' ?>"><?= e($a['status']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No attendance recorded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Results -->
                <div class="col-lg-6">
                    <div class="card p-4 h-100">
                        <h5 class="mb-3"><i class="fas fa-award text-primary me-2"></i> Semester Results</h5>
                        <?php if ($results): ?>
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Semester</th><th class="text-end">SGPA</th><th class="text-end">CGPA</th><th>Result</th></tr></thead>
                                <tbody>
                                <?php foreach ($results as $r): ?>
                                    <tr>
                                        <td><?= (int) $r['semester'] ?></td>
                                        <td class="text-end"><?= e($r['sgpa']) ?></td>
                                        <td class="text-end fw-bold"><?= e($r['cgpa']) ?></td>
                                        <td><?= e(ucfirst($r['result_status'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-muted mb-0">No results published yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Events -->
                <div class="col-lg-6">
                    <div class="card p-4 h-100">
                        <h5 class="mb-3"><i class="fas fa-calendar-alt text-primary me-2"></i> Event Registrations</h5>
                        <?php if ($events): ?>
                            <ul class="list-unstyled mb-0 small">
                                <?php foreach ($events as $ev): ?>
                                    <li class="mb-2">
                                        <strong><?= e($ev['title']) ?></strong>
                                        <span class="text-muted">· <?= e(date('d M Y', strtotime($ev['event_date']))) ?></span>
                                        <?= $ev['team_name'] ? '· Team ' . e($ev['team_name']) : '' ?>
                                        <span class="badge bg-secondary ms-1"><?= e(ucfirst($ev['status'])) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted mb-0">Not registered for any events.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
<?php admin_page_end();
