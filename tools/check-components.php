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

echo $failed === 0
    ? "\nEvery component safeguard holds.\n"
    : "\n$failed check(s) failed. Do not deploy.\n";
exit($failed === 0 ? 0 : 1);
