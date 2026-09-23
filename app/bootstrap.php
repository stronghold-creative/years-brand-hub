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

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
]);
session_start();

db_init($config['db']);
