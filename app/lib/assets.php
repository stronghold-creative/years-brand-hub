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
        // Logging must never block a download, but it should be visible.
        error_log('download not recorded for asset ' . $assetId . ': ' . $e->getMessage());
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

/** Image types that are safe to render inline in a page. */
function asset_is_inline_image(array $asset): bool
{
    return in_array($asset['mime'], ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml'], true);
}

/** Video that plays in a page rather than downloading. */
function asset_is_inline_video(array $asset): bool
{
    return $asset['mime'] === 'video/mp4';
}

/**
 * A font file in the asset library.
 *
 * Only woff2 and woff are served to a browser: the other two are accepted as
 * uploads because they are what a foundry sends and what a designer has to hand,
 * but a browser has no reason to be given a ttf when a woff2 exists, and serving
 * one is handing over a more reusable copy of a licensed font than it needs.
 */
function asset_is_font(array $asset): bool
{
    return in_array($asset['mime'], ['font/woff2', 'font/woff', 'application/font-woff'], true)
        || in_array(strtolower(pathinfo((string) $asset['original_name'], PATHINFO_EXTENSION)), ['woff2', 'woff'], true);
}

/**
 * Anything that displays in a page rather than downloading.
 *
 * The video block offers an uploaded file as an alternative to a YouTube or
 * Vimeo link, and that file has to be served somewhere. It cannot go through
 * the download route, which sends everything as an attachment, so it comes
 * through the same inline route as images.
 */
function asset_is_inline_media(array $asset): bool
{
    return asset_is_inline_image($asset) || asset_is_inline_video($asset);
}

/**
 * Streams a file for display rather than download. SVG is served with a
 * restrictive CSP, because an SVG can carry script and is same-origin here.
 *
 * Video is served with byte range support. Without it Safari will not play a
 * video at all, and no browser can seek within one.
 */
function asset_send_inline(array $asset, ?int $width = null): never
{
    $path = asset_path($asset);

    // Serve a generated variant when one was asked for and exists; otherwise
    // fall back to the original, so a missing variant is never a broken image.
    if ($width && in_array($width, asset_variant_widths(), true)) {
        $variant = asset_storage_dir() . '/' . asset_variant_name($asset['stored_name'], $width);
        if (is_file($variant)) {
            $path = $variant;
        }
    }

    if (!is_file($path)) {
        not_found();
    }

    header('Content-Type: ' . $asset['mime']);
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; sandbox');
    // Cached hard: the URL carries the version, so a replacement busts it.
    header('Cache-Control: private, max-age=604800, immutable');

    while (ob_get_level()) {
        ob_end_clean();
    }

    if (asset_is_inline_video($asset)) {
        asset_send_range($path);
    }

    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

/**
 * Serves a file honouring a Range request, for media that is played rather
 * than loaded in one go. An unsatisfiable or absent range sends the whole
 * file, so this is always safe to call.
 */
function asset_send_range(string $path): never
{
    $size  = (int) filesize($path);
    $range = (string) ($_SERVER['HTTP_RANGE'] ?? '');
    header('Accept-Ranges: bytes');

    $start = 0;
    $end   = $size - 1;

    if (preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m)) {
        // "bytes=-500" means the last 500 bytes, not a range starting at zero.
        if ($m[1] === '' && $m[2] !== '') {
            $start = max(0, $size - (int) $m[2]);
        } else {
            $start = (int) $m[1];
            if ($m[2] !== '') {
                $end = (int) $m[2];
            }
        }
        $end = min($end, $size - 1);

        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }

    header('Content-Length: ' . ($end - $start + 1));

    $handle = fopen($path, 'rb');
    if (!$handle) {
        not_found();
    }
    fseek($handle, $start);
    $remaining = $end - $start + 1;
    // Read in chunks so a large file never has to fit in memory at once.
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, (int) min(262144, $remaining));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        flush();
        $remaining -= strlen($chunk);
    }
    fclose($handle);
    exit;
}

/**
 * The URL for showing an asset inline, versioned so replacements take effect.
 * A width asks for one of the generated variants.
 */
function asset_image_url(array $asset, ?int $width = null): string
{
    return '/i/' . (int) $asset['id']
         . ($width ? '/' . $width : '')
         . '?v=' . rawurlencode((string) $asset['version']);
}

