<?php
declare(strict_types=1);

/**
 * Checks that every URL the interface calls actually routes.
 *
 * Two bugs got through without this: a route that was never inserted because
 * a search-and-replace silently failed, and two patterns with one backslash
 * too many, which made them match nothing at all. Both looked fine in the
 * source and both returned 404 in the browser.
 *
 *   php tools/check-routes.php
 *
 * Paths come from this file's own location, so it runs from any checkout. It
 * previously held absolute paths from the machine it was written on and could
 * not run anywhere else at all.
 */
define('ROOT_DIR', dirname(__DIR__));
define('APP_DIR', ROOT_DIR . '/app');
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
    'GET  /search.json'            => '/search.json',
    'GET  /admin'                  => '/admin',
    'GET  /admin/pages/12'         => '/admin/pages/12',
    'POST /admin/pages/12/save'    => '/admin/pages/12/save',
    'POST /admin/pages/12/cover'   => '/admin/pages/12/cover',
    'POST /admin/pages/12/delete'  => '/admin/pages/12/delete',
    'GET  /admin/pages/12/preview' => '/admin/pages/12/preview',
    'GET  /admin/pages/12/versions'=> '/admin/pages/12/versions',
    'POST /admin/pages/12/move/up' => '/admin/pages/12/move/up',
    'POST /admin/pages/12/duplicate' => '/admin/pages/12/duplicate',
    'GET  /admin/pages/12/versions/3/preview' => '/admin/pages/12/versions/3/preview',
    'GET  /admin/styles/preview'   => '/admin/styles/preview',
    'POST /admin/assets/3/replace' => '/admin/assets/3/replace',
    'POST /admin/assets/3/delete'  => '/admin/assets/3/delete',
    'GET  /admin/assets.json'      => '/admin/assets.json',
    'POST /admin/assets/add'       => '/admin/assets/add',
    'GET  /admin/export'           => '/admin/export',
    'POST /admin/sections/move/up' => '/admin/sections/move/up',
    'GET  /admin/components'       => '/admin/components',
    'POST /admin/components/new'   => '/admin/components/new',
    'POST /admin/components/7/move/up' => '/admin/components/7/move/up',
    'POST /admin/components/7/delete'  => '/admin/components/7/delete',
    'GET  /admin/components/7'         => '/admin/components/7',
    'POST /admin/components/7/css'     => '/admin/components/7/css',
    'POST /admin/components/7/variants/new'        => '/admin/components/7/variants/new',
    'POST /admin/components/7/variants/2/save'     => '/admin/components/7/variants/2/save',
    'POST /admin/components/7/variants/2/default'  => '/admin/components/7/variants/2/default',
    'POST /admin/components/7/variants/2/move/up'  => '/admin/components/7/variants/2/move/up',
    'POST /admin/components/7/variants/2/delete'   => '/admin/components/7/variants/2/delete',
    'GET  /components/button/frame/2'  => '/components/button/frame/2',
];

$failed = 0;
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
    if (!$hit) {
        $failed++;
    }
    printf("  %-30s %s\n", $label, $hit ? 'routed' : 'NO ROUTE');
}

// A non-zero exit, so this can gate a deploy rather than only be read.
echo $failed === 0 ? "\nEvery URL routes.\n" : "\n$failed URL(s) do not route.\n";
exit($failed === 0 ? 0 : 1);
