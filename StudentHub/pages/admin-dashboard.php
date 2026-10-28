<?php
/**
 * StudentHub - Admin dashboard (admins only)
 * User statistics, student and event counts, and the latest registrations.
 * Faculty are sent to their own dashboard by php/guard.php.
 */

declare(strict_types=1);

require __DIR__ . '/../php/guard.php';
require __DIR__ . '/../php/dashboard.php';
require __DIR__ . '/../php/views/admin-layout.php';

$user = require_role(['admin']);

try {
    $users   = dashboard_user_stats();
    $events  = dashboard_event_stats();
    $latestStudents = dashboard_latest_students();
    $latestSignups  = dashboard_latest_event_registrations();
    $dbError = false;
} catch (mysqli_sql_exception $e) {
    error_log('[StudentHub admin dashboard] ' . $e->getMessage());
    $users = $events = $latestStudents = $latestSignups = [];
    $dbError = true;
}

$n = fn(array $row, string $key): int => (int) ($row[$key] ?? 0);
$totalStudents = $n($users, 'students');
$statusBars = ['active' => 'success', 'pending' => 'warning', 'inactive' => 'secondary', 'suspended' => 'danger'];
$regColors  = ['registered' => 'success', 'attended' => 'primary', 'waitlisted' => 'warning text-dark', 'cancelled' => 'secondary'];

/** "5 min ago", "3 days ago", else a date. */
function time_ago(string $timestamp): string
{
    $seconds = time() - strtotime($timestamp);
    return match (true) {
        $seconds < 60     => 'just now',
        $seconds < 3600   => intdiv($seconds, 60) . ' min ago',
        $seconds < 86400  => intdiv($seconds, 3600) . ' h ago',
        $seconds < 604800 => intdiv($seconds, 86400) . ' d ago',
        default           => date('d M Y', strtotime($timestamp)),
    };
}

