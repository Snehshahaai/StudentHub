<?php
/**
 * StudentHub - Event Management: add (no id) or edit (?id=) an event
 * Admins only. Validates every field and the poster upload (type, size,
 * dimensions) on the server, saves with MySQLi prepared statements, then
 * redirects with a success message (Post/Redirect/Get).
 */

declare(strict_types=1);

require __DIR__ . '/../php/guard.php';
require __DIR__ . '/../php/events.php';
require __DIR__ . '/../php/views/admin-layout.php';

$user = require_role(['admin']);

try {
    $editId   = (int) ($_GET['id'] ?? 0);
    $existing = $editId ? event_find($editId) : null;
} catch (mysqli_sql_exception $e) {
    error_log('[StudentHub event form] ' . $e->getMessage());
    http_response_code(503);
    exit('The database is unavailable right now. Please make sure MySQL is running and try again.');
}

if ($editId && !$existing) {
    set_flash('error', 'That event no longer exists.');
    header('Location: admin-events.php', true, 303);
    exit;
}

$isEdit = $existing !== null;
$errors = [];

// Form values: what was posted, else the stored event, else defaults
$values = $isEdit ? [
    'title'                 => $existing['title'],
    'event_type'            => $existing['event_type'],
    'description'           => $existing['description'],
    'event_date'            => $existing['event_date'],
    'start_time'            => substr($existing['start_time'], 0, 5),
    'end_time'              => $existing['end_time'] ? substr($existing['end_time'], 0, 5) : '',
    'venue'                 => $existing['venue'],
    'organizer'             => $existing['organizer'],
    'seats'                 => $existing['seats'],
    'is_team_event'         => (bool) $existing['is_team_event'],
    'max_team_size'         => (string) $existing['max_team_size'],
    'registration_deadline' => $existing['registration_deadline'] ? date('Y-m-d\TH:i', strtotime($existing['registration_deadline'])) : '',
    'is_featured'           => (bool) $existing['is_featured'],
    'status'                => $existing['status'],
] : [
    'title' => '', 'event_type' => '', 'description' => '', 'event_date' => '', 'start_time' => '10:00',
    'end_time' => '', 'venue' => '', 'organizer' => '', 'seats' => '100', 'is_team_event' => false,
    'max_team_size' => '', 'registration_deadline' => '', 'is_featured' => false, 'status' => 'scheduled',
];

