<?php
/**
 * StudentHub - Event Management: list, search, filter, delete
 * Admins only.
 */

declare(strict_types=1);

require __DIR__ . '/../php/guard.php';
require __DIR__ . '/../php/events.php';
require __DIR__ . '/../php/views/admin-layout.php';

$user = require_role(['admin']);

const EVENT_LIST_DEFAULTS = ['page' => 1, 'per_page' => EVENT_PER_PAGE[0], 'sort' => 'upcoming'];

/* ---------- Delete (POST, then redirect back) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = is_string($_POST['back'] ?? null) && str_starts_with($_POST['back'], 'admin-events.php')
        ? $_POST['back'] : 'admin-events.php';

    if (!csrf_valid()) {
        set_flash('error', 'Your form expired. Please try again.');
    } elseif (($_POST['action'] ?? '') !== 'delete') {
        set_flash('error', 'Unknown action.');
    } else {
        try {
            $event = event_find((int) ($_POST['event_id'] ?? 0));
            if (!$event) {
                set_flash('error', 'That event no longer exists.');
            } elseif (event_delete($event)) {
                audit_log('admin', $user['id'], 'event.delete', 'events', (int) $event['event_id'],
                    "Deleted \"{$event['title']}\" ({$event['registered']} registrations)");
                set_flash('success', "\"{$event['title']}\" was deleted"
                    . ($event['registered'] ? " along with {$event['registered']} registration" . ($event['registered'] == 1 ? '' : 's') : '') . '.');
            } else {
                set_flash('error', 'The event could not be deleted. Please try again.');
            }
        } catch (mysqli_sql_exception $e) {
            error_log('[StudentHub events] ' . $e->getMessage());
            set_flash('error', 'Database error: the event was not deleted. Please try again.');
        }
    }
    header('Location: ' . $back, true, 303);
    exit;
}

/* ---------- List ---------- */
try {
    $filters = event_filters($_GET);
    $result  = event_search($filters);
} catch (mysqli_sql_exception $e) {
    error_log('[StudentHub events] ' . $e->getMessage());
    http_response_code(503);
    exit('The database is unavailable right now. Please make sure MySQL is running and try again.');
}
$filters['page'] = $result['page'];
$url  = fn(array $changes = []): string => filter_url('admin-events.php', $filters, $changes, EVENT_LIST_DEFAULTS);
$here = $url();
$filtered = $filters['q'] !== '' || $filters['type'] !== '' || $filters['status'] !== '' || $filters['when'] !== '';
$first = $result['total'] ? ($result['page'] - 1) * $filters['per_page'] + 1 : 0;
$last  = min($result['total'], $result['page'] * $filters['per_page']);
$today = date('Y-m-d');
$statusColors = ['scheduled' => 'success', 'cancelled' => 'danger', 'completed' => 'secondary'];

