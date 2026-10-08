<?php
/**
 * StudentHub - Event Management: one event, its poster and registrations
 * Admins only.
 */

declare(strict_types=1);

require __DIR__ . '/../php/guard.php';
require __DIR__ . '/../php/events.php';
require __DIR__ . '/../php/views/admin-layout.php';

$user = require_role(['admin']);
$id   = (int) ($_GET['id'] ?? 0);

try {
    $event = event_find($id);
    $registrations = $event ? event_registrations($id) : [];
} catch (mysqli_sql_exception $e) {
    error_log('[StudentHub event view] ' . $e->getMessage());
    http_response_code(503);
    exit('The database is unavailable right now. Please make sure MySQL is running and try again.');
}

if (!$event) {
    set_flash('error', 'That event does not exist or has been deleted.');
    header('Location: admin-events.php', true, 303);
    exit;
}

$registered   = (int) $event['registered'];
$seatsLeft    = max(0, (int) $event['seats'] - $registered);
$fillPercent  = min(100, (int) round($registered * 100 / max(1, (int) $event['seats'])));
$statusColors = ['scheduled' => 'success', 'cancelled' => 'danger', 'completed' => 'secondary'];

admin_page_start($event['title'], 'events', $user);
?>
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item"><a href="admin-events.php">Events</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($event['title']) ?></li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="mb-1"><?= e($event['title']) ?></h2>
                    <p class="mb-0">
                        <span class="badge bg-<?= $statusColors[$event['status']] ?? 'secondary' ?>"><?= e(ucfirst($event['status'])) ?></span>
                        <span class="badge bg-primary"><?= e($event['event_type']) ?></span>
                        <?php if ($event['is_featured']): ?><span class="badge bg-primary">Featured on home page</span><?php endif; ?>
                        <?php if ($event['is_team_event']): ?><span class="badge bg-secondary">Teams of up to <?= (int) $event['max_team_size'] ?></span><?php endif; ?>
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="admin-event-form.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit me-1"></i> Edit</a>
                    <form method="post" action="admin-events.php"
                          data-confirm="Delete &quot;<?= e($event['title']) ?>&quot;?<?= $registered ? " {$registered} student registration(s) will be deleted too." : '' ?> This cannot be undone.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="event_id" value="<?= $id ?>">
                        <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash-alt me-1"></i> Delete</button>
                    </form>
                </div>
            </div>

            <div class="row g-4">
                <!-- Poster -->
                <div class="col-lg-4">
                    <div class="card p-3 h-100">
                        <?php if ($event['image_path']): ?>
                            <img src="../<?= e($event['image_path']) ?>" alt="Poster for <?= e($event['title']) ?>" class="img-fluid rounded">
                        <?php else: ?>
                            <div class="sh-poster-preview">
                                <span class="text-muted small"><i class="fas fa-image fs-2 d-block mb-2"></i>No poster uploaded.
                                    <a href="admin-event-form.php?id=<?= $id ?>">Add one</a></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Details -->
                <div class="col-lg-8">
                    <div class="card p-4 h-100">
                        <p class="mb-4"><?= nl2br(e($event['description'])) ?></p>
                        <dl class="row small mb-4">
                            <dt class="col-sm-4">Date</dt>
                            <dd class="col-sm-8"><?= e(date('l, d F Y', strtotime($event['event_date']))) ?></dd>
                            <dt class="col-sm-4">Time</dt>
                            <dd class="col-sm-8"><?= e(date('h:i A', strtotime($event['start_time']))) ?><?= $event['end_time'] ? ' – ' . e(date('h:i A', strtotime($event['end_time']))) : '' ?></dd>
                            <dt class="col-sm-4">Venue</dt>
                            <dd class="col-sm-8"><?= e($event['venue']) ?></dd>
                            <dt class="col-sm-4">Organizer</dt>
                            <dd class="col-sm-8"><?= e($event['organizer']) ?></dd>
                            <dt class="col-sm-4">Registration closes</dt>
                            <dd class="col-sm-8"><?= $event['registration_deadline'] ? e(date('d M Y, h:i A', strtotime($event['registration_deadline']))) : '<span class="text-muted">Not set</span>' ?></dd>
                            <dt class="col-sm-4">Created by</dt>
                            <dd class="col-sm-8"><?= $event['created_by_name'] ? e($event['created_by_name']) : '<span class="text-muted">Unknown</span>' ?> · <?= e(date('d M Y', strtotime($event['created_at']))) ?></dd>
                        </dl>

                        <div class="d-flex justify-content-between small mb-1">
                            <strong>Seats</strong>
                            <span><?= $registered ?> registered · <?= $seatsLeft ?> left of <?= (int) $event['seats'] ?></span>
                        </div>
                        <div class="progress" role="progressbar" aria-label="Seats filled" aria-valuenow="<?= $fillPercent ?>" aria-valuemin="0" aria-valuemax="100" style="height: 8px">
                            <div class="progress-bar" style="width: <?= $fillPercent ?>%"></div>
                        </div>
                    </div>
                </div>

                <!-- Registrations -->
                <div class="col-12">
                    <div class="card p-4">
                        <h5 class="mb-3"><i class="fas fa-users text-primary me-2"></i> Registrations (<?= count($registrations) ?>)</h5>
                        <?php if ($registrations): ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead><tr><th>Student</th><th>Enrollment No.</th><th>Team</th><th>Status</th><th>Registered</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($registrations as $r): ?>
                                        <tr>
                                            <td><?= e($r['full_name']) ?><div class="small text-muted"><?= e($r['email']) ?></div></td>
                                            <td class="font-monospace"><?= e($r['enrollment_no']) ?></td>
                                            <td><?= $r['team_name'] ? e($r['team_name']) . ' (' . (int) $r['team_size'] . ')' : '<span class="text-muted">—</span>' ?></td>
                                            <td><?= e(ucfirst($r['status'])) ?></td>
                                            <td class="small"><?= e(date('d M Y, h:i A', strtotime($r['registered_at']))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No students have registered yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
<?php admin_page_end();
