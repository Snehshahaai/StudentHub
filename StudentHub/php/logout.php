<?php
/**
 * StudentHub - Logout
 * Destroys the session, deletes the remember-me token and cookie, then
 * returns to the login page. POST only, so a link on another site can't
 * log people out.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';

const RETURN_TO = '../pages/login.html';

require_post(RETURN_TO);

try {
    $user = current_user();
    logout_user();
    if ($user) {
        audit_log($user['role'], $user['id'], 'logout', $user['type'] === 'student' ? 'students' : 'faculty', $user['id']);
    }
} catch (mysqli_sql_exception $e) {
    respond_db_error($e, 'logout', RETURN_TO);
}

respond(true, 'You have been logged out.', [], RETURN_TO);
