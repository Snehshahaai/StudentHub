<?php
/**
 * StudentHub - Login Handler (students, faculty and admins)
 * Finds the account by email or username, checks the password with
 * password_verify(), starts a fresh session (new session id) and sends the
 * user to the dashboard for their role. Optional "remember me" cookie.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';

const RETURN_TO = '../pages/login.html';

// Hash of random bytes (matches no password). Compared against when the account
// doesn't exist, so both cases take the same time
const DUMMY_HASH = '$2y$12$rOxKc6p1MtMNnfJ8TFNq3.FrPpPklU.LfVmwHDZ2nP2f0U8hM3c82';

require_post(RETURN_TO);

/* ---------- Sanitize & validate ---------- */
// One box accepts either: anything with "@" is treated as an email
$rawLogin = clean_text($_POST['login'] ?? '');
$byEmail  = str_contains($rawLogin, '@');
$login    = $byEmail ? clean_email($rawLogin) : clean_username($rawLogin);
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$remember = !empty($_POST['remember']);

$errors = [];
// Usernames: the database's own rule (older accounts may predate the stricter sign-up rule)
$formatOk = $byEmail ? email_error($login) === '' : (bool) preg_match('/^[a-z][a-z0-9_.]{3,19}$/', $login);
if (!$formatOk) {
    $errors['login'] = 'Enter the email address or username you registered with.';
}
if ($password === '') {
    $errors['password'] = 'Please enter your password.';
}
if ($errors) {
    respond(false, 'Please fix the highlighted fields.', $errors, RETURN_TO);
}

/* ---------- Find the account ---------- */
// Students log in with email or username; faculty and admins with their email
function find_account(string $login, bool $byEmail): ?array
{
    $student = db_one(
        "SELECT student_id AS id, full_name, password_hash, status, 'student' AS role
           FROM students WHERE " . ($byEmail ? 'email' : 'username') . ' = ?',
        [$login]
    );
    if ($student) {
        return ['type' => 'student'] + $student;
    }
    if (!$byEmail) {
        return null;
    }
    $staff = db_one(
        'SELECT faculty_id AS id, full_name, password_hash, status, role FROM faculty WHERE email = ?',
        [$login]
    );
    return $staff ? ['type' => 'faculty'] + $staff : null;
}

/* ---------- Authenticate ---------- */
try {
    if (too_many_failed_logins($login)) {
        respond(false, 'Too many failed attempts. Please wait ' . LOCKOUT_MINUTES . ' minutes and try again.', [], RETURN_TO, 429);
    }

    $account = find_account($login, $byEmail);

    // password_verify() compares against the stored password_hash() value
    $valid = password_verify($password, $account['password_hash'] ?? DUMMY_HASH) && $account !== null;

    if (!$valid) {
        audit_log($account['role'] ?? 'system', isset($account['id']) ? (int) $account['id'] : null, 'login.failed', null, null, $login);
        // Same message for "no such account" and "wrong password", so accounts can't be guessed
        respond(false, 'Incorrect email/username or password.', ['password' => 'Incorrect email/username or password.'], RETURN_TO, 401);
    }

    if ($account['status'] !== 'active') {
        $message = $account['status'] === 'pending'
            ? 'Your account is waiting for admin approval. Please try again later.'
            : 'Your account has been disabled. Please contact the administration office.';
        respond(false, $message, [], RETURN_TO, 403);
    }

    $id = (int) $account['id'];

    // Upgrade old hashes automatically when PHP's default algorithm changes
    if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
        db_execute($account['type'] === 'student'
            ? 'UPDATE students SET password_hash = ? WHERE student_id = ?'
            : 'UPDATE faculty SET password_hash = ? WHERE faculty_id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    login_user($account['type'], $id, $remember);
    audit_log($account['role'], $id, 'login.success', $account['type'] === 'student' ? 'students' : 'faculty', $id, $login);
} catch (mysqli_sql_exception $e) {
    respond_db_error($e, 'login', RETURN_TO);
}

/* ---------- Role-based redirect ---------- */
$dashboard = DASHBOARDS[$account['role']];
// Students by first name; staff by full name, since theirs often start with "Dr." or "Prof."
$greeting = $account['type'] === 'student' ? explode(' ', $account['full_name'])[0] : $account['full_name'];

respond(true, "Welcome back, {$greeting}! Logging you in...", [], '../pages/' . $dashboard, 0, [
    'role'     => $account['role'],
    'redirect' => $dashboard,
]);
