<?php
declare(strict_types=1);

define('APP_DIR', __DIR__);
define('ROOT_DIR', dirname(__DIR__));

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

// Storage is excluded from deploys so uploads can never be overwritten by a
// push. That means it may not exist yet, so create it on first run.
foreach (['assets', 'cache'] as $dir) {
    $path = ($config['paths']['storage'] ?? ROOT_DIR . '/storage') . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}

require APP_DIR . '/lib/db.php';
require APP_DIR . '/lib/http.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/view.php';
require APP_DIR . '/lib/blocks.php';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
]);
session_start();

db_init($config['db']);
