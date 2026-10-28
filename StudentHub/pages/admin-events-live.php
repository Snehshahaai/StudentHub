<?php
/**
 * StudentHub - Live Events (admins only)
 * Same data as admin-events.php, but this page never reloads: js/events-live.js
 * talks to the JSON API (php/api/events-api.php) with the Fetch API to list,
 * add, update and delete events.
 */

declare(strict_types=1);

require __DIR__ . '/../php/guard.php';
require __DIR__ . '/../php/events.php';
require __DIR__ . '/../php/views/admin-layout.php';

$user = require_role(['admin']);

admin_page_start('Live Events', 'live', $user);
?>
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2><i class="fas fa-bolt text-primary me-2"></i> Live Events</h2>
                    <p class="text-muted mb-0">Add, edit and delete events instantly &ndash; the page never reloads.</p>
                </div>
                <button type="button" class="btn btn-primary" id="addEventBtn"><i class="fas fa-plus me-1"></i> Add Event</button>
            </div>

            <!-- Search & filters: any change re-fetches the list -->
            <form id="filterForm" class="card p-3 mb-3" role="search" novalidate>
                <div class="row g-2 align-items-end">
                    <div class="col-lg-4">
                        <label for="q" class="form-label small fw-bold mb-1">Search</label>
                        <input type="search" class="form-control" id="q" name="q" placeholder="Title, venue, organizer or description" maxlength="60">
                    </div>
                    <div class="col-6 col-lg-2">
                        <label for="type" class="form-label small fw-bold mb-1">Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">All types</option>
                            <?php foreach (EVENT_TYPES as $t): ?>
                                <option value="<?= e($t) ?>"><?= e($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label for="status" class="form-label small fw-bold mb-1">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All</option>
                            <?php foreach (EVENT_STATUSES as $s): ?>
                                <option value="<?= e($s) ?>"><?= e(ucfirst($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label for="when" class="form-label small fw-bold mb-1">When</label>
                        <select class="form-select" id="when" name="when">
                            <option value="">All</option>
                            <option value="upcoming">Upcoming</option>
                            <option value="past">Past</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label for="sort" class="form-label small fw-bold mb-1">Sort</label>
                        <select class="form-select" id="sort" name="sort">
                            <?php foreach (EVENT_SORTS as $key => [$label]): ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>

            <p class="text-muted small mb-2" id="eventsSummary" aria-live="polite">Loading events&hellip;</p>

            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-primary">
                            <tr>
                                <th>Event</th>
                                <th>Date</th>
                                <th class="d-none d-md-table-cell">Venue</th>
                                <th>Seats</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="eventsBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3">
                <div class="d-flex align-items-center gap-2">
                    <label for="per_page" class="small text-muted text-nowrap">Rows per page</label>
                    <select class="form-select form-select-sm" id="per_page" form="filterForm">
                        <?php foreach (EVENT_PER_PAGE as $n): ?>
                            <option value="<?= $n ?>"><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <nav aria-label="Event pages"><ul class="pagination sh-pagination mb-0 flex-wrap" id="eventsPager"></ul></nav>
            </div>

            <!-- Add / edit -->
            <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalTitle" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <form class="modal-content" id="eventForm" novalidate>
                        <div class="modal-header">
                            <h5 class="modal-title" id="eventModalTitle">Add Event</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger d-none" id="formAlert" role="alert"></div>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="f_title" class="form-label fw-bold">Event Title</label>
                                    <input type="text" class="form-control" id="f_title" name="title" minlength="5" maxlength="150" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-4">
                                    <label for="f_event_type" class="form-label fw-bold">Type</label>
                                    <select class="form-select" id="f_event_type" name="event_type" required>
                                        <option value="">Choose...</option>
                                        <?php foreach (EVENT_TYPES as $t): ?>
                                            <option value="<?= e($t) ?>"><?= e($t) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-12">
                                    <label for="f_description" class="form-label fw-bold">Description</label>
                                    <textarea class="form-control" id="f_description" name="description" rows="3" maxlength="2000" required></textarea>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-4">
                                    <label for="f_event_date" class="form-label fw-bold">Date</label>
                                    <input type="date" class="form-control" id="f_event_date" name="event_date" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label for="f_start_time" class="form-label fw-bold">Start time</label>
                                    <input type="time" class="form-control" id="f_start_time" name="start_time" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label for="f_end_time" class="form-label fw-bold">End time <span class="text-muted fw-normal">(optional)</span></label>
                                    <input type="time" class="form-control" id="f_end_time" name="end_time">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-5">
                                    <label for="f_venue" class="form-label fw-bold">Venue</label>
                                    <input type="text" class="form-control" id="f_venue" name="venue" maxlength="100" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-5">
                                    <label for="f_organizer" class="form-label fw-bold">Organizer</label>
                                    <input type="text" class="form-control" id="f_organizer" name="organizer" maxlength="100" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-2">
                                    <label for="f_seats" class="form-label fw-bold">Seats</label>
                                    <input type="number" class="form-control" id="f_seats" name="seats" min="1" max="<?= EVENT_MAX_SEATS ?>" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-4">
                                    <label for="f_status" class="form-label fw-bold">Status</label>
                                    <select class="form-select" id="f_status" name="status" required>
                                        <?php foreach (EVENT_STATUSES as $s): ?>
                                            <option value="<?= e($s) ?>"><?= e(ucfirst($s)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-8">
                                    <label for="f_registration_deadline" class="form-label fw-bold">Registration deadline <span class="text-muted fw-normal">(optional)</span></label>
                                    <input type="datetime-local" class="form-control" id="f_registration_deadline" name="registration_deadline">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check mt-md-2">
                                        <input class="form-check-input" type="checkbox" id="f_is_team_event" name="is_team_event">
                                        <label class="form-check-label" for="f_is_team_event">Team event</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label for="f_max_team_size" class="form-label fw-bold">Max team size</label>
                                    <input type="number" class="form-control" id="f_max_team_size" name="max_team_size" min="2" max="20" disabled>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check mt-md-2">
                                        <input class="form-check-input" type="checkbox" id="f_is_featured" name="is_featured">
                                        <label class="form-check-label" for="f_is_featured">Featured on home page</label>
                                    </div>
                                </div>
                            </div>
                            <p class="small text-muted mt-3 mb-0" id="posterNote">Posters are managed on the full <a href="admin-events.php">Events</a> page; editing here keeps the current poster.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="saveBtn">Save event</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete confirmation -->
            <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteModalTitle">Delete event?</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="deleteText"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" id="confirmDeleteBtn"><i class="fas fa-trash-alt me-1"></i> Delete</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>window.EVENTS_API = { url: '../php/api/events-api.php', csrf: <?= json_encode(csrf_token()) ?> };</script>
            <script src="../js/events-live.js" defer></script>
<?php admin_page_end();
