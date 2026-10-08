<?php
/**
 * StudentHub - Shared server-side helpers
 * Sanitizing, validation responses and file storage (JSON + CSV) used by
 * register.php and contact.php.
 */

declare(strict_types=1);

const STORAGE_DIR = __DIR__ . '/../storage';

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
function respond(bool $success, string $message, array $errors = [], string $returnTo = '../index.html'): never
{
    if (wants_json()) {
        http_response_code($success ? 200 : 422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'errors'  => (object) $errors,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // No-JS fallback: show the first field error so the user knows what to fix
    if (!$success && $errors) {
        $message .= ' ' . reset($errors);
    }
    $query = http_build_query(['status' => $success ? 'success' : 'error', 'message' => $message]);
    header('Location: ' . $returnTo . '?' . $query, true, 303);
    exit;
}

function require_post(string $returnTo): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        respond(false, 'Invalid request method. Please submit the form.', [], $returnTo);
    }
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
   STORAGE
   ========================================================================== */

function storage_path(string $file): string
{
    if (!is_dir(STORAGE_DIR) && !mkdir(STORAGE_DIR, 0750, true) && !is_dir(STORAGE_DIR)) {
        throw new RuntimeException('Storage folder could not be created.');
    }
    return STORAGE_DIR . '/' . $file;
}

/**
 * Read-modify-write a JSON array file under an exclusive lock, so two
 * submissions at the same moment can't overwrite each other.
 * $update receives the records by reference and may return an error string
 * to abort without saving.
 */
function update_json_store(string $file, callable $update): ?string
{
    $fp = fopen(storage_path($file), 'c+');
    if ($fp === false || !flock($fp, LOCK_EX)) {
        throw new RuntimeException('Storage file could not be opened.');
    }

    try {
        $raw = stream_get_contents($fp);
        $records = $raw ? json_decode($raw, true) : [];
        if (!is_array($records)) {
            throw new RuntimeException('Storage file is corrupted.');
        }

        $error = $update($records);
        if (is_string($error)) {
            return $error;
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        return null;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

// Append one row to a CSV file, writing the header row for a new file
function append_csv(string $file, array $row): void
{
    $fp = fopen(storage_path($file), 'a');
    if ($fp === false || !flock($fp, LOCK_EX)) {
        throw new RuntimeException('Storage file could not be opened.');
    }

    try {
        if (fstat($fp)['size'] === 0) {
            fputcsv($fp, array_keys($row), ',', '"', '');
        }
        fputcsv($fp, array_map('csv_safe', array_values($row)), ',', '"', '');
        fflush($fp);
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

// Stop spreadsheet apps from running a cell as a formula (CSV injection)
function csv_safe(mixed $value): string
{
    $value = (string) $value;
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

function new_id(string $prefix): string
{
    return $prefix . '-' . date('Ymd') . '-' . bin2hex(random_bytes(3));
}
