<?php
declare(strict_types=1);

/**
 * Asserts the things the component preview's safety rests on.
 *
 * The preview frame renders author-supplied HTML, CSS and JavaScript. That is
 * only acceptable because of a small number of exact strings — a sandbox
 * attribute without one particular value in it, a content security policy that
 * allows scripts but no network, and an admin screen that never prints the markup
 * as markup. Every one of those is a one-word edit away from being wrong, and
 * none of them fails loudly when it is: the page still renders, and looks fine.
 *
 * So they are checked here, and the check exits non-zero so it can gate a deploy.
 * Needs no database.
 *
 *   php tools/check-components.php
 */

define('APP_DIR', dirname(__DIR__) . '/app');
define('ROOT_DIR', dirname(__DIR__));

require APP_DIR . '/lib/http.php';
require APP_DIR . '/lib/icons.php';

// The settings these views read are database-backed and irrelevant here.
function setting(string $key, $default = null) { return $default; }
function setting_bool(string $key, bool $default = false): bool { return $default; }
function current_user(): ?array { return ['id' => 1, 'role' => 'admin', 'name' => 'Check']; }
function style_css(): string { return ":root { --s-radius: 6px; }"; }
function font_head(): string { return '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=X">'; }

require APP_DIR . '/lib/components.php';

$_SESSION = ['csrf' => str_repeat('a', 64)];

$failed = 0;

/**
 * Any warning or notice counts as a failure.
 *
 * Without this the checks below pass while the views quietly emit "undefined
 * variable" — which is exactly what happened when the component screen gained a
 * field that needed a new variable passed to it: the page rendered, the
 * assertions held, and the datalist was silently empty. A template's warnings are
 * its bugs.
 */
error_reporting(E_ALL);
set_error_handler(function (int $level, string $message, string $file, int $line): bool {
    global $failed;
    $failed++;
    // STDERR, not stdout: these fire while a view is being captured with
    // ob_start(), so anything printed normally goes into the buffer and is
    // thrown away with it — which is how two of these went unseen at first.
    fprintf(STDERR, "  !! %s in %s:%d\n", $message, basename($file), $line);
    return true;
});
function ok(string $what, bool $pass): void
{
    global $failed;
    if (!$pass) {
        $failed++;
    }
    printf("  %-58s %s\n", $what, $pass ? 'ok' : 'FAILED');
}

echo "\nThe content security policy\n";

$csp = component_frame_csp();
ok("starts from default-src 'none'",        str_contains($csp, "default-src 'none'"));
ok("connect-src 'none', so nothing phones home", str_contains($csp, "connect-src 'none'"));
ok("form-action 'none', so no form posts out",   str_contains($csp, "form-action 'none'"));
ok("base-uri 'none'",                      str_contains($csp, "base-uri 'none'"));
ok("object-src 'none'",                    str_contains($csp, "object-src 'none'"));
ok("frame-ancestors 'self', so only we may frame it", str_contains($csp, "frame-ancestors 'self'"));
ok("no unsafe-eval anywhere",              !str_contains($csp, 'unsafe-eval'));
// A wildcard in connect-src or default-src would undo the point of the policy.
ok("no wildcard source",                   !preg_match('/(^|[; ])(default|connect)-src[^;]*\*/', $csp));

echo "\nA variant's markup on the admin screen\n";

// Hostile content, which an editor could paste in good faith from anywhere.
$nasty = '<img src=x onerror="alert(1)"><script>alert(2)</script>';

$component = [
    'id' => 7, 'slug' => 'button', 'name' => 'Button', 'group_name' => 'Actions',
    'status' => 'ready', 'summary' => null, 'figma_url' => null,
    'base_css' => '.btn { border-radius: var(--s-radius); }', 'base_js' => '',
    'docs' => '[]', 'notes_override' => '{}', 'sort_order' => 0,
    'updated_at' => '2026-09-27 10:00:00',
];
$variants = [
    ['id' => 2, 'component_id' => 7, 'label' => 'Primary', 'markup' => '<button class="btn">Buy</button>',
     'variant_css' => '.btn { background: #7257B7; }', 'notes' => 'One per screen.',
     'is_default' => 1, 'sort_order' => 0],
    ['id' => 3, 'component_id' => 7, 'label' => 'Pasted from anywhere', 'markup' => $nasty,
     'variant_css' => '', 'notes' => null, 'is_default' => 0, 'sort_order' => 1],
];

