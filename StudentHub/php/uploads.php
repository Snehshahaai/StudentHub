<?php
/**
 * StudentHub - Event poster uploads
 *
 * Checks, in order: upload errors -> file size -> real file type (read from
 * the file's bytes, not the name or the browser) -> extension matches the
 * type -> image dimensions. Then the image is re-encoded with GD, which
 * drops anything hidden inside the file (scripts, EXIF/GPS data), scales
 * very large posters down, and saves it under a random name.
 */

declare(strict_types=1);

const POSTER_MAX_BYTES  = 2 * 1024 * 1024;     // 2 MB
const POSTER_MIN_WIDTH  = 300;
const POSTER_MIN_HEIGHT = 200;
const POSTER_MAX_PIXELS = 6000;                // per side, before resizing (guards against huge "bomb" images)
const POSTER_SAVE_WIDTH = 1600;                // wider posters are scaled down to this
const POSTER_DIR        = __DIR__ . '/../uploads/events';
const POSTER_URL_PREFIX = 'uploads/events/';   // stored in events.image_path, relative to the site root

// Detected MIME type => [saved extension, extensions allowed in the original name]
const POSTER_TYPES = [
    'image/jpeg' => ['jpg', ['jpg', 'jpeg']],
    'image/png'  => ['png', ['png']],
    'image/webp' => ['webp', ['webp']],
];

/** "2 MB", "850 KB" */
function human_size(int $bytes): string
{
    return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
}

/** True when the request was bigger than post_max_size, so PHP dropped all fields and files. */
function post_too_large(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST) && empty($_FILES)
        && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}

/**
 * Validate and save an uploaded poster from $_FILES[...].
 * Returns ['path' => 'uploads/events/xxx.jpg'] on success,
 *         ['path' => null] when no file was chosen,
 *         ['error' => 'message'] when the file is rejected.
 */
function save_poster_upload(?array $file): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null];
    }
    if (!is_int($file['error']) || is_array($file['name'])) {
        return ['error' => 'Please upload a single image file.'];
    }

    // 1. Upload errors reported by PHP
    $limit = human_size(POSTER_MAX_BYTES);
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['error' => "The poster is too large. The maximum size is {$limit}."];
        case UPLOAD_ERR_PARTIAL:
            return ['error' => 'The poster was only partly uploaded. Please try again.'];
        default:
            error_log('[StudentHub upload] PHP upload error ' . $file['error']);
            return ['error' => 'The server could not receive the poster. Please try again.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'Invalid upload.'];
    }

    // 2. File size (checked on the real file, not the size the browser claimed)
    $size = filesize($file['tmp_name']);
    if ($size === 0) {
        return ['error' => 'The poster file is empty.'];
    }
    if ($size > POSTER_MAX_BYTES) {
        return ['error' => 'The poster is ' . human_size($size) . ". The maximum size is {$limit}."];
    }

    // 3. Real file type from the file's contents
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(POSTER_TYPES[$mime])) {
        return ['error' => 'Only JPG, PNG or WebP images can be uploaded as posters.'];
    }

    // 4. The file name's extension must match what the file really is
    [$saveExt, $allowedExt] = POSTER_TYPES[$mime];
    $nameExt = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($nameExt, $allowedExt, true)) {
        return ['error' => 'The file name ends in ".' . $nameExt . '" but the file is a '
            . strtoupper($saveExt) . ' image. Please upload a correctly named JPG, PNG or WebP file.'];
    }

    // 5. Dimensions
    $info = @getimagesize($file['tmp_name']);
    if (!$info) {
        return ['error' => 'The poster could not be read as an image. It may be damaged.'];
    }
    [$width, $height] = $info;
    if ($width > POSTER_MAX_PIXELS || $height > POSTER_MAX_PIXELS) {
        return ['error' => "The poster is {$width}×{$height} pixels. The maximum is " . POSTER_MAX_PIXELS . ' pixels per side.'];
    }
    if ($width < POSTER_MIN_WIDTH || $height < POSTER_MIN_HEIGHT) {
        return ['error' => "The poster is only {$width}×{$height} pixels. It must be at least "
            . POSTER_MIN_WIDTH . '×' . POSTER_MIN_HEIGHT . '.'];
    }

    // 6. Re-encode with GD under a random name
    $image = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if (!$image) {
        return ['error' => 'The poster could not be processed. Please try a different image.'];
    }
    if ($width > POSTER_SAVE_WIDTH) {
        // imagecopyresampled works on every GD build (some lack imagescale's bicubic mode)
        $newHeight = (int) round($height * POSTER_SAVE_WIDTH / $width);
        $scaled = imagecreatetruecolor(POSTER_SAVE_WIDTH, $newHeight);
        imagealphablending($scaled, false);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, POSTER_SAVE_WIDTH, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $scaled;
    }
    if ($saveExt !== 'jpg') {
        imagealphablending($image, false);     // keep PNG / WebP transparency
        imagesavealpha($image, true);
    }

    if (!is_dir(POSTER_DIR) && !mkdir(POSTER_DIR, 0755, true) && !is_dir(POSTER_DIR)) {
        imagedestroy($image);
        error_log('[StudentHub upload] could not create ' . POSTER_DIR);
        return ['error' => 'The server could not save the poster. Please try again later.'];
    }

    $name = 'event-' . date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $saveExt;
    $target = POSTER_DIR . '/' . $name;
    $saved = match ($saveExt) {
        'jpg'  => imagejpeg($image, $target, 85),
        'png'  => imagepng($image, $target, 6),
        'webp' => imagewebp($image, $target, 85),
    };
    imagedestroy($image);

    if (!$saved) {
        @unlink($target);
        error_log('[StudentHub upload] could not write ' . $target);
        return ['error' => 'The server could not save the poster. Please try again later.'];
    }
    return ['path' => POSTER_URL_PREFIX . $name];
}

/** Delete a poster file we uploaded. Built-in images (images/...) are never touched. */
function delete_poster_file(?string $path): void
{
    if (!$path || !preg_match('#^uploads/events/event-[0-9]{8}-[a-f0-9]{16}\.(jpg|png|webp)$#', $path)) {
        return;
    }
    $file = POSTER_DIR . '/' . basename($path);
    if (is_file($file)) {
        @unlink($file);
    }
}