function asset_find(int $id): ?array
{
    return db_one('SELECT * FROM assets WHERE id = ?', [$id]);
}

/* ===========================================================================
   Image variants

   A 7MB photograph uploaded as an asset should not be what a card thumbnail
   serves. On upload, smaller copies are generated beside the original and the
   right one is chosen at render time, with a srcset so the browser can pick.

   Originals are never modified, and if GD is unavailable everything falls
   back to serving the original, so the site works either way.
   =========================================================================== */

/** The widths generated for every raster image. */
function asset_variant_widths(): array
{
    return [400, 800, 1600];
}

function asset_can_resize(): bool
{
    return extension_loaded('gd') && function_exists('imagecreatetruecolor');
}

/** Raster formats worth resizing. SVG is already small; GIF may be animated. */
function asset_is_resizable(string $mime): bool
{
    return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);
}

function asset_variant_name(string $storedName, int $width): string
{
    $dot = strrpos($storedName, '.');
    return substr($storedName, 0, $dot) . '-' . $width . substr($storedName, $dot);
}

/**
 * Creates the smaller copies. Skips any width at or above the original, so a
 * small image is never scaled up. Returns the widths actually written.
 */
function asset_make_variants(string $storedName, string $mime): array
{
    if (!asset_can_resize() || !asset_is_resizable($mime)) {
        return [];
    }
    $path = asset_storage_dir() . '/' . $storedName;
    $size = @getimagesize($path);
    if (!$size) {
        return [];
    }
    [$sourceWidth, $sourceHeight] = $size;

    $source = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png'  => @imagecreatefrompng($path),
        'image/webp' => @imagecreatefromwebp($path),
        default      => null,
    };
    if (!$source) {
        return [];
    }

    $made = [];
    foreach (asset_variant_widths() as $width) {
        if ($width >= $sourceWidth) {
            continue;
        }
        $height = (int) round($sourceHeight * ($width / $sourceWidth));
        $canvas = imagecreatetruecolor($width, $height);

        // Transparency has to be preserved or a logo gains a black background.
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        $target = asset_storage_dir() . '/' . asset_variant_name($storedName, $width);

        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($canvas, $target, 82),
            'image/png'  => imagepng($canvas, $target, 6),
            'image/webp' => imagewebp($canvas, $target, 82),
            default      => false,
        };
        imagedestroy($canvas);
        if ($ok) {
            $made[] = $width;
        }
    }
    imagedestroy($source);
    return $made;
}

/** Removes an asset's variants, for a replace or a delete. */
function asset_remove_variants(string $storedName): void
{
    foreach (asset_variant_widths() as $width) {
        $path = asset_storage_dir() . '/' . asset_variant_name($storedName, $width);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/** The widths that actually exist on disk for an asset. */
function asset_available_widths(array $asset): array
{
    $out = [];
    foreach (asset_variant_widths() as $width) {
        if (is_file(asset_storage_dir() . '/' . asset_variant_name($asset['stored_name'], $width))) {
            $out[] = $width;
        }
    }
    return $out;
}

/**
 * A srcset for an asset, so the browser downloads a size that suits the screen
 * rather than the original every time. Empty when there are no variants.
 */
function asset_srcset(array $asset): string
{
    $parts = [];
    foreach (asset_available_widths($asset) as $width) {
        $parts[] = asset_image_url($asset, $width) . ' ' . $width . 'w';
    }
    if ($parts) {
        $parts[] = asset_image_url($asset) . ' ' . (int) (max(asset_variant_widths()) * 2) . 'w';
    }
    return implode(', ', $parts);
}

/** The intrinsic size of an image, for width and height attributes. */
function asset_dimensions(array $asset): ?array
{
    $size = @getimagesize(asset_path($asset));
    return $size ? ['width' => (int) $size[0], 'height' => (int) $size[1]] : null;
}

/**
 * How many pages use an asset, as a cover or inside a block. Matching on the
 * stored JSON is crude, but it is exact enough for a warning and needs no
 * extra table to maintain.
 */
function asset_usage(int $assetId): array
{
    $needleBlock = '"asset":"' . $assetId . '"';
    $rows = db_all(
        'SELECT id, title, slug FROM pages
          WHERE cover_asset_id = ? OR blocks LIKE ?
          ORDER BY title',
        [$assetId, '%' . $needleBlock . '%']
    );
    return $rows;
}