$open = component_default_variant($variants);
$groups = ['Actions', 'Forms'];
$_GET = ['v' => '3'];

ob_start();
require APP_DIR . '/views/admin/component.php';
$admin = ob_get_clean();

ok('the screen rendered',                   strlen($admin) > 1000);
ok('no live <script> from a variant',        !str_contains($admin, '<script>alert(2)'));
ok('no live onerror attribute',              !str_contains($admin, 'onerror="alert'));
ok('no live <img> from a variant',           !str_contains($admin, '<img src=x'));
ok('the markup is present, escaped',         str_contains($admin, '&lt;img src=x'));

echo "\nThe sandbox attribute, as rendered\n";

/**
 * Read off the rendered page, not the template, and compared exactly rather than
 * searched. Both matter: the template's own comment explains why
 * allow-same-origin is forbidden, so a substring search over the source reports
 * the explanation as the offence — and an exact comparison also catches a token
 * nobody thought about being added later, which a search for one known-bad value
 * never would.
 */
$sandbox = preg_match('/<iframe[^>]*\bsandbox="([^"]*)"/i', $admin, $sm) ? trim($sm[1]) : null;
$tokens  = $sandbox === null ? [] : preg_split('/\s+/', $sandbox, -1, PREG_SPLIT_NO_EMPTY);

ok('the frame carries a sandbox attribute',  $sandbox !== null);
ok('it is exactly allow-scripts',            $tokens === ['allow-scripts']);
// Spelled out because this is the single value that would undo everything else:
// a frame with scripts AND same-origin access can reach its own element and
// remove the attribute, which leaves no sandbox at all.
ok('allow-same-origin is not among its tokens', !in_array('allow-same-origin', $tokens, true));

echo "\nThe frame itself\n";

// The frame is the one place the markup is meant to be live, so the assertion
// here is the opposite one: that it does render, and is served as its own page.
$variant = $variants[1];
$scheme = 'dark';
ob_start();
require APP_DIR . '/views/component-frame.php';
$frame = ob_get_clean();

ok('the frame emits the markup unescaped',   str_contains($frame, $nasty));
ok('the frame honours the scheme',           str_contains($frame, 'data-scheme="dark"'));
ok('the frame does not load site.css',       !str_contains($frame, 'site.css'));
ok('the frame does not load admin.css',      !str_contains($frame, 'admin.css'));
// The bridge is what sizes the iframe; without it every preview is 20rem tall.
ok('the frame reports its height',           str_contains($frame, "source: 'component-frame'"));

echo "\nStored links that become an href\n";

foreach ([
    'https://www.figma.com/design/x' => true,
    'http://example.com'             => true,
    'javascript:alert(1)'            => false,
    'JavaScript:alert(1)'            => false,
    'data:text/html,<script>'        => false,
    'vbscript:msgbox'                => false,
    '/admin/settings'                => false,
    ''                               => false,
] as $url => $allowed) {
    ok(
        sprintf('%-32s %s', $url === '' ? '(empty)' : $url, $allowed ? 'allowed' : 'refused'),
        (component_link($url) !== null) === $allowed
    );
}

echo "\nThe list screen\n";

// Rendered in all three of its states, mostly so the warning handler above sees
// them: an undefined variable in a template is invisible in a browser with
// display_errors off, and the page still looks almost right.
$components = [
    ['id' => 7, 'slug' => 'button', 'name' => 'Button', 'group_name' => 'Actions',
     'status' => 'ready', 'summary' => 'The one most often misused.',
     'figma_url' => 'javascript:alert(1)', 'sort_order' => 0, 'updated_at' => '2026-09-27 10:00:00'],
    ['id' => 8, 'slug' => 'card', 'name' => 'Card', 'group_name' => 'Commerce',
     'status' => 'draft', 'summary' => null,
     'figma_url' => null, 'sort_order' => 1, 'updated_at' => '2026-09-27 10:00:00'],
];
$installed = true;

