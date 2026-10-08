<?php
/**
 * StudentHub - Database settings
 * Defaults match a fresh XAMPP install (user "root", no password).
 * Override any value with environment variables on a real server.
 */

declare(strict_types=1);

return [
    // 127.0.0.1 forces TCP, so the PHP CLI and XAMPP's Apache both reach XAMPP's MySQL
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => (int) (getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_NAME') ?: 'studenthub',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'charset'  => 'utf8mb4',
    'timezone' => '+05:30',          // India Standard Time, same as php/lib.php
];
