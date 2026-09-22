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
