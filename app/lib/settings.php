<?php
declare(strict_types=1);

/**
 * Settings live in the database so they can be changed from the admin.
 * config.php still provides the fallback, so a fresh install behaves sensibly
 * before anyone has saved anything.
 */
function settings_all(): array
{
    if (!isset($GLOBALS['__settings'])) {
        try {
            $loaded = [];
            foreach (db_all('SELECT name, value FROM settings') as $row) {
                $loaded[$row['name']] = $row['value'];
            }
            $GLOBALS['__settings'] = $loaded;
        } catch (Throwable $e) {
                // Before the settings table exists, config supplies the values.
            error_log('settings unavailable: ' . $e->getMessage());
            $GLOBALS['__settings'] = [];
        }
    }
    return $GLOBALS['__settings'];
}

function setting(string $name, mixed $fallback = null): mixed
{
    $all = settings_all();
    return array_key_exists($name, $all) ? $all[$name] : $fallback;
}

function setting_bool(string $name, bool $fallback = false): bool
{
    $value = setting($name);
    return $value === null ? $fallback : $value === '1';
}

function setting_save(string $name, string $value): void
{
    db_run(
        'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
        [$name, $value]
    );
    unset($GLOBALS['__settings']);
}

/** True when a signed-in account is needed to read the public site. */
function site_is_private(array $config): bool
{
    return setting_bool('require_login_to_view', !empty($config['site']['require_login_to_view']));
}

/** The order sections appear in, as chosen in the admin. */
function section_order(): array
{
    $raw = json_decode((string) setting('section_order', '[]'), true);
    return is_array($raw) ? array_values(array_filter(array_map('strval', $raw))) : [];
}

/**
 * Sorts a section-keyed array by the chosen order. Anything not in the list
 * follows, alphabetically, so a new section never disappears.
 */
function sort_sections(array $bySection): array
{
    $order = section_order();
    $known = [];
    foreach ($order as $name) {
        if (array_key_exists($name, $bySection)) {
            $known[$name] = $bySection[$name];
            unset($bySection[$name]);
        }
    }
    ksort($bySection);
    return $known + $bySection;
}

function section_order_save(array $names): void
{
    setting_save('section_order', json_encode(array_values($names)));
}

/** Moves one section up or down, keeping every other section's position. */
function section_move(string $name, string $direction, array $allSections): void
{
    $order = section_order();
    // Start from the current display order so a never-ordered list still moves.
    foreach ($allSections as $section) {
        if (!in_array($section, $order, true)) {
            $order[] = $section;
        }
    }
    $order = array_values(array_filter($order, fn($s) => in_array($s, $allSections, true)));

    $index = array_search($name, $order, true);
    if ($index === false) {
        return;
    }
    $target = $direction === 'up' ? $index - 1 : $index + 1;
    if ($target < 0 || $target >= count($order)) {
        return;
    }
    array_splice($order, $target, 0, array_splice($order, $index, 1));
    section_order_save($order);
}

/** The uploaded logo for the masthead, if one has been chosen. */
function site_logo(): ?array
{
    $id = (int) setting('site_logo', 0);
    if ($id <= 0 || !function_exists('asset_find')) {
        return null;
    }
    $asset = asset_find($id);
    return ($asset && asset_is_inline_image($asset)) ? $asset : null;
}

/**
 * The site's mark, as a link home.
 *
 * Reading pages put it at the head of the rail and narrow ones put it in the
 * bar, because a rail that slides away cannot hold a mark that has to stay
 * visible. That is two places in the document, which is normally how markup
 * drifts, so the markup itself lives here and both places call this.
 */
function site_mark(string $class = 'mark'): string
{
    $logo = site_logo();
    $inside = $logo
        ? '<img src="' . e(asset_image_url($logo)) . '" alt="Years" class="mark__logo">'
        : '<span class="mark__wordmark">Years</span>';

    return '<a class="' . e($class) . '" href="/">' . $inside . '</a>';
}

