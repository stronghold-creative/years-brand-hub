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
