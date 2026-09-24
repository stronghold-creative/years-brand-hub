<?php
declare(strict_types=1);

define('APP_DIR', __DIR__);
define('ROOT_DIR', dirname(__DIR__));

// Storage holds uploaded assets, so it is excluded from deploys and may not
// exist yet. Created here, before the config check, so a fresh server is set
// up on the very first request.
foreach (['assets', 'cache'] as $dir) {
    $path = ROOT_DIR . '/storage/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}

$configFile = APP_DIR . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing app/config.php. Copy app/config.sample.php and fill in your database details.');
}

$config = require $configFile;

if (!empty($config['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

// Every library in app/lib is loaded, in filename order. They contain only
// function definitions, so order does not matter, and adding a library never
// means remembering to edit this file.
foreach (glob(APP_DIR . '/lib/*.php') ?: [] as $library) {
    require $library;
}

if (!function_exists('db_init')) {
    http_response_code(500);
    exit('The app/lib directory is incomplete. Re-deploy, or check that every file in app/lib uploaded.');
}

/**
 * A failed query usually means a migration has not been applied yet, which is
 * a recoverable situation, not a crash. Say so plainly and never show the
 * stack trace, which would leak server paths.
 */
set_exception_handler(function (Throwable $e) use ($config) {
    $pending = [];
    try {
        $pending = migrations_pending();
    } catch (Throwable $ignored) {
        // The error handler must never throw, so this stays quiet on purpose.
    }
    error_log('unhandled: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    if ($pending) {
        echo '<!doctype html><meta charset="utf-8">'
           . '<title>Update waiting</title>'
           . '<style>body{font-family:system-ui,sans-serif;max-width:32rem;margin:15vh auto;padding:0 1.5rem;line-height:1.6;color:#1F1F1F}'
           . 'a{color:#5B3FA3}code{background:#F4F2EE;padding:.125rem .375rem}</style>'
           . '<h1 style="font-size:1.375rem;font-weight:600">A database update is waiting</h1>'
           . '<p>The code has moved ahead of the database. Sign in and open '
           . '<a href="/admin/settings">Settings</a>, then click Apply updates.</p>'
           . '<p style="font-size:.875rem;color:#6E6B68">Waiting: <code>'
           . htmlspecialchars(implode(', ', $pending), ENT_QUOTES) . '</code></p>';
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>'
           . '<style>body{font-family:system-ui,sans-serif;max-width:32rem;margin:15vh auto;padding:0 1.5rem;line-height:1.6}</style>'
           . '<h1 style="font-size:1.375rem;font-weight:600">Something went wrong</h1>'
           . '<p>The page could not be loaded. If this keeps happening, tell the brand team.</p>';
        if (!empty($config['debug'])) {
            echo '<pre style="white-space:pre-wrap;font-size:.75rem;color:#A8322D">'
               . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</pre>';
        }
    }
    exit;
});

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
]);
session_start();

db_init($config['db']);