/**
 * The areas in the masthead toggle. Brand is the guidelines; Components is for
 * documenting web components for design and development, and is switched off
 * until there is something in it.
 */
function site_areas(): array
{
    return [
        ['href' => '/',           'label' => (string) setting('area_one_label', 'Brand'),      'key' => 'brand'],
        ['href' => '/components', 'label' => (string) setting('area_two_label', 'Components'), 'key' => 'components'],
    ];
}

function components_enabled(): bool
{
    return setting_bool('components_enabled', false);
}

/**
 * Everything worth backing up, as a plain array. Uploaded files are not
 * included, only the register that describes them, so a restore knows what is
 * missing if storage/ was lost too.
 */
function export_payload(): array
{
    return [
        'exported_at' => date('c'),
        'pages'       => db_all('SELECT slug, title, section, summary, status, sort_order, show_on_home, cover_asset_id, blocks, updated_at FROM pages ORDER BY section, sort_order'),
        'settings'    => db_all('SELECT name, value FROM settings ORDER BY name'),
        'assets'      => db_all('SELECT id, stored_name, original_name, label, mime, bytes, version, tags, uploaded_at FROM assets ORDER BY id'),
        'users'       => db_all('SELECT name, email, role FROM users ORDER BY name'),
        'migrations'  => db_all('SELECT filename FROM migrations ORDER BY filename'),
    ];
}

/**
 * True when Plus Jakarta Sans has been self-hosted. Until the files are in
 * place the site falls back to Google Fonts, so it never looks broken, but
 * self-hosting removes an external dependency and is faster.
 */
function fonts_are_local(): bool
{
    static $known = null;
    if ($known === null) {
        $known = is_file(ROOT_DIR . '/public_html/assets/fonts/plus-jakarta-sans-400.woff2');
    }
    return $known;
}

/** The interface weights actually used, so no file is asked for needlessly. */
/**
 * Every typeface actually on the server, read from the files themselves.
 *
 * Returns `['More Years' => ['/assets/fonts/MoreYears-Regular.woff2' => 400]]`,
 * so one function answers three questions that were previously answered in three
 * places: what the type specimen may offer, what `@font-face` the component
 * preview needs, and whether a face is available at all.
 *
 * The filename is the declaration. `MoreYears-Regular.woff2` is More Years at
 * 400; `SomeFace-Bold.woff2` is Some Face at 700. Drop a file in the folder and
 * it works, with nothing to register — which is the same bargain the rest of this
 * project makes, and the reason four faces were once declared in CSS for files
 * that had never been uploaded.
 */
function brand_fonts(): array
{
    static $found = null;
    if ($found !== null) {
        return $found;
    }

    $weights = [
        'thin' => 100, 'extralight' => 200, 'ultralight' => 200, 'light' => 300,
        'regular' => 400, 'book' => 400, 'normal' => 400, 'medium' => 500,
        'semibold' => 600, 'demibold' => 600, 'bold' => 700,
        'extrabold' => 800, 'ultrabold' => 800, 'black' => 900, 'heavy' => 900,
    ];

    /** `MoreYears-SemiBold` becomes ['More Years', 600]. */
    $read = function (string $name) use ($weights): array {
        $parts  = explode('-', $name, 2);
        $family = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $parts[0]) ?? $parts[0];
        $style  = strtolower(str_replace(['-', ' ', '_', 'italic'], '', $parts[1] ?? 'Regular'));
        return [trim(str_replace('_', ' ', $family)), $weights[$style] ?? 400];
    };

    $found = [];

    // Deployed with the code.
    foreach (glob(ROOT_DIR . '/public_html/assets/fonts/*.woff2') ?: [] as $file) {
        $name = basename($file, '.woff2');
        // The interface face is written by font_head() with its own naming, and
        // is not a brand face to document.
        if (str_starts_with($name, 'plus-jakarta-sans-')) {
            continue;
        }
        [$family, $weight] = $read($name);
        $found[$family]['/assets/fonts/' . basename($file)] = $weight;
    }

    /**
     * And uploaded through the asset library.
     *
     * Without this a typeface could be uploaded and still not exist as far as the
     * site was concerned: the folder above is deployed with the code, so adding a
     * face meant a commit. A component imported from Figma in a face nobody could
     * add was the thing standing between "nearly right" and right.
     *
     * The family and weight are read from the original filename, because that is
     * what a foundry names its files and what a designer has to hand —
     * `Mulish-SemiBold.woff2` is Mulish at 600. The label is a fallback for a file
     * named something less helpful.
     */
    if (function_exists('asset_is_font')) {
        try {
            $rows = db_all("SELECT id, label, original_name, mime, version FROM assets ORDER BY id");
        } catch (Throwable $e) {
            $rows = [];
        }
        foreach ($rows as $row) {
            if (!asset_is_font($row)) {
                continue;
            }
            $base = pathinfo((string) $row['original_name'], PATHINFO_FILENAME);
            [$family, $weight] = $read($base !== '' ? $base : (string) $row['label']);
            if ($family === '') {
                continue;
            }
            // The version is in the URL because /f/ is served immutable: replacing a
            // face in place keeps its id, and without this the old one would be
            // cached for a year.
            $found[$family]['/f/' . (int) $row['id'] . '?v=' . (int) ($row['version'] ?? 1)] = $weight;
        }
    }

    ksort($found);
    return $found;
}

