<?php
/**
 * StudentHub - Events JSON API (admins only)
 *
 *   GET    events-api.php               list (q, type, status, when, sort, per_page, page)
 *   GET    events-api.php?id=5          one event
 *   POST   events-api.php               add     (JSON body)
 *   PUT    events-api.php?id=5          update  (JSON body; the poster is left as it is)
 *   DELETE events-api.php?id=5          delete
 *
 * Bodies are JSON, answers are always JSON: { success, message, errors, ... }.
 * Every write must send the session's CSRF token in the X-CSRF-Token header.
 * Reuses the validation and prepared-statement queries of php/events.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../events.php';

header('X-Content-Type-Options: nosniff');

function api_fail(int $status, string $message, array $errors = []): never
{
    send_json($status, ['success' => false, 'message' => $message, 'errors' => (object) $errors]);
}

/** JSON request body as an array of plain strings / booleans. */
function api_body(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if ($raw === false || strlen($raw) > 65536) {
        api_fail(413, 'The request body is too large.');
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || array_is_list($data)) {
        api_fail(400, 'Send the event as a JSON object.');
    }
    // Numbers arrive as numbers in JSON; the shared validators expect form-style strings
    return array_map(fn($v) => is_int($v) || is_float($v) ? (string) $v : $v, $data);
}

/** Event row shaped for the browser (times trimmed, flags as booleans). */
function api_event(array $e): array
{
    return [
        'event_id'              => (int) $e['event_id'],
        'title'                 => $e['title'],
        'event_type'            => $e['event_type'],
        'description'           => $e['description'] ?? null,
        'event_date'            => $e['event_date'],
        'start_time'            => substr($e['start_time'], 0, 5),
        'end_time'              => !empty($e['end_time']) ? substr($e['end_time'], 0, 5) : '',
        'venue'                 => $e['venue'],
        'organizer'             => $e['organizer'],
        'seats'                 => (int) $e['seats'],
        'registered'            => (int) $e['registered'],
        'is_team_event'         => (bool) ($e['is_team_event'] ?? false),
        'max_team_size'         => isset($e['max_team_size']) ? (string) $e['max_team_size'] : '',
        'registration_deadline' => !empty($e['registration_deadline']) ? date('Y-m-d\TH:i', strtotime($e['registration_deadline'])) : '',
        'is_featured'           => (bool) $e['is_featured'],
        'status'                => $e['status'],
        'image_path'            => $e['image_path'],
    ];
}

try {
    $user = current_user();
    if (!$user) {
        api_fail(401, session_end_reason() === 'expired'
            ? 'Your session expired due to inactivity. Please log in again.'
            : 'Please log in to continue.');
    }
    if ($user['role'] !== 'admin') {
        api_fail(403, "You don't have permission to manage events.");
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $id     = (int) ($_GET['id'] ?? 0);

    if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
        header('Allow: GET, POST, PUT, DELETE');
        api_fail(405, 'Method not allowed.');
    }

    if ($method !== 'GET') {
        start_session();
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($_SESSION['csrf_token']) || !is_string($sent) || !hash_equals($_SESSION['csrf_token'], $sent)) {
            api_fail(419, 'Your page expired. Please reload it and try again.');
        }
    }

    switch ($method) {
        case 'GET':
            if ($id) {
                $event = event_find($id) ?? api_fail(404, 'That event no longer exists.');
                send_json(200, ['success' => true, 'event' => api_event($event)]);
            }
            $filters = event_filters($_GET);
            $result  = event_search($filters);
            send_json(200, [
                'success'  => true,
                'events'   => array_map('api_event', $result['rows']),
                'total'    => $result['total'],
                'pages'    => $result['pages'],
                'page'     => $result['page'],
                'per_page' => $filters['per_page'],
            ]);

        case 'POST':
            $in     = event_input(api_body());
            $errors = event_validate($in, null);
            if ($errors) {
                api_fail(422, 'Please fix the highlighted fields.', $errors);
            }
            $newId = event_create($in, null, $user['id']);
            audit_log('admin', $user['id'], 'event.create', 'events', $newId, "Created \"{$in['title']}\"");
            send_json(201, [
                'success' => true,
                'message' => "Event \"{$in['title']}\" was created.",
                'event'   => api_event(event_find($newId)),
            ]);

        case 'PUT':
            $existing = $id ? event_find($id) : null;
            $existing ?? api_fail(404, 'That event no longer exists.');
            $in     = event_input(api_body());
            $errors = event_validate($in, $existing);
            if ($errors) {
                api_fail(422, 'Please fix the highlighted fields.', $errors);
            }
            $changed = event_changes($existing, $in, false);
            event_update($id, $in, $existing['image_path']);
            audit_log('admin', $user['id'], 'event.update', 'events', $id,
                $changed ? 'Changed: ' . implode(', ', $changed) : 'No changes');
            send_json(200, [
                'success' => true,
                'message' => $changed
                    ? "Saved changes to \"{$in['title']}\": " . implode(', ', $changed) . '.'
                    : "No changes were made to \"{$in['title']}\".",
                'event'   => api_event(event_find($id)),
            ]);

        case 'DELETE':
            $event = $id ? event_find($id) : null;
            $event ?? api_fail(404, 'That event no longer exists.');
            if (!event_delete($event)) {
                api_fail(500, 'The event could not be deleted. Please try again.');
            }
            audit_log('admin', $user['id'], 'event.delete', 'events', $id,
                "Deleted \"{$event['title']}\" ({$event['registered']} registrations)");
            send_json(200, ['success' => true, 'message' => "\"{$event['title']}\" was deleted.", 'event_id' => $id]);
    }
} catch (mysqli_sql_exception $e) {
    error_log('[StudentHub events api] ' . $e->getMessage());
    api_fail(503, 'The database is unavailable right now. Please make sure MySQL is running and try again.');
}