/* ---------- Save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_too_large()) {
        // Bigger than post_max_size: PHP drops every field, including the CSRF token
        $errors['form'] = 'The upload was far too large and nothing was received. Posters can be at most '
            . human_size(POSTER_MAX_BYTES) . '.';
    } elseif (!csrf_valid()) {
        $errors['form'] = 'Your form expired. Please submit it again.';
    } else {
        $values = event_input($_POST);
        $form   = $values;                      // keep what was typed for re-display
        $errors = event_validate($values, $existing);

        // Validate + save the poster only when the other fields are fine, so a
        // rejected form never leaves an orphan file behind
        $upload = ['path' => null];
        if (!$errors) {
            $upload = save_poster_upload($_FILES['poster'] ?? null);
            if (isset($upload['error'])) {
                $errors['poster'] = $upload['error'];
            }
        } elseif (($_FILES['poster']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $errors['poster'] = 'Please choose the poster again after fixing the other fields.';
        }

        if (!$errors) {
            $oldPoster = $existing['image_path'] ?? null;
            $newPoster = $upload['path'] ?? ($values['remove_poster'] ? null : $oldPoster);

            try {
                if ($isEdit) {
                    $changed = event_changes($existing, $values, $newPoster !== $oldPoster);
                    event_update($editId, $values, $newPoster);
                    if ($newPoster !== $oldPoster) {
                        delete_poster_file($oldPoster);       // replaced or removed
                    }
                    audit_log('admin', $user['id'], 'event.update', 'events', $editId,
                        $changed ? 'Changed: ' . implode(', ', $changed) : 'No changes');
                    set_flash('success', $changed
                        ? "Saved changes to \"{$values['title']}\": " . implode(', ', $changed) . '.'
                        : "No changes were made to \"{$values['title']}\".");
                    header('Location: admin-event-view.php?id=' . $editId, true, 303);
                } else {
                    $newId = event_create($values, $newPoster, $user['id']);
                    audit_log('admin', $user['id'], 'event.create', 'events', $newId, "Created \"{$values['title']}\"");
                    set_flash('success', "Event \"{$values['title']}\" was created"
                        . ($newPoster ? ' with its poster.' : '. You can add a poster any time by editing it.'));
                    header('Location: admin-event-view.php?id=' . $newId, true, 303);
                }
                exit;
            } catch (mysqli_sql_exception $e) {
                // The database refused: don't keep a poster nobody points to
                if ($upload['path'] ?? null) {
                    delete_poster_file($upload['path']);
                }
                error_log('[StudentHub event form] ' . $e->getMessage());
                $errors['form'] = 'Database error: the event was not saved. Please try again.';
            }
        }
        $values = $form;
    }
}

function field_state(array $errors, string $field): array
{
    return isset($errors[$field])
        ? [' is-invalid', '<div class="invalid-feedback">' . e($errors[$field]) . '</div>']
        : ['', ''];
}

$poster = $existing['image_path'] ?? null;
$title  = $isEdit ? "Edit {$existing['title']}" : 'Add Event';
admin_page_start($title, 'events', $user);
?>
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item"><a href="admin-events.php">Events</a></li>
                    <?php if ($isEdit): ?>
                        <li class="breadcrumb-item"><a href="admin-event-view.php?id=<?= $editId ?>"><?= e($existing['title']) ?></a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Edit' : 'Add' ?></li>
                </ol>
            </nav>

            <h2 class="mb-4"><i class="fas <?= $isEdit ? 'fa-edit' : 'fa-calendar-plus' ?> text-primary me-2"></i> <?= e($title) ?></h2>

            <?php if ($errors): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= isset($errors['form']) ? e($errors['form']) : 'The event was not saved. Please fix the ' . count($errors) . ' highlighted field' . (count($errors) === 1 ? '' : 's') . '.' ?>
                </div>
            <?php endif; ?>

            <!-- multipart/form-data is required for file uploads -->
            <form method="post" enctype="multipart/form-data" class="card p-4 shadow-sm" id="eventForm" novalidate>
                <?= csrf_field() ?>
                <!-- Browsers use this to refuse oversized files early; the server checks again -->
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= POSTER_MAX_BYTES ?>">

                <div class="row g-4">
                    <!-- Event details -->
                    <div class="col-lg-8">
                        <div class="row g-3">
                            <?php [$cls, $msg] = field_state($errors, 'title'); ?>
                            <div class="col-md-8">
                                <label for="title" class="form-label fw-bold">Event Title</label>
                                <input type="text" class="form-control<?= $cls ?>" id="title" name="title" value="<?= e($values['title']) ?>" minlength="5" maxlength="150" required>
                                <?= $msg ?>
                            </div>
                            <?php [$cls, $msg] = field_state($errors, 'event_type'); ?>
                            <div class="col-md-4">
                                <label for="event_type" class="form-label fw-bold">Type</label>
                                <select class="form-select<?= $cls ?>" id="event_type" name="event_type" required>
                                    <option value="">Choose...</option>
                                    <?php foreach (EVENT_TYPES as $t): ?>
                                        <option value="<?= e($t) ?>"<?= $values['event_type'] === $t ? ' selected' : '' ?>><?= e($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= $msg ?>
                            </div>

                            <?php [$cls, $msg] = field_state($errors, 'description'); ?>
                            <div class="col-12">
                                <label for="description" class="form-label fw-bold">Description</label>
                                <textarea class="form-control<?= $cls ?>" id="description" name="description" rows="4" minlength="20" maxlength="2000" required><?= e($values['description']) ?></textarea>
                                <?= $msg ?>
                            </div>

                            <?php [$cls, $msg] = field_state($errors, 'event_date'); ?>
                            <div class="col-md-4">
                                <label for="event_date" class="form-label fw-bold">Date</label>
                                <input type="date" class="form-control<?= $cls ?>" id="event_date" name="event_date" value="<?= e($values['event_date']) ?>"<?= $isEdit ? '' : ' min="' . date('Y-m-d') . '"' ?> required>
                                <?= $msg ?>
                            </div>
                            <?php [$cls, $msg] = field_state($errors, 'start_time'); ?>
                            <div class="col-6 col-md-4">
                                <label for="start_time" class="form-label fw-bold">Starts</label>
                                <input type="time" class="form-control<?= $cls ?>" id="start_time" name="start_time" value="<?= e($values['start_time']) ?>" required>
                                <?= $msg ?>
                            </div>
                            <?php [$cls, $msg] = field_state($errors, 'end_time'); ?>
                            <div class="col-6 col-md-4">
                                <label for="end_time" class="form-label fw-bold">Ends <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="time" class="form-control<?= $cls ?>" id="end_time" name="end_time" value="<?= e($values['end_time'] ?? '') ?>">
                                <?= $msg ?>
                            </div>

                            <?php [$cls, $msg] = field_state($errors, 'venue'); ?>
                            <div class="col-md-6">
                                <label for="venue" class="form-label fw-bold">Venue</label>
                                <input type="text" class="form-control<?= $cls ?>" id="venue" name="venue" value="<?= e($values['venue']) ?>" maxlength="100" required>
                                <?= $msg ?>
                            </div>
                            <?php [$cls, $msg] = field_state($errors, 'organizer'); ?>
                            <div class="col-md-6">
                                <label for="organizer" class="form-label fw-bold">Organizer</label>
                                <input type="text" class="form-control<?= $cls ?>" id="organizer" name="organizer" value="<?= e($values['organizer']) ?>" maxlength="100" required>
                                <?= $msg ?>
                            </div>

                            <?php [$cls, $msg] = field_state($errors, 'seats'); ?>
                            <div class="col-6 col-md-3">
                                <label for="seats" class="form-label fw-bold">Seats</label>
                                <input type="number" class="form-control<?= $cls ?>" id="seats" name="seats" value="<?= e($values['seats']) ?>" min="<?= max(1, (int) ($existing['registered'] ?? 0)) ?>" max="<?= EVENT_MAX_SEATS ?>" required>
                                <?php if ($isEdit && (int) $existing['registered']): ?><div class="form-text"><?= (int) $existing['registered'] ?> already registered</div><?php endif; ?>
                                <?= $msg ?>
                            </div>
                            <?php [$cls, $msg] = field_state($errors, 'registration_deadline'); ?>
                            <div class="col-6 col-md-5">
                                <label for="registration_deadline" class="form-label fw-bold">Registration closes <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="datetime-local" class="form-control<?= $cls ?>" id="registration_deadline" name="registration_deadline" value="<?= e($values['registration_deadline'] ?? '') ?>">
                                <?= $msg ?>
                            </div>
                            <?php [$cls, $msg] = field_state($errors, 'status'); ?>
                            <div class="col-md-4">
                                <label for="status" class="form-label fw-bold">Status</label>
                                <select class="form-select<?= $cls ?>" id="status" name="status" required>
                                    <?php foreach (EVENT_STATUSES as $s): ?>
                                        <option value="<?= e($s) ?>"<?= $values['status'] === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= $msg ?>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_team_event" name="is_team_event" value="1"<?= $values['is_team_event'] ? ' checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="is_team_event">Team event</label>
                                </div>
                            </div>
                            <?php [$cls, $msg] = field_state($errors, 'max_team_size'); ?>
                            <div class="col-md-6" id="teamSizeGroup">
                                <label for="max_team_size" class="form-label fw-bold">Max team size</label>
                                <input type="number" class="form-control<?= $cls ?>" id="max_team_size" name="max_team_size" value="<?= e($values['max_team_size'] ?? '') ?>" min="2" max="20">
                                <?= $msg ?>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_featured" name="is_featured" value="1"<?= $values['is_featured'] ? ' checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="is_featured">Feature on the home page carousel</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Poster upload -->
                    <div class="col-lg-4">
                        <?php [$cls, $msg] = field_state($errors, 'poster'); ?>
                        <label for="poster" class="form-label fw-bold">Event Poster <span class="text-muted fw-normal">(optional)</span></label>
                        <div class="sh-poster-preview mb-2" id="posterPreview">
                            <?php if ($poster): ?>
                                <img src="../<?= e($poster) ?>" alt="Current poster">
                            <?php else: ?>
                                <span class="text-muted small"><i class="fas fa-image fs-2 d-block mb-2"></i>No poster yet</span>
                            <?php endif; ?>
                        </div>
                        <input type="file" class="form-control<?= $cls ?>" id="poster" name="poster" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <div class="form-text">
                            JPG, PNG or WebP · max <?= human_size(POSTER_MAX_BYTES) ?> · at least <?= POSTER_MIN_WIDTH ?>×<?= POSTER_MIN_HEIGHT ?> px.
                            <?= $isEdit && $poster ? 'Choosing a file replaces the current poster.' : '' ?>
                        </div>
                        <?= $msg ?>
                        <div class="invalid-feedback" id="posterClientError"></div>

                        <?php if ($isEdit && $poster): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="remove_poster" name="remove_poster" value="1">
                                <label class="form-check-label small" for="remove_poster">Remove the current poster</label>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 d-flex justify-content-between flex-wrap gap-2">
                        <a href="<?= $isEdit ? 'admin-event-view.php?id=' . $editId : 'admin-events.php' ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> <?= $isEdit ? 'Save Changes' : 'Create Event' ?>
                        </button>
                    </div>
                </div>
            </form>

            <script>
                // Instant feedback only: php/uploads.php checks everything again on the server
                document.addEventListener('DOMContentLoaded', () => {
                    const MAX_BYTES = <?= POSTER_MAX_BYTES ?>;
                    const TYPES = ['image/jpeg', 'image/png', 'image/webp'];
                    const input = document.getElementById('poster');
                    const preview = document.getElementById('posterPreview');
                    const error = document.getElementById('posterClientError');
                    const original = preview.innerHTML;
                    const remove = document.getElementById('remove_poster');

                    input.addEventListener('change', () => {
                        const file = input.files[0];
                        let message = '';
                        if (file && !TYPES.includes(file.type)) {
                            message = 'Only JPG, PNG or WebP images can be uploaded.';
                        } else if (file && file.size > MAX_BYTES) {
                            message = `This file is ${(file.size / 1048576).toFixed(1)} MB. The maximum is ${MAX_BYTES / 1048576} MB.`;
                        }

                        error.textContent = message;
                        error.classList.toggle('d-block', Boolean(message));
                        input.classList.toggle('is-invalid', Boolean(message));
                        if (message) input.value = '';

                        if (file && !message) {
                            const img = document.createElement('img');
                            img.alt = 'New poster preview';
                            img.src = URL.createObjectURL(file);
                            preview.replaceChildren(img);
                            if (remove) remove.checked = false;
                        } else {
                            preview.innerHTML = original;
                        }
                    });

                    // Team size only matters for team events
                    const team = document.getElementById('is_team_event');
                    const sizeGroup = document.getElementById('teamSizeGroup');
                    const syncTeam = () => { sizeGroup.hidden = !team.checked; };
                    team.addEventListener('change', syncTeam);
                    syncTeam();
                });
            </script>
<?php admin_page_end();
