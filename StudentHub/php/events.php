<?php
/**
 * StudentHub - Event records (data layer for the event management module)
 * Validation, search/filter, create, read, update and delete for `events`.
 * Every query goes through php/db.php, i.e. MySQLi prepared statements with
 * bound parameters; only fixed, whitelisted SQL fragments are concatenated.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/uploads.php';

const EVENT_TYPES    = ['Technical', 'Cultural', 'Sports', 'Workshop', 'Seminar', 'Placement'];
const EVENT_STATUSES = ['scheduled', 'cancelled', 'completed'];
const EVENT_PER_PAGE = [10, 25, 50];
const EVENT_MAX_SEATS = 5000;

const EVENT_SORTS = [
    'upcoming'   => ['Upcoming first', '(e.event_date < CURRENT_DATE), e.event_date ASC, e.start_time ASC'],
    'date_desc'  => ['Latest date',    'e.event_date DESC, e.start_time DESC'],
    'title_asc'  => ['Title (A-Z)',    'e.title ASC'],
    'newest'     => ['Recently added', 'e.created_at DESC, e.event_id DESC'],
    'most_seats' => ['Most seats',     'e.seats DESC'],
];

/* ==========================================================================
   READ
   ========================================================================== */

/** One event with its registration count, or null. */
function event_find(int $id): ?array
{
    return db_one(
        "SELECT e.*,
                (SELECT COUNT(*) FROM event_registrations r
                  WHERE r.event_id = e.event_id AND r.status IN ('registered', 'attended')) AS registered,
                f.full_name AS created_by_name
           FROM events e
           LEFT JOIN faculty f ON f.faculty_id = e.created_by
          WHERE e.event_id = ?",
        [$id]
    );
}

/** Read and whitelist the list page's query string. */
function event_filters(array $query): array
{
    $text = fn(string $key): string => is_string($query[$key] ?? null) ? clean_text($query[$key]) : '';
    $perPage = (int) $text('per_page');

    return [
        'q'        => mb_substr($text('q'), 0, 60),
        'type'     => in_array($text('type'), EVENT_TYPES, true) ? $text('type') : '',
        'status'   => in_array($text('status'), EVENT_STATUSES, true) ? $text('status') : '',
        'when'     => in_array($text('when'), ['upcoming', 'past'], true) ? $text('when') : '',
        'sort'     => array_key_exists($text('sort'), EVENT_SORTS) ? $text('sort') : 'upcoming',
        'per_page' => in_array($perPage, EVENT_PER_PAGE, true) ? $perPage : EVENT_PER_PAGE[0],
        'page'     => max(1, (int) $text('page')),
    ];
}

