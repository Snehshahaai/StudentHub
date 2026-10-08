<?php
/**
 * StudentHub - Student sessions & "Remember login state"
 *
 * Login keeps the student id in a PHP session. With "remember me" a
 * long-lived cookie holds "selector:validator"; only a SHA-256 hash of the
 * validator is stored in remember_tokens, so a leaked database can't be used
 * to log in.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib.php';

const SESSION_NAME     = 'studenthub_sid';
const REMEMBER_COOKIE  = 'studenthub_remember';
const REMEMBER_DAYS    = 30;
const MAX_FAILED_LOGINS = 5;       // per email ...
const LOCKOUT_MINUTES   = 15;      // ... within this window

function is_https(): bool
{
    return ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
}

function cookie_options(int $expires): array
{
    return [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,        // JavaScript can't read the cookie
        'samesite' => 'Lax',
    ];
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(SESSION_NAME);
    // Session cookies take "lifetime" (0 = until the browser closes) instead of "expires"
    $options = cookie_options(0);
    unset($options['expires']);
    session_set_cookie_params(['lifetime' => 0] + $options);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

/**
 * The logged-in student's profile row (from v_student_profile), or null.
 * Falls back to the remember-me cookie when the session has expired.
 */
function current_student(): ?array
{
    start_session();

    $id = $_SESSION['student_id'] ?? null;
    if (!$id) {
        $id = login_from_remember_cookie();
    }
    if (!$id) {
        return null;
    }

    $student = db_one("SELECT * FROM v_student_profile WHERE student_id = ? AND status = 'active'", [(int) $id]);
    if (!$student) {
        // Account was disabled or deleted after logging in
        logout_student();
        return null;
    }
    return $student;
}

function login_student(int $studentId, bool $remember): void
{
    start_session();
    session_regenerate_id(true);       // new session id after login (prevents session fixation)
    $_SESSION['student_id'] = $studentId;
    $_SESSION['logged_in_at'] = time();

    db_execute('UPDATE students SET last_login_at = NOW() WHERE student_id = ?', [$studentId]);

    if ($remember) {
        issue_remember_token($studentId);
    }
}

function logout_student(): void
{
    start_session();

    [$selector] = parse_remember_cookie();
    if ($selector) {
        db_execute('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
    }
    setcookie(REMEMBER_COOKIE, '', cookie_options(time() - 3600));

    $_SESSION = [];
    setcookie(session_name(), '', cookie_options(time() - 3600));
    session_destroy();
}

/* ---------- Remember me ---------- */

function issue_remember_token(int $studentId): void
{
    $selector  = bin2hex(random_bytes(12));      // 24 chars, used to look the row up
    $validator = bin2hex(random_bytes(32));      // secret, only its hash is stored
    $expires   = time() + REMEMBER_DAYS * 86400;

    db_insert(
        'INSERT INTO remember_tokens (student_id, selector, validator_hash, user_agent, expires_at)
         VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))',
        [$studentId, $selector, hash('sha256', $validator),
         substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), $expires]
    );
    setcookie(REMEMBER_COOKIE, "{$selector}:{$validator}", cookie_options($expires));
}

function parse_remember_cookie(): array
{
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if (!is_string($cookie) || !preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $cookie, $m)) {
        return [null, null];
    }
    return [$m[1], $m[2]];
}

function login_from_remember_cookie(): ?int
{
    [$selector, $validator] = parse_remember_cookie();
    if (!$selector) {
        return null;
    }

    $token = db_one(
        'SELECT token_id, student_id, validator_hash FROM remember_tokens
          WHERE selector = ? AND expires_at > NOW()',
        [$selector]
    );
    if (!$token || !hash_equals($token['validator_hash'], hash('sha256', $validator))) {
        setcookie(REMEMBER_COOKIE, '', cookie_options(time() - 3600));
        return null;
    }

    // Rotate: each remember token works once, then is replaced
    db_execute('DELETE FROM remember_tokens WHERE token_id = ?', [(int) $token['token_id']]);
    login_student((int) $token['student_id'], true);
    return (int) $token['student_id'];
}

/* ---------- Brute-force protection ---------- */

function too_many_failed_logins(string $email): bool
{
    $failures = db_value(
        "SELECT COUNT(*) FROM audit_logs
          WHERE action = 'login.failed' AND details = ?
            AND created_at > NOW() - INTERVAL ? MINUTE",
        [$email, LOCKOUT_MINUTES]
    );
    return (int) $failures >= MAX_FAILED_LOGINS;
}
