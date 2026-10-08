<?php
/**
 * StudentHub - Sessions, roles, timeouts & "Remember login state"
 *
 * Two kinds of account can log in:
 *   - students (role "student")           -> pages/student-dashboard.php
 *   - faculty  (role "faculty" or "admin") -> pages/admin-dashboard.php
 *
 * The session stores only who is logged in and when. Sessions end after
 * SESSION_IDLE_TIMEOUT seconds without a request, and always after
 * SESSION_ABSOLUTE_TIMEOUT. With "remember me" a cookie holds
 * "selector:validator"; only a SHA-256 hash of the validator is stored in
 * remember_tokens, so a leaked database can't be used to log in.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib.php';

const SESSION_NAME        = 'studenthub_sid';
const REMEMBER_COOKIE     = 'studenthub_remember';
const REMEMBER_DAYS       = 30;
const SESSION_REGENERATE_EVERY = 15 * 60;   // fresh session id every 15 minutes
const MAX_FAILED_LOGINS   = 5;              // per email/username ...
const LOCKOUT_MINUTES     = 15;             // ... within this window

// Timeouts in seconds. Override for testing, e.g.  SESSION_IDLE_TIMEOUT=60 php -S ...
define('SESSION_IDLE_TIMEOUT', (int) (getenv('SESSION_IDLE_TIMEOUT') ?: 30 * 60));       // 30 minutes
define('SESSION_ABSOLUTE_TIMEOUT', (int) (getenv('SESSION_ABSOLUTE_TIMEOUT') ?: 8 * 3600)); // 8 hours

// Where each role lands after logging in (relative to pages/)
const DASHBOARDS = [
    'student' => 'student-dashboard.php',
    'faculty' => 'admin-dashboard.php',
    'admin'   => 'admin-dashboard.php',
];

function is_https(): bool
{
    return ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
}

function cookie_options(int $expires): array
{
    return [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => is_https(),   // HTTPS-only cookies when the site runs on HTTPS
        'httponly' => true,         // JavaScript can't read the cookie
        'samesite' => 'Lax',        // not sent on cross-site POSTs
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
    ini_set('session.use_strict_mode', '1');     // reject session ids the server didn't create
    ini_set('session.use_only_cookies', '1');    // never accept ids from the URL
    ini_set('session.gc_maxlifetime', (string) SESSION_ABSOLUTE_TIMEOUT);
    session_start();
}

/* ==========================================================================
   CURRENT USER
   ========================================================================== */

// Why the last current_user() call found no session: null, "expired"
function session_end_reason(?string $set = null): ?string
{
    static $reason = null;
    if ($set !== null) {
        $reason = $set;
    }
    return $reason;
}

/**
 * The logged-in account, or null:
 *   ['type' => 'student'|'faculty', 'id' => int, 'role' => 'student'|'faculty'|'admin',
 *    'name' => string, 'record' => row from the database]
 * Enforces the idle / absolute timeouts and falls back to the remember-me cookie.
 */
function current_user(): ?array
{
    start_session();
    $now  = time();
    $auth = $_SESSION['auth'] ?? null;

    if ($auth) {
        $idle    = $now - $auth['last_activity'];
        $elapsed = $now - $auth['logged_in_at'];

        if ($idle > SESSION_IDLE_TIMEOUT || $elapsed > SESSION_ABSOLUTE_TIMEOUT) {
            end_session();                     // keeps the remember-me cookie
            session_end_reason('expired');
            $auth = null;
        } else {
            $_SESSION['auth']['last_activity'] = $now;
            if ($now - $auth['regenerated_at'] > SESSION_REGENERATE_EVERY) {
                session_regenerate_id(true);
                $_SESSION['auth']['regenerated_at'] = $now;
            }
        }
    }

    if (!$auth) {
        $auth = login_from_remember_cookie();
    }
    if (!$auth) {
        return null;
    }

    $record = load_account($auth['type'], $auth['id']);
    if (!$record) {
        // Account was disabled or deleted after logging in
        logout_user();
        return null;
    }

    return [
        'type'   => $auth['type'],
        'id'     => $auth['id'],
        'role'   => $auth['type'] === 'student' ? 'student' : $record['role'],
        'name'   => $record['full_name'],
        'record' => $record,
    ];
}