admin_page_start('Events', 'events', $user);
?>
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2><i class="fas fa-calendar-alt text-primary me-2"></i> Event Management</h2>
                    <p class="text-muted mb-0">Create campus events, upload posters and track registrations.</p>
                </div>
                <a href="admin-event-form.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add Event</a>
            </div>

            <!-- Search & filters -->
            <form method="get" action="admin-events.php" class="card p-3 mb-3" role="search">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-4">
                        <label for="q" class="form-label small fw-bold mb-1">Search</label>
                        <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>"
                               placeholder="Title, venue, organizer or description" maxlength="60">
                    </div>
                    <div class="col-6 col-lg-2">
                        <label for="type" class="form-label small fw-bold mb-1">Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">All types</option>
                            <?php foreach (EVENT_TYPES as $t): ?>
                                <option value="<?= e($t) ?>"<?= $filters['type'] === $t ? ' selected' : '' ?>><?= e($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-1">
                        <label for="status" class="form-label small fw-bold mb-1">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All</option>
                            <?php foreach (EVENT_STATUSES as $s): ?>
                                <option value="<?= e($s) ?>"<?= $filters['status'] === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-1">
                        <label for="when" class="form-label small fw-bold mb-1">When</label>
                        <select class="form-select" id="when" name="when">
                            <option value="">All</option>
                            <option value="upcoming"<?= $filters['when'] === 'upcoming' ? ' selected' : '' ?>>Upcoming</option>
                            <option value="past"<?= $filters['when'] === 'past' ? ' selected' : '' ?>>Past</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label for="sort" class="form-label small fw-bold mb-1">Sort</label>
                        <select class="form-select" id="sort" name="sort">
                            <?php foreach (EVENT_SORTS as $key => [$label]): ?>
                                <option value="<?= e($key) ?>"<?= $filters['sort'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search me-1"></i> Apply</button>
                        <?php if ($filtered || $filters['sort'] !== 'upcoming'): ?>
                            <a href="admin-events.php" class="btn btn-outline-secondary" title="Clear search and filters" aria-label="Clear search and filters"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <input type="hidden" name="per_page" value="<?= $filters['per_page'] ?>">
            </form>

            <p class="text-muted small mb-2" aria-live="polite">
                <?php if ($result['total']): ?>
                    Showing <?= $first ?>–<?= $last ?> of <?= $result['total'] ?> event<?= $result['total'] === 1 ? '' : 's' ?><?= $filtered ? ' matching your search' : '' ?>
                <?php else: ?>
                    No events found<?= $filtered ? ' matching your search' : '' ?>.
                <?php endif; ?>
            </p>

            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-primary">
                            <tr>
                                <th style="width: 88px">Poster</th>
                                <th>Event</th>
                                <th>Date</th>
                                <th class="d-none d-md-table-cell">Venue</th>
                                <th>Seats</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$result['rows']): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="fas fa-calendar-times fs-3 d-block mb-2"></i>
                                    <?= $filtered ? 'No events match these filters.' : 'No events yet.' ?>
                                    <?php if ($filtered): ?><a href="admin-events.php">Clear filters</a><?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($result['rows'] as $ev): ?>
                            <?php $full = (int) $ev['registered'] >= (int) $ev['seats']; ?>
                            <tr>
                                <td>
                                    <?php if ($ev['image_path']): ?>
                                        <img src="../<?= e($ev['image_path']) ?>" alt="" class="sh-poster-thumb" loading="lazy">
                                    <?php else: ?>
                                        <div class="sh-poster-thumb sh-poster-empty" title="No poster"><i class="fas fa-image"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="admin-event-view.php?id=<?= (int) $ev['event_id'] ?>" class="fw-bold"><?= e($ev['title']) ?></a>
                                    <?php if ($ev['is_featured']): ?><span class="badge bg-primary ms-1" title="Shown in the home page carousel">Featured</span><?php endif; ?>
                                    <div class="small text-muted"><?= e($ev['event_type']) ?> · <?= e($ev['organizer']) ?></div>
                                </td>
                                <td class="text-nowrap">
                                    <?= e(date('d M Y', strtotime($ev['event_date']))) ?>
                                    <div class="small text-muted"><?= e(date('h:i A', strtotime($ev['start_time']))) ?><?= $ev['event_date'] < $today ? ' · Past' : '' ?></div>
                                </td>
                                <td class="d-none d-md-table-cell small"><?= e($ev['venue']) ?></td>
                                <td class="text-nowrap">
                                    <?= (int) $ev['registered'] ?> / <?= (int) $ev['seats'] ?>
                                    <?php if ($full): ?><div class="small text-danger">Full</div><?php endif; ?>
                                </td>
                                <td><span class="badge bg-<?= $statusColors[$ev['status']] ?? 'secondary' ?>"><?= e(ucfirst($ev['status'])) ?></span></td>
                                <td class="text-end text-nowrap">
                                    <a href="admin-event-view.php?id=<?= (int) $ev['event_id'] ?>" class="btn btn-sm btn-outline-primary" title="View" aria-label="View <?= e($ev['title']) ?>"><i class="fas fa-eye"></i></a>
                                    <a href="admin-event-form.php?id=<?= (int) $ev['event_id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit" aria-label="Edit <?= e($ev['title']) ?>"><i class="fas fa-edit"></i></a>
                                    <form method="post" class="d-inline"
                                          data-confirm="Delete &quot;<?= e($ev['title']) ?>&quot;?<?= (int) $ev['registered'] ? ' ' . (int) $ev['registered'] . ' student registration(s) will be deleted too.' : '' ?> This cannot be undone.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="event_id" value="<?= (int) $ev['event_id'] ?>">
                                        <input type="hidden" name="back" value="<?= e($here) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete <?= e($ev['title']) ?>"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination + page size -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3">
                <form method="get" action="admin-events.php" class="d-flex align-items-center gap-2">
                    <?php foreach (['q', 'type', 'status', 'when', 'sort'] as $key): ?>
                        <?php if ($filters[$key] !== ''): ?>
                            <input type="hidden" name="<?= $key ?>" value="<?= e($filters[$key]) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <label for="per_page" class="small text-muted text-nowrap">Rows per page</label>
                    <select class="form-select form-select-sm" id="per_page" name="per_page" onchange="this.form.submit()">
                        <?php foreach (EVENT_PER_PAGE as $n): ?>
                            <option value="<?= $n ?>"<?= $filters['per_page'] === $n ? ' selected' : '' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button class="btn btn-sm btn-outline-primary">Go</button></noscript>
                </form>

                <?php if ($result['pages'] > 1): ?>
                    <nav aria-label="Event pages">
                        <ul class="pagination sh-pagination mb-0 flex-wrap">
                            <li class="page-item<?= $result['page'] === 1 ? ' disabled' : '' ?>">
                                <a class="page-link" href="<?= e($url(['page' => $result['page'] - 1])) ?>" aria-label="Previous page"><i class="fas fa-chevron-left"></i></a>
                            </li>
                            <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
                                <li class="page-item<?= $p === $result['page'] ? ' active' : '' ?>">
                                    <a class="page-link" href="<?= e($url(['page' => $p])) ?>"<?= $p === $result['page'] ? ' aria-current="page"' : '' ?>><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item<?= $result['page'] === $result['pages'] ? ' disabled' : '' ?>">
                                <a class="page-link" href="<?= e($url(['page' => $result['page'] + 1])) ?>" aria-label="Next page"><i class="fas fa-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
<?php admin_page_end();
