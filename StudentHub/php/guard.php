<?php
/**
 * StudentHub - Page guard for protected pages
 * Put at the very top of a page in pages/:
 *
 *   <?php require __DIR__ . '/../php/guard.php'; $user = require_role(['student']); ?>
 *
 * Not logged in (or session timed out) -> login page with a message.
 * Logged in with the wrong role       -> that role's own dashboard.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function require_role(array $roles): array
{
    // Protected pages must never be cached, or "Back" after logout would show them
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    try {
        $user = current_user();
    } catch (mysqli_sql_exception $e) {
        error_log('[StudentHub guard] ' . $e->getMessage());
        http_response_code(503);
        exit('The database is unavailable right now. Please make sure MySQL is running and try again.');
    }

    if (!$user) {
        redirect_with_message('login.html', 'error', session_end_reason() === 'expired'
            ? 'Your session expired due to inactivity. Please log in again.'
            : 'Please log in to view this page.');
    }

    if (!in_array($user['role'], $roles, true)) {
        redirect_with_message(DASHBOARDS[$user['role']], 'error', "You don't have permission to view that page.");
    }

    return $user;
}

// Location is relative to pages/, where every protected page lives
function redirect_with_message(string $page, string $status, string $message): never
{
    header('Location: ' . $page . '?' . http_build_query(['status' => $status, 'message' => $message]), true, 303);
    exit;
}

// Escape text for HTML output in the protected pages
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
