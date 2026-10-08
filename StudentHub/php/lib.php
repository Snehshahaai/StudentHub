<?php
/**
 * StudentHub - Shared server-side helpers
 * Request checks, JSON / redirect responses, sanitizing and database error
 * handling used by every form handler in php/.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

date_default_timezone_set('Asia/Kolkata');

/* ==========================================================================
   REQUEST / RESPONSE
   ========================================================================== */

// The JS front end asks for JSON; a plain HTML form post (no JS) does not
function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

/**
 * Send the result back to the browser.
 * JSON clients get { success, message, errors }; plain form posts are
 * redirected back to the page with the message in the query string.
 */
function respond(bool $success, string $message, array $errors = [], string $returnTo = '../index.html', int $status = 0, array $extra = []): never
{
    if (wants_json()) {
        send_json($status ?: ($success ? 200 : 422), [
            'success' => $success,
            'message' => $message,
            'errors'  => (object) $errors,
        ] + $extra);
    }

    // No-JS fallback: show the first field error so the user knows what to fix
    if (!$success && $errors) {
        $message .= ' ' . reset($errors);
    }
    $query = http_build_query(['status' => $success ? 'success' : 'error', 'message' => $message]);
    header('Location: ' . $returnTo . '?' . $query, true, 303);
    exit;
}

function send_json(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_post(string $returnTo): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        respond(false, 'Invalid request method. Please submit the form.', [], $returnTo, 405);
    }
}

/**
 * Log a database failure and answer with a friendly message.
 * A refused connection (2002) usually means MySQL isn't started in XAMPP.
 */
function respond_db_error(Throwable $e, string $context, string $returnTo): never
{
    error_log("[StudentHub {$context}] " . $e->getMessage());
    $message = in_array($e->getCode(), [2002, 2006, 1045, 1049], true)
        ? 'The database is unavailable right now. Please make sure MySQL is running and try again.'
        : 'Server error: your request could not be completed. Please try again later.';
    respond(false, $message, [], $returnTo, 500);
}

function client_ip(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
}

// Admin dashboard "Audit Logs"
function audit_log(string $actorType, ?int $actorId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void
{
    db_insert(
        'INSERT INTO audit_logs (actor_type, actor_id, action, entity_type, entity_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$actorType, $actorId, $action, $entityType, $entityId, $details, client_ip()]
    );
}

// Bots fill every field, people never see this one
function is_spam(): bool
{
    return trim((string) ($_POST['website'] ?? '')) !== '';
}

/* ==========================================================================
   SANITIZING
   ========================================================================== */

/**
 * Clean a single-line or multi-line text value:
 * trims, removes HTML tags and invisible control characters, and
 * collapses repeated spaces. Output is still escaped wherever it is shown.
 */
function clean_text(mixed $value, bool $multiline = false): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = strip_tags($value);
    // Remove control characters (keep tab/newline for multi-line text)
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

    if ($multiline) {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[ \t]+/', ' ', $value) ?? '';
        $value = preg_replace('/\n{3,}/', "\n\n", $value) ?? '';
    } else {
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
    }
    return trim($value);
}

function clean_email(mixed $value): string
{
    $email = filter_var(clean_text($value), FILTER_SANITIZE_EMAIL);
    return strtolower((string) $email);
}

// Only accept a value from a fixed list of allowed options
function clean_choice(mixed $value, array $allowed): string
{
    $value = clean_text($value);
    return in_array($value, $allowed, true) ? $value : '';
}

function text_length(string $value): int
{
    return mb_strlen($value, 'UTF-8');
}

/* ==========================================================================
   SHARED FIELD RULES (same rules as js/register.js)
   ========================================================================== */

// 4-20 chars, starts with a letter, letters/digits/underscore, single dots between parts
const USERNAME_PATTERN = '/^(?=.{4,20}$)[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)*$/';
const RESERVED_USERNAMES = ['admin', 'administrator', 'root', 'system', 'support', 'studenthub', 'faculty', 'null', 'undefined'];

// Usernames are case-insensitive: always stored and compared in lowercase
function clean_username(mixed $value): string
{
    return strtolower(clean_text($value));
}

/** Error message for an invalid username, or '' when the format is fine. */
function username_error(string $username): string
{
    if ($username === '') {
        return 'Username is required.';
    }
    if (!preg_match(USERNAME_PATTERN, $username)) {
        return 'Username must be 4-20 characters: start with a letter, then letters, numbers, _ or single dots.';
    }
    if (in_array($username, RESERVED_USERNAMES, true)) {
        return 'This username is reserved. Please choose another one.';
    }
    return '';
}

/** Error message for an invalid full name, or '' when the format is fine. */
function name_error(string $name): string
{
    if ($name === '') {
        return 'Full name is required.';
    }
    if (!preg_match('/^(?=.{3,50}$)[A-Za-z]+(?: [A-Za-z]+)*$/', $name)) {
        return 'Name must be 3-50 letters and spaces only.';
    }
    return '';
}

/** Error message for an invalid Indian mobile number, or '' when the format is fine. */
function mobile_error(string $mobile): string
{
    if ($mobile === '') {
        return 'Mobile number is required.';
    }
    if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        return 'Enter a valid 10-digit mobile number starting with 6-9.';
    }
    return '';
}

/** Error message for a weak password, or '' when it is strong enough. */
function password_error(string $password): string
{
    if ($password === '') {
        return 'Password is required.';
    }
    // bcrypt only uses the first 72 bytes, so longer passwords are refused
    if (strlen($password) > 72 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9])\S{8,}$/', $password)) {
        return 'Password needs 8-72 characters, an uppercase, a lowercase, a number and a special character (no spaces).';
    }
    return '';
}

/** Escape % and _ so user text is matched literally inside LIKE '%...%'. */
function like_escape(string $value): string
{
    return addcslashes($value, '%_\\');
}

/** Error message for an invalid email, or '' when the format is fine. */
function email_error(string $email): string
{
    if ($email === '') {
        return 'Email address is required.';
    }
    if (text_length($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid email address (e.g. student@university.edu).';
    }
    return '';
}

/* ==========================================================================
   IDS
   ========================================================================== */

function new_id(string $prefix): string
{
    return $prefix . '-' . date('Ymd') . '-' . bin2hex(random_bytes(3));
}