/**
 * `@font-face` for every brand typeface on the server.
 *
 * The public site declares More Years in `site.css`, but the component preview
 * deliberately does not load `site.css` — so a component whose CSS asked for a
 * brand face got the fallback, and type imported from Figma never matched the
 * design. This gives that frame the real faces.
 */
function brand_font_faces(): string
{
    $css = '';
    foreach (brand_fonts() as $family => $files) {
        foreach ($files as $path => $weight) {
            // A deployed file is stamped with its modification time so a replaced
            // face beats the cache. An uploaded one is served from /f/{id}, where
            // there is no file on disk to stamp — asset_url() would fall back to
            // the current time and bust the cache on every single request.
            $url = str_starts_with($path, '/f/') ? $path : asset_url($path);
            $format = str_ends_with($path, '.woff') ? 'woff' : 'woff2';
            $css .= "@font-face{font-family:'" . $family . "';"
                  . "src:url('" . e($url) . "') format('" . $format . "');"
                  . 'font-weight:' . $weight . ';font-style:normal;font-display:swap}';
        }
    }
    return $css;
}

function interface_font_weights(): array
{
    return [400, 500, 600, 700];
}

/**
 * Everything the head needs for the interface face.
 *
 * Self-hosted when the files are there, Google Fonts when they are not. The
 * @font-face rules cannot live in the stylesheet, because a static rule cannot
 * know whether the file behind it exists: they were there, the files were not,
 * and every page load fetched four woff2 files and got four 404s.
 *
 * Only weights that are actually on disk are declared, so a half-finished
 * upload degrades to the weights present rather than to a page of failures.
 */
function font_head(): string
{
    if (!fonts_are_local()) {
        return '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n"
             . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n"
             . '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">';
    }

    $faces = '';
    $dir = ROOT_DIR . '/public_html/assets/fonts';

    foreach (interface_font_weights() as $weight) {
        $file = '/assets/fonts/plus-jakarta-sans-' . $weight . '.woff2';
        if (!is_file($dir . '/plus-jakarta-sans-' . $weight . '.woff2')) {
            continue;
        }
        $faces .= "@font-face{font-family:'Plus Jakarta Sans';"
                . "src:url('" . e(asset_url($file)) . "') format('woff2');"
                . 'font-weight:' . $weight . ';font-display:swap}';
    }

    return '<link rel="preload" href="' . e(asset_url('/assets/fonts/plus-jakarta-sans-400.woff2'))
         . '" as="font" type="font/woff2" crossorigin>' . "\n"
         . '<style>' . $faces . '</style>';
}