/** Active student (profile view) or active faculty row, without the password hash. */
function load_account(string $type, int $id): ?array
{
    if ($type === 'student') {
        return db_one("SELECT * FROM v_student_profile WHERE student_id = ? AND status = 'active'", [$id]);
    }
    return db_one(
        "SELECT f.faculty_id, f.employee_code, f.full_name, f.email, f.phone, f.designation, f.role,
                d.name AS department
           FROM faculty f LEFT JOIN departments d ON d.department_id = f.department_id
          WHERE f.faculty_id = ? AND f.status = 'active'",
        [$id]
    );
}

/** Seconds left before the current session times out (for the client-side warning). */
function session_seconds_left(): int
{
    $auth = $_SESSION['auth'] ?? null;
    if (!$auth) {
        return 0;
    }
    $now = time();
    return max(0, min(
        SESSION_IDLE_TIMEOUT - ($now - $auth['last_activity']),
        SESSION_ABSOLUTE_TIMEOUT - ($now - $auth['logged_in_at'])
    ));
}

/* ==========================================================================
   LOGIN / LOGOUT
   ========================================================================== */

function login_user(string $type, int $id, bool $remember): array
{
    start_session();
    session_regenerate_id(true);       // new session id after login (prevents session fixation)

    $now = time();
    $_SESSION = [];                    // drop anything from before login
    $_SESSION['auth'] = [
        'type'           => $type,
        'id'             => $id,
        'logged_in_at'   => $now,
        'last_activity'  => $now,
        'regenerated_at' => $now,
    ];

    db_execute($type === 'student'
        ? 'UPDATE students SET last_login_at = NOW() WHERE student_id = ?'
        : 'UPDATE faculty SET last_login_at = NOW() WHERE faculty_id = ?', [$id]);

    if ($remember) {
        issue_remember_token($type, $id);
    }
    return $_SESSION['auth'];
}

/** Clear the session but keep the remember-me cookie (used on timeout). */
function end_session(): void
{
    $_SESSION = [];
    setcookie(session_name(), '', cookie_options(time() - 3600));
    session_destroy();
}

/** Full logout: session and remember-me token. */
function logout_user(): void
{
    start_session();

    [$selector] = parse_remember_cookie();
    if ($selector) {
        db_execute('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
    }
    setcookie(REMEMBER_COOKIE, '', cookie_options(time() - 3600));

    if (session_status() === PHP_SESSION_ACTIVE) {
        end_session();
    }
}

/* ==========================================================================
   REMEMBER ME
   ========================================================================== */

function issue_remember_token(string $type, int $id): void
{
    $selector  = bin2hex(random_bytes(12));      // 24 chars, used to look the row up
    $validator = bin2hex(random_bytes(32));      // secret, only its hash is stored
    $expires   = time() + REMEMBER_DAYS * 86400;

    db_insert(
        'INSERT INTO remember_tokens (student_id, faculty_id, selector, validator_hash, user_agent, expires_at)
         VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?))',
        [
            $type === 'student' ? $id : null,
            $type === 'faculty' ? $id : null,
            $selector,
            hash('sha256', $validator),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            $expires,
        ]
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

function login_from_remember_cookie(): ?array
{
    [$selector, $validator] = parse_remember_cookie();
    if (!$selector) {
        return null;
    }

    $token = db_one(
        'SELECT token_id, student_id, faculty_id, validator_hash FROM remember_tokens
          WHERE selector = ? AND expires_at > NOW()',
        [$selector]
    );
    if (!$token || !hash_equals($token['validator_hash'], hash('sha256', $validator))) {
        setcookie(REMEMBER_COOKIE, '', cookie_options(time() - 3600));
        return null;
    }

    // Rotate: each remember token works once, then is replaced
    db_execute('DELETE FROM remember_tokens WHERE token_id = ?', [(int) $token['token_id']]);

    $type = $token['student_id'] !== null ? 'student' : 'faculty';
    $id   = (int) ($token['student_id'] ?? $token['faculty_id']);
    session_end_reason('');            // logged back in, so nothing "expired" for the user
    return login_user($type, $id, true);
}

/* ==========================================================================
   BRUTE-FORCE PROTECTION
   ========================================================================== */

function too_many_failed_logins(string $login): bool
{
    $failures = db_value(
        "SELECT COUNT(*) FROM audit_logs
          WHERE action = 'login.failed' AND details = ?
            AND created_at > NOW() - INTERVAL ? MINUTE",
        [$login, LOCKOUT_MINUTES]
    );
    return (int) $failures >= MAX_FAILED_LOGINS;
}
