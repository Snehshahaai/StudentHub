<?php
/**
 * StudentHub - Logout
 * Ends the session, deletes the remember-me token and returns to the login page.
 * POST only, so a link on another site can't log people out.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';

const RETURN_TO = '../pages/login.html';

require_post(RETURN_TO);

try {
    $student = current_student();
    logout_student();
    if ($student) {
        audit_log('student', (int) $student['student_id'], 'logout', 'students', (int) $student['student_id']);
    }
} catch (mysqli_sql_exception $e) {
    respond_db_error($e, 'logout', RETURN_TO);
}

respond(true, 'You have been logged out.', [], RETURN_TO);
