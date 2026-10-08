<?php
/**
 * StudentHub - Student Login Handler
 * Checks the email + password against the `students` table, starts a
 * session and optionally sets a "remember me" cookie.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';

const RETURN_TO = '../pages/login.html';
const DASHBOARD = '../pages/student-dashboard.html';

// Compared against when the email doesn't exist, so both cases take the same time
const DUMMY_HASH = '$2y$12$39uymJhLlgqJQpSrrXkhGeTjB3mV51dLSERVH1a7DkooG.rBNTx1y';

require_post(RETURN_TO);

/* ---------- Sanitize & validate ---------- */
$email    = clean_email($_POST['email'] ?? '');
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$remember = !empty($_POST['remember']);

$errors = [];
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter the email address you registered with.';
}
if ($password === '') {
    $errors['password'] = 'Please enter your password.';
}
if ($errors) {
    respond(false, 'Please fix the highlighted fields.', $errors, RETURN_TO);
}

/* ---------- Authenticate ---------- */
try {
    if (too_many_failed_logins($email)) {
        respond(false, 'Too many failed attempts. Please wait ' . LOCKOUT_MINUTES . ' minutes and try again.', [], RETURN_TO, 429);
    }

    $student = db_one(
        'SELECT student_id, full_name, password_hash, status FROM students WHERE email = ?',
        [$email]
    );

    $valid = password_verify($password, $student['password_hash'] ?? DUMMY_HASH) && $student !== null;

    if (!$valid) {
        audit_log('student', $student ? (int) $student['student_id'] : null, 'login.failed', 'students', null, $email);
        // Same message for "no such email" and "wrong password", so accounts can't be guessed
        respond(false, 'Incorrect email or password.', ['password' => 'Incorrect email or password.'], RETURN_TO, 401);
    }

    if ($student['status'] !== 'active') {
        $message = $student['status'] === 'pending'
            ? 'Your account is waiting for admin approval. Please try again later.'
            : 'Your account has been disabled. Please contact the administration office.';
        respond(false, $message, [], RETURN_TO, 403);
    }

    $id = (int) $student['student_id'];

    // Upgrade old hashes automatically when PHP's default algorithm changes
    if (password_needs_rehash($student['password_hash'], PASSWORD_DEFAULT)) {
        db_execute('UPDATE students SET password_hash = ? WHERE student_id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    login_student($id, $remember);
    audit_log('student', $id, 'login.success', 'students', $id, $email);
} catch (mysqli_sql_exception $e) {
    respond_db_error($e, 'login', RETURN_TO);
}

$firstName = explode(' ', $student['full_name'])[0];
respond(true, "Welcome back, {$firstName}! Logging you in...", [], DASHBOARD);
