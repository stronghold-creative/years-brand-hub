<?php
declare(strict_types=1);
define('APP_DIR','/mnt/user-data/outputs/brandhub/app');
define('ROOT_DIR','/mnt/user-data/outputs/brandhub');

/**
 * Checks that every URL the interface calls actually routes.
 *
 * Two bugs got through without this: a route that was never inserted because
 * a search-and-replace silently failed, and two patterns with one backslash
 * too many, which made them match nothing at all. Both looked fine in the
 * source and both returned 404 in the browser.
 *
 *   php tools/check-routes.php
 */
$src = file_get_contents(APP_DIR . '/routes.php');
preg_match_all("/preg_match\((\'[^\']+\')/", $src, $m);
$patterns = array_map(fn($literal) => eval("return $literal;"), $m[1]);

$expected = [
    'GET  /'                       => '/',
    'GET  /s/foundations'          => '/s/foundations',
    'GET  /i/9'                    => '/i/9',
    'GET  /i/9/400'                => '/i/9/400',
    'GET  /d/9'                    => '/d/9',
    'GET  /bundle/logos'           => '/bundle/logos',
    'GET  /admin'                  => '/admin',
    'GET  /admin/pages/12'         => '/admin/pages/12',
    'POST /admin/pages/12/save'    => '/admin/pages/12/save',
    'POST /admin/pages/12/cover'   => '/admin/pages/12/cover',
    'POST /admin/pages/12/delete'  => '/admin/pages/12/delete',
    'GET  /admin/pages/12/preview' => '/admin/pages/12/preview',
    'GET  /admin/pages/12/versions'=> '/admin/pages/12/versions',
    'POST /admin/pages/12/move/up' => '/admin/pages/12/move/up',
    'POST /admin/assets/3/replace' => '/admin/assets/3/replace',
    'POST /admin/assets/3/delete'  => '/admin/assets/3/delete',
    'GET  /admin/assets.json'      => '/admin/assets.json',
    'GET  /admin/export'           => '/admin/export',
    'POST /admin/sections/move/up' => '/admin/sections/move/up',
];

foreach ($expected as $label => $url) {
    $hit = false;
    foreach ($patterns as $pattern) {
        // Patterns are evaluated as PHP sees them, not as they look in the
        // source, which is where the extra backslashes were hiding.
        if (@preg_match($pattern, $url)) { $hit = true; break; }
    }
    // Some endpoints are plain string comparisons rather than patterns.
    if (!$hit) {
        $hit = str_contains($src, "\$path === '" . $url . "'");
    }
    printf("  %-30s %s\n", $label, $hit ? 'routed' : 'NO ROUTE');
}
