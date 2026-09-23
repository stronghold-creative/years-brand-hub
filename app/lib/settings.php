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
            $GLOBALS['__settings'] = [];   // Not migrated yet, fall back to config.
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
