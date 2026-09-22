<?php
// Copy to app/config.php on the server and fill in. config.php is gitignored.
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'u000000000_brandhub',
        'user' => 'u000000000_brand',
        'pass' => 'change-me',
    ],
    'site' => [
        'name'     => 'Years brand hub',
        'base_url' => 'https://brand.example.com',
        // Set false while you are building, true once employees need to sign in to read.
        'require_login_to_view' => false,
    ],
    'paths' => [
        // Absolute path to the storage directory, which must sit outside public_html.
        'storage' => dirname(__DIR__) . '/storage',
    ],
    'debug' => false,
];
