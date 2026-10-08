<?php
/**
 * StudentHub - Live "is it taken?" check for the registration form
 *   GET php/check-availability.php?field=username&value=sneh.shah
 *   -> { "available": false, "taken": true, "message": "This username is already taken..." }
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$field = $_GET['field'] ?? '';
$raw   = $_GET['value'] ?? '';

if (!in_array($field, ['username', 'email'], true) || !is_string($raw)) {
    send_json(400, ['available' => false, 'message' => 'Invalid request.']);
}

// Same cleaning and format rules as php/register.php
$value = $field === 'username' ? clean_username($raw) : clean_email($raw);
$error = $field === 'username' ? username_error($value) : email_error($value);
if ($error) {
    send_json(200, ['available' => false, 'message' => $error]);
}

try {
    // $field is one of two fixed column names checked above, never user text
    $exists = (int) db_value("SELECT COUNT(*) FROM students WHERE {$field} = ?", [$value]) > 0;
} catch (mysqli_sql_exception $e) {
    error_log('[StudentHub availability] ' . $e->getMessage());
    send_json(500, ['available' => null, 'message' => 'Could not check right now.']);
}

send_json(200, $exists
    ? ['available' => false, 'taken' => true, 'message' => $field === 'username'
        ? 'This username is already taken. Please choose another one.'
        : 'This email is already registered. Try logging in instead.']
    : ['available' => true, 'message' => ucfirst($field) . ' is available.']);