admin_page_start('Dashboard', 'dashboard', $user);
?>
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2><i class="fas fa-tachometer-alt text-primary me-2"></i> Admin Dashboard</h2>
                    <p class="text-muted mb-0">Users, events and the latest registrations at a glance.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="admin-students.php" class="btn btn-outline-primary"><i class="fas fa-users me-1"></i> Students</a>
                    <a href="admin-event-form.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add Event</a>
                </div>
            </div>

            <?php if ($dbError): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    The database is unavailable right now, so the figures could not be loaded.
                </div>
            <?php endif; ?>

            <!-- Headline numbers -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card p-3 text-center border-start border-4 border-primary h-100">
                        <small class="text-muted fw-bold">STUDENTS</small>
                        <h2 class="fw-bold mb-0 text-primary"><?= number_format($totalStudents) ?></h2>
                        <small class="text-muted"><?= number_format($n($users, 'new_this_week')) ?> new this week</small>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card p-3 text-center border-start border-4 border-warning h-100">
                        <small class="text-muted fw-bold">PENDING APPROVALS</small>
                        <h2 class="fw-bold mb-0 text-warning"><?= number_format($n($users, 'pending')) ?></h2>
                        <small><a href="admin-students.php?status=pending">Review students</a></small>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card p-3 text-center border-start border-4 border-success h-100">
                        <small class="text-muted fw-bold">EVENTS</small>
                        <h2 class="fw-bold mb-0 text-success"><?= number_format($n($events, 'total')) ?></h2>
                        <small class="text-muted"><?= number_format($n($events, 'upcoming')) ?> upcoming</small>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card p-3 text-center border-start border-4 border-info h-100">
                        <small class="text-muted fw-bold">EVENT SIGN-UPS</small>
                        <h2 class="fw-bold mb-0 text-info"><?= number_format($n($events, 'registrations')) ?></h2>
                        <small class="text-muted">across all events</small>
                    </div>
                </div>
            </div>

            <!-- User statistics -->
            <div class="row g-4 mb-4">
                <div class="col-lg-7">
                    <div class="card shadow-sm h-100 p-3">
                        <h5 class="fw-bold mb-3"><i class="fas fa-chart-pie text-primary me-2"></i> Students by status</h5>
                        <?php if ($totalStudents): ?>
                            <div class="progress mb-3" style="height: 14px" role="img"
                                 aria-label="Students by status: <?= implode(', ', array_map(fn($s) => $n($users, $s) . ' ' . $s, array_keys($statusBars))) ?>">
                                <?php foreach ($statusBars as $status => $color): ?>
                                    <?php if ($n($users, $status)): ?>
                                        <div class="progress-bar bg-<?= $color ?>" style="width: <?= round($n($users, $status) * 100 / $totalStudents, 2) ?>%"></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="row g-2">
                                <?php foreach ($statusBars as $status => $color): ?>
                                    <div class="col-6 col-md-3">
                                        <span class="badge bg-<?= $color ?><?= $color === 'warning' ? ' text-dark' : '' ?>"><?= e(ucfirst($status)) ?></span>
                                        <strong class="ms-1"><?= number_format($n($users, $status)) ?></strong>
                                        <div class="small text-muted"><?= round($n($users, $status) * 100 / $totalStudents) ?>%</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No students have registered yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card shadow-sm h-100 p-3">
                        <h5 class="fw-bold mb-3"><i class="fas fa-user-friends text-primary me-2"></i> Users by role</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item bg-transparent d-flex justify-content-between px-0"><span><i class="fas fa-user-graduate me-2 text-muted"></i>Students</span><strong><?= number_format($totalStudents) ?></strong></li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between px-0"><span><i class="fas fa-chalkboard-teacher me-2 text-muted"></i>Faculty</span><strong><?= number_format($n($users, 'faculty')) ?></strong></li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between px-0"><span><i class="fas fa-user-shield me-2 text-muted"></i>Admins</span><strong><?= number_format($n($users, 'admins')) ?></strong></li>
                        </ul>
                        <p class="small text-muted mt-3 mb-0">
                            Events: <?= $n($events, 'upcoming') ?> upcoming · <?= $n($events, 'completed') ?> completed · <?= $n($events, 'cancelled') ?> cancelled
                        </p>
                    </div>
                </div>
            </div>

            <!-- Latest registrations -->
            <div class="row g-4">
                <div class="col-xl-7">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-surface py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-user-plus text-primary me-2"></i> Latest student registrations</h5>
                            <a href="admin-students.php" class="btn btn-sm btn-outline-primary">All students</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-primary">
                                    <tr><th>Student</th><th class="d-none d-md-table-cell">Department</th><th>Status</th><th>Registered</th></tr>
                                </thead>
                                <tbody>
                                <?php if (!$latestStudents): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">No students yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($latestStudents as $s): ?>
                                    <tr>
                                        <td>
                                            <a href="admin-student-view.php?id=<?= (int) $s['student_id'] ?>" class="fw-bold"><?= e($s['full_name']) ?></a>
                                            <div class="small text-muted"><?= e($s['enrollment_no']) ?></div>
                                        </td>
                                        <td class="d-none d-md-table-cell"><?= e($s['department']) ?></td>
                                        <td><?= status_badge($s['status']) ?></td>
                                        <td class="text-nowrap small" title="<?= e($s['created_at']) ?>"><?= e(time_ago($s['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-surface py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-ticket-alt text-primary me-2"></i> Latest event sign-ups</h5>
                            <a href="admin-events.php" class="btn btn-sm btn-outline-primary">All events</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-primary">
                                    <tr><th>Student</th><th>Event</th><th>When</th></tr>
                                </thead>
                                <tbody>
                                <?php if (!$latestSignups): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">No event sign-ups yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($latestSignups as $r): ?>
                                    <tr>
                                        <td>
                                            <a href="admin-student-view.php?id=<?= (int) $r['student_id'] ?>" class="fw-bold"><?= e($r['full_name']) ?></a>
                                            <div><span class="badge bg-<?= $regColors[$r['status']] ?? 'secondary' ?>"><?= e(ucfirst($r['status'])) ?></span></div>
                                        </td>
                                        <td><a href="admin-event-view.php?id=<?= (int) $r['event_id'] ?>"><?= e($r['event_title']) ?></a>
                                            <?php if ($r['team_name']): ?><div class="small text-muted">Team: <?= e($r['team_name']) ?></div><?php endif; ?></td>
                                        <td class="text-nowrap small" title="<?= e($r['registered_at']) ?>"><?= e(time_ago($r['registered_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
<?php admin_page_end();
