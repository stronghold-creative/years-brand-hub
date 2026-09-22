<?php
declare(strict_types=1);

/**
 * Asset handling. Files live in storage/assets, outside the web root, and are
 * only ever served through the download route, so access rules and logging
 * apply to every file every time.
 */

/** Extension to MIME allowlist. Anything not listed here is rejected. */
function asset_allowed_types(): array
{
    return [
        'svg'  => ['image/svg+xml'],
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'gif'  => ['image/gif'],
        'pdf'  => ['application/pdf'],
        'eps'  => ['application/postscript', 'image/x-eps'],
        'ai'   => ['application/postscript', 'application/pdf'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        'otf'  => ['font/otf', 'application/font-sfnt', 'application/vnd.ms-opentype', 'application/octet-stream'],
        'ttf'  => ['font/ttf', 'application/font-sfnt', 'application/octet-stream'],
        'woff' => ['font/woff', 'application/font-woff'],
        'woff2'=> ['font/woff2', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'mp4'  => ['video/mp4'],
    ];
}

function asset_max_bytes(): int
{
    return 50 * 1024 * 1024;   // 50 MB
}

function asset_extension(string $filename): string
{
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Validates an uploaded file. Returns an error string, or null when it passes.
 * Checks the real MIME from file content rather than trusting the browser.
 */
function asset_validation_error(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return match ($file['error'] ?? 0) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is larger than the server allows.',
            UPLOAD_ERR_NO_FILE                         => 'Choose a file to upload.',
            UPLOAD_ERR_PARTIAL                         => 'The upload was interrupted. Try again.',
            default                                    => 'The upload failed. Try again.',
        };
    }
    if (($file['size'] ?? 0) > asset_max_bytes()) {
        return 'Files need to be under ' . asset_human_size(asset_max_bytes()) . '.';
    }

    $allowed = asset_allowed_types();
    $ext = asset_extension((string) ($file['name'] ?? ''));
    if (!isset($allowed[$ext])) {
        return 'That file type is not accepted. Allowed: ' . implode(', ', array_keys($allowed)) . '.';
    }

    $mime = asset_detect_mime((string) $file['tmp_name']);
    // SVG and some font formats are detected inconsistently across servers, so
    // the extension allowlist carries the weight for those.
    $lenient = ['svg', 'otf', 'ttf', 'woff', 'woff2', 'ai', 'eps'];
    if (!in_array($ext, $lenient, true) && !in_array($mime, $allowed[$ext], true)) {
        return 'That file does not look like a genuine .' . $ext . ' file.';
    }
    return null;
}

function asset_detect_mime(string $path): string
{
    if (function_exists('finfo_open') && is_file($path)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (is_string($mime)) {
                return $mime;
            }
        }
    }
    return 'application/octet-stream';
}

/** A storage filename that cannot collide or be guessed. */
function asset_stored_name(string $originalName): string
{
    return bin2hex(random_bytes(16)) . '.' . asset_extension($originalName);
}

function asset_storage_dir(): string
{
    return ROOT_DIR . '/storage/assets';
}

function asset_path(array $asset): string
{
    return asset_storage_dir() . '/' . $asset['stored_name'];
}

function asset_human_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' KB';
    }
    return $bytes . ' B';
}

/** Tags are stored as a comma separated string, normalised to lowercase. */
function asset_parse_tags(string $raw): array
{
    $tags = array_filter(array_map(
        fn($t) => strtolower(trim($t)),
        explode(',', $raw)
    ));
    return array_values(array_unique($tags));
}

function asset_tags_string(string $raw): string
{
    return implode(', ', asset_parse_tags($raw));
}

function assets_by_tag(string $tag): array
{
    $tag = strtolower(trim($tag));
    if ($tag === '') {
        return [];
    }
    // FIND_IN_SET needs a comma list with no spaces, so normalise on the way in.
    return db_all(
        "SELECT * FROM assets WHERE FIND_IN_SET(?, REPLACE(tags, ' ', '')) > 0 ORDER BY label",
        [$tag]
    );
}

function asset_all_tags(): array
{
    $tags = [];
    foreach (db_all('SELECT tags FROM assets WHERE tags IS NOT NULL') as $row) {
        foreach (asset_parse_tags((string) $row['tags']) as $tag) {
            $tags[$tag] = ($tags[$tag] ?? 0) + 1;
        }
    }
    ksort($tags);
    return $tags;
}

function asset_zip_supported(): bool
{
    return class_exists('ZipArchive');
}

/**
 * Builds, or reuses, a zip of every asset carrying a tag. The cache key
 * includes the set of files and their versions, so replacing any asset
 * produces a new file rather than serving a stale bundle.
 */
function asset_bundle_path(string $tag): ?string
{
    if (!asset_zip_supported()) {
        return null;
    }
    $assets = assets_by_tag($tag);
    if (!$assets) {
        return null;
    }

    $signature = '';
    foreach ($assets as $asset) {
        $signature .= $asset['id'] . ':' . $asset['version'] . ':' . $asset['stored_name'] . ';';
    }
    $cacheDir = ROOT_DIR . '/storage/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    $path = $cacheDir . '/bundle-' . preg_replace('/[^a-z0-9\-]/', '', $tag) . '-' . substr(sha1($signature), 0, 12) . '.zip';

    if (is_file($path)) {
        return $path;
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return null;
    }
    $used = [];
    foreach ($assets as $asset) {
        $file = asset_path($asset);
        if (!is_file($file)) {
            continue;
        }
        // Use readable names inside the zip, de-duplicated.
        $name = $asset['original_name'];
        $n = 1;
        while (isset($used[strtolower($name)])) {
            $name = pathinfo($asset['original_name'], PATHINFO_FILENAME) . '-' . (++$n) . '.' . asset_extension($asset['original_name']);
        }
        $used[strtolower($name)] = true;
        $zip->addFile($file, $name);
    }
    $zip->close();

    return is_file($path) ? $path : null;
}

/** Removes cached bundles that no longer match current assets. */
function asset_clear_bundles(): void
{
    foreach (glob(ROOT_DIR . '/storage/cache/bundle-*.zip') ?: [] as $file) {
        @unlink($file);
    }
}

function asset_record_download(int $assetId, ?int $userId): void
{
    try {
        db_run('INSERT INTO downloads (asset_id, user_id) VALUES (?, ?)', [$assetId, $userId]);
    } catch (Throwable $e) {
        // Logging must never block a download.
    }
}

/** Streams a file as an attachment, with headers that stop the browser running it. */
function asset_send_file(string $path, string $downloadName, string $mime = 'application/octet-stream'): never
{
    if (!is_file($path)) {
        not_found();
    }
    // A safe filename for the Content-Disposition header.
    $safe = preg_replace('/[^A-Za-z0-9._\- ]/', '', $downloadName) ?: 'download';

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $safe . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, must-revalidate');

    while (ob_get_level()) {
        ob_end_clean();
    }
    readfile($path);
    exit;
}