ob_start();
require APP_DIR . '/views/admin/components.php';
$list = ob_get_clean();

ok('the list rendered',                      strlen($list) > 1000);
ok('the name links to the component',        str_contains($list, 'href="/admin/components/7"'));
// A stored javascript: URL must not become a link even in the list.
ok('a javascript: link is not rendered',     !str_contains($list, 'href="javascript:'));

foreach ([['empty', [], true], ['not installed', [], false]] as [$state, $rows, $isInstalled]) {
    $components = $rows;
    $installed  = $isInstalled;
    ob_start();
    require APP_DIR . '/views/admin/components.php';
    $out = ob_get_clean();
    ok('the list renders when ' . $state,    strlen($out) > 200);
}

echo "\nThe history screen\n";

// A hostile variant reaching the history screen through a snapshot is the same
// risk by a longer route, so it is checked on that page too.
$versions = [
    [
        'id' => 9, 'name' => 'Button', 'created_at' => '2026-09-27 11:00:00', 'author' => 'Nathan',
        'snapshot' => json_encode([
            'component' => ['name' => 'Button', 'group_name' => 'Actions', 'status' => 'ready',
                            'summary' => null, 'slug' => 'button', 'figma_url' => null,
                            'base_css' => '.btn{}', 'base_js' => ''],
            'variants'  => [['label' => 'Primary', 'markup' => $nasty, 'variant_css' => '']],
        ]),
    ],
    [
        'id' => 8, 'name' => 'Button', 'created_at' => '2026-09-27 10:00:00', 'author' => 'Nathan',
        'snapshot' => json_encode([
            'component' => ['name' => 'Button', 'group_name' => 'Actions', 'status' => 'draft',
                            'summary' => null, 'slug' => 'button', 'figma_url' => null,
                            'base_css' => '', 'base_js' => ''],
            'variants'  => [],
        ]),
    ],
];

ob_start();
require APP_DIR . '/views/admin/component-versions.php';
$history = ob_get_clean();

ok('the history screen rendered',            strlen($history) > 500);
ok('no live script from a snapshot',         !str_contains($history, '<script>alert(2)'));
ok('no live onerror from a snapshot',        !str_contains($history, 'onerror="alert'));
ok('it names the first save as the first',   str_contains($history, 'First save'));
ok('it describes the newer save',            str_contains($history, 'Added Primary'));
// The oldest entry has nothing to go back to, so it must not offer a restore.
ok('one restore button for two versions',    substr_count($history, '/restore/') === 1);

// A snapshot that is not valid JSON, which a truncated write would produce.
$versions = [['id' => 1, 'name' => 'X', 'created_at' => '2026-09-27 10:00:00',
              'author' => null, 'snapshot' => '{ broken']];
ob_start();
require APP_DIR . '/views/admin/component-versions.php';
$broken = ob_get_clean();
ok('a corrupt snapshot does not break the page', strlen($broken) > 500);

echo "\nReading a Figma link\n";

foreach ([
    // url, expected file key, expected node id
    ['https://www.figma.com/design/AbCdEf123456/Years?node-id=1234-5678', 'AbCdEf123456', '1234-5678'],
    ['https://figma.com/file/AbCdEf123456/Years?node-id=12%3A34',         'AbCdEf123456', '12-34'],
    ['https://www.figma.com/design/AbCdEf123456/Years',                   'AbCdEf123456', null],
    ['https://www.figma.com/design/AbCdEf123456/Years?node-id=oops',      'AbCdEf123456', null],
    ['https://example.com/not-figma',                                     null,           null],
] as [$url, $key, $node]) {
    $got = component_figma_parse($url);
    ok(
        sprintf('%-62s', str_trim_to($url, 62)),
        $got['file_key'] === $key && $got['node_id'] === $node && $got['url'] === $url
    );
}

// A link that is not safe to follow must not be kept at all, or it would be
// rendered as an href on the component page.
$refused = component_figma_parse('javascript:alert(1)');
ok('javascript: is not kept as a link',      $refused['url'] === null);

echo $failed === 0
    ? "\nEvery component safeguard holds.\n"
    : "\n$failed check(s) failed. Do not deploy.\n";
exit($failed === 0 ? 0 : 1);