/** Search + filter + sort + paginate. */
function event_search(array $f): array
{
    $where  = [];
    $params = [];

    if ($f['q'] !== '') {
        $like = '%' . like_escape($f['q']) . '%';
        $where[] = '(e.title LIKE ? OR e.venue LIKE ? OR e.organizer LIKE ? OR e.description LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    if ($f['type'] !== '') {
        $where[]  = 'e.event_type = ?';
        $params[] = $f['type'];
    }
    if ($f['status'] !== '') {
        $where[]  = 'e.status = ?';
        $params[] = $f['status'];
    }
    if ($f['when'] === 'upcoming') {
        $where[] = 'e.event_date >= CURRENT_DATE';
    } elseif ($f['when'] === 'past') {
        $where[] = 'e.event_date < CURRENT_DATE';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = (int) db_value("SELECT COUNT(*) FROM events e {$whereSql}", $params);
    $pages = max(1, (int) ceil($total / $f['per_page']));
    $page  = min($f['page'], $pages);

    $rows = db_all(
        "SELECT e.event_id, e.title, e.event_type, e.event_date, e.start_time, e.venue, e.organizer,
                e.seats, e.status, e.is_featured, e.image_path,
                (SELECT COUNT(*) FROM event_registrations r
                  WHERE r.event_id = e.event_id AND r.status IN ('registered', 'attended')) AS registered
           FROM events e
         {$whereSql}
          ORDER BY " . EVENT_SORTS[$f['sort']][1] . '
          LIMIT ? OFFSET ?',
        [...$params, $f['per_page'], ($page - 1) * $f['per_page']]
    );

    return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
}

/** Students registered for an event. */
function event_registrations(int $id): array
{
    return db_all(
        'SELECT r.registration_id, r.team_name, r.team_size, r.status, r.registered_at,
                st.student_id, st.enrollment_no, st.full_name, st.email
           FROM event_registrations r
           JOIN students st ON st.student_id = r.student_id
          WHERE r.event_id = ?
          ORDER BY r.registered_at',
        [$id]
    );
}

/* ==========================================================================
   VALIDATION
   ========================================================================== */

/** Sanitize the add/edit form. */
function event_input(array $post): array
{
    $text = fn(string $key, bool $multi = false): string => clean_text($post[$key] ?? '', $multi);

    return [
        'title'                 => $text('title'),
        'event_type'            => clean_choice($post['event_type'] ?? '', EVENT_TYPES),
        'description'           => $text('description', true),
        'event_date'            => $text('event_date'),
        'start_time'            => $text('start_time'),
        'end_time'              => $text('end_time'),
        'venue'                 => $text('venue'),
        'organizer'             => $text('organizer'),
        'seats'                 => $text('seats'),
        'is_team_event'         => !empty($post['is_team_event']),
        'max_team_size'         => $text('max_team_size'),
        'registration_deadline' => $text('registration_deadline'),
        'is_featured'           => !empty($post['is_featured']),
        'status'                => clean_choice($post['status'] ?? '', EVENT_STATUSES),
        'remove_poster'         => !empty($post['remove_poster']),
    ];
}

/** A real calendar date "YYYY-MM-DD"? */
function valid_date(string $value): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $value);
    return $d && $d->format('Y-m-d') === $value;
}

/** A time "HH:MM" or "HH:MM:SS"? Returns "HH:MM:SS" or null. */
function normalize_time(string $value): ?string
{
    return preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $value) ? substr($value . ':00', 0, 8) : null;
}

/**
 * Validate the form. $existing is the current row when editing.
 * Returns field => message, and normalizes $in (times, numbers) by reference.
 */
function event_validate(array &$in, ?array $existing): array
{
    $errors = [];
    $len = fn(string $v): int => text_length($v);

    if ($in['title'] === '') {
        $errors['title'] = 'Event title is required.';
    } elseif ($len($in['title']) < 5 || $len($in['title']) > 150) {
        $errors['title'] = 'Title must be 5-150 characters.';
    }

    if ($in['event_type'] === '') {
        $errors['event_type'] = 'Please choose an event type.';
    }

    if ($in['description'] === '') {
        $errors['description'] = 'Please describe the event.';
    } elseif ($len($in['description']) < 20 || $len($in['description']) > 2000) {
        $errors['description'] = 'Description must be 20-2000 characters.';
    }

    // Date: a real date; new events can't be in the past
    if (!valid_date($in['event_date'])) {
        $errors['event_date'] = 'Please choose a valid date.';
    } elseif (!$existing && $in['event_date'] < date('Y-m-d')) {
        $errors['event_date'] = 'A new event cannot be in the past.';
    }

    $start = normalize_time($in['start_time']);
    if (!$start) {
        $errors['start_time'] = 'Please choose a valid start time.';
    } else {
        $in['start_time'] = $start;
    }

    if ($in['end_time'] === '') {
        $in['end_time'] = null;
    } elseif (!($end = normalize_time($in['end_time']))) {
        $errors['end_time'] = 'Please choose a valid end time.';
    } elseif ($start && $end <= $start) {
        $errors['end_time'] = 'End time must be after the start time.';
    } else {
        $in['end_time'] = $end;
    }

    foreach (['venue' => 'Venue', 'organizer' => 'Organizer'] as $field => $label) {
        if ($in[$field] === '') {
            $errors[$field] = "{$label} is required.";
        } elseif ($len($in[$field]) > 100) {
            $errors[$field] = "{$label} must be 100 characters or fewer.";
        }
    }

    // Seats: whole number, and never fewer than the students already registered
    $registered = $existing ? (int) $existing['registered'] : 0;
    if (!ctype_digit($in['seats']) || (int) $in['seats'] < 1 || (int) $in['seats'] > EVENT_MAX_SEATS) {
        $errors['seats'] = 'Seats must be a whole number from 1 to ' . EVENT_MAX_SEATS . '.';
    } elseif ((int) $in['seats'] < $registered) {
        $errors['seats'] = "{$registered} students are already registered, so seats cannot be fewer than that.";
    } else {
        $in['seats'] = (int) $in['seats'];
    }

    if ($in['is_team_event']) {
        if (!ctype_digit($in['max_team_size']) || (int) $in['max_team_size'] < 2 || (int) $in['max_team_size'] > 20) {
            $errors['max_team_size'] = 'Team events need a maximum team size from 2 to 20.';
        } else {
            $in['max_team_size'] = (int) $in['max_team_size'];
        }
    } else {
        $in['max_team_size'] = null;
    }

    // Registration deadline: optional, and not after the event starts
    if ($in['registration_deadline'] === '') {
        $in['registration_deadline'] = null;
    } else {
        $deadline = DateTime::createFromFormat('!Y-m-d\TH:i', $in['registration_deadline'])
            ?: DateTime::createFromFormat('!Y-m-d H:i:s', $in['registration_deadline']);
        if (!$deadline) {
            $errors['registration_deadline'] = 'Please choose a valid date and time.';
        } elseif (!isset($errors['event_date']) && $start
                  && $deadline->format('Y-m-d H:i:s') > "{$in['event_date']} {$start}") {
            $errors['registration_deadline'] = 'Registration must close before the event starts.';
        } else {
            $in['registration_deadline'] = $deadline->format('Y-m-d H:i:s');
        }
    }

    if ($in['status'] === '') {
        $errors['status'] = 'Please choose a valid status.';
    }

    return $errors;
}

