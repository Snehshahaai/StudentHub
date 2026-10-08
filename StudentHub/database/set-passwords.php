<?php
/**
 * StudentHub - Set passwords for the demo accounts (command line only)
 *
 * The sample data ships with locked accounts: no password works until you
 * run this once after importing seed.sql. Passwords are printed here and are
 * never stored anywhere except as password_hash() hashes in the database.
 *
 *   php database/set-passwords.php                      random password for every demo account
 *   php database/set-passwords.php admin@university.edu another random password for one account
 *   php database/set-passwords.php --password='My@Pass123' sneh.shah@university.edu
 *
 * Changing a password also signs that account out of every "remember me" device.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../php/lib.php';

// The accounts created by seed.sql
const DEMO_ACCOUNTS = [
    'sneh.shah@university.edu', 'rohan.v@university.edu', 'priya.p@university.edu',
    'admin@university.edu', 'a.mehta@university.edu', 'r.joshi@university.edu', 'n.desai@university.edu',
];

/** 14 random characters that always pass the site's password rules. */
function random_password(): string
{
    $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789', '@#%+=?!'];
    $all = implode('', $sets);
    do {
        $password = '';
        for ($i = 0; $i < 14; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }
    } while (password_error($password) !== '');
    return $password;
}

/* ---------- Arguments ---------- */
$chosen = null;
$emails = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--password=')) {
        $chosen = substr($arg, strlen('--password='));
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: php database/set-passwords.php [--password=...] [email ...]\n");
        exit(0);
    } else {
        $emails[] = strtolower($arg);
    }
}
$emails = $emails ?: DEMO_ACCOUNTS;

if ($chosen !== null && ($message = password_error($chosen)) !== '') {
    fwrite(STDERR, "Password rejected: {$message}\n");
    exit(1);
}

/* ---------- Update ---------- */
$rows = [];
try {
    foreach ($emails as $email) {
        $password = $chosen ?? random_password();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($student = db_one('SELECT student_id FROM students WHERE email = ?', [$email])) {
            $id = (int) $student['student_id'];
            db_execute('UPDATE students SET password_hash = ? WHERE student_id = ?', [$hash, $id]);
            db_execute('DELETE FROM remember_tokens WHERE student_id = ?', [$id]);
            $rows[] = ['student', $email, $password];
        } elseif ($staff = db_one('SELECT faculty_id, role FROM faculty WHERE email = ?', [$email])) {
            $id = (int) $staff['faculty_id'];
            db_execute('UPDATE faculty SET password_hash = ? WHERE faculty_id = ?', [$hash, $id]);
            db_execute('DELETE FROM remember_tokens WHERE faculty_id = ?', [$id]);
            $rows[] = [$staff['role'], $email, $password];
        } else {
            $rows[] = ['-', $email, '(no such account, skipped)'];
        }
    }
} catch (mysqli_sql_exception $e) {
    fwrite(STDERR, 'Database error: ' . $e->getMessage() . "\nIs MySQL running and the database imported?\n");
    exit(1);
}

/* ---------- Report ---------- */
fwrite(STDOUT, "\nNew passwords (shown once, save them somewhere safe):\n\n");
foreach ($rows as [$role, $email, $password]) {
    fwrite(STDOUT, sprintf("  %-8s %-28s %s\n", $role, $email, $password));
}
fwrite(STDOUT, "\n");