/* ==========================================================================
   CREATE / UPDATE / DELETE
   ========================================================================== */

/** Values in the column order used by INSERT and UPDATE. */
function event_values(array $in): array
{
    return [
        $in['title'], $in['event_type'], $in['description'], $in['event_date'], $in['start_time'],
        $in['end_time'], $in['venue'], $in['organizer'], $in['seats'], $in['is_team_event'],
        $in['max_team_size'], $in['registration_deadline'], $in['is_featured'], $in['status'],
    ];
}

function event_create(array $in, ?string $posterPath, int $byFacultyId): int
{
    return db_insert(
        'INSERT INTO events
            (title, event_type, description, event_date, start_time, end_time, venue, organizer,
             seats, is_team_event, max_team_size, registration_deadline, is_featured, status,
             image_path, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [...event_values($in), $posterPath, $byFacultyId]
    );
}

function event_update(int $id, array $in, ?string $posterPath): void
{
    db_execute(
        'UPDATE events
            SET title = ?, event_type = ?, description = ?, event_date = ?, start_time = ?, end_time = ?,
                venue = ?, organizer = ?, seats = ?, is_team_event = ?, max_team_size = ?,
                registration_deadline = ?, is_featured = ?, status = ?, image_path = ?
          WHERE event_id = ?',
        [...event_values($in), $posterPath, $id]
    );
}

/** Delete the event (its registrations go too) and its uploaded poster file. */
function event_delete(array $event): bool
{
    $deleted = db_execute('DELETE FROM events WHERE event_id = ?', [(int) $event['event_id']]) === 1;
    if ($deleted) {
        delete_poster_file($event['image_path']);
    }
    return $deleted;
}

/** Which fields differ between the stored row and the new values (for messages / audit log). */
function event_changes(array $existing, array $in, bool $posterChanged): array
{
    $changed = [];
    foreach (['title', 'event_type', 'description', 'event_date', 'start_time', 'end_time', 'venue',
              'organizer', 'seats', 'is_team_event', 'max_team_size', 'registration_deadline',
              'is_featured', 'status'] as $field) {
        if ((string) $existing[$field] !== (string) (is_bool($in[$field]) ? (int) $in[$field] : $in[$field])) {
            $changed[] = str_replace('_', ' ', $field);
        }
    }
    if ($posterChanged) {
        $changed[] = 'poster';
    }
    return $changed;
}
