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
// The component screens render the spec table through partial().
require APP_DIR . '/lib/view.php';

// The settings these views read are database-backed and irrelevant here.
function setting(string $key, $default = null) { return $default; }
function setting_bool(string $key, bool $default = false): bool { return $default; }
function current_user(): ?array { return ['id' => 1, 'role' => 'admin', 'name' => 'Check']; }
function style_css(): string { return ":root { --s-radius: 6px; }"; }
function font_head(): string { return '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=X">'; }

// The appearance and palette tokens the spec matches against. Real shapes, fixed
// values, so a change to the site's own settings cannot make this check fail.
function style_flat(): array
{
    return [
        'radius'  => ['css' => '--s-radius', 'type' => 'number', 'unit' => 'px',  'default' => 6,     'label' => 'Corner radius'],
        'pad'     => ['css' => '--s-pad',    'type' => 'number', 'unit' => 'rem', 'default' => 1.75,  'label' => 'Inside panels'],
        'accent'  => ['css' => '--accent',   'type' => 'colour', 'default' => '#7257B7', 'label' => 'Accent'],
        'ink'     => ['css' => '--ink',      'type' => 'colour', 'default' => '#1F1F1F', 'label' => 'Text'],
    ];
}
function style_value(string $key): string
{
    $field = style_flat()[$key] ?? [];
    return (string) ($field['default'] ?? '');
}
function style_css_value(string $key): string
{
    $field = style_flat()[$key] ?? [];
    return ($field['type'] ?? '') === 'colour'
        ? (string) $field['default']
        : $field['default'] . ($field['unit'] ?? '');
}
function design_colour_tokens(): array { return ['--sand' => '#EFE9DD']; }
// The brand faces the preview frame declares, and the spec measures a pasted
// font-family against. Fixed here so the check does not depend on what happens
// to be in the fonts folder.
function brand_fonts(): array
{
    return ['More Years' => ['/assets/fonts/MoreYears-Regular.woff2' => 400]];
}
function brand_font_faces(): string
{
    return "@font-face{font-family:'More Years';src:url('/assets/fonts/MoreYears-Regular.woff2') format('woff2');font-weight:400}";
}

require APP_DIR . '/lib/blocks.php';
require APP_DIR . '/lib/components.php';
require APP_DIR . '/lib/spec.php';
require APP_DIR . '/lib/import.php';

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
$spec = component_spec($component['base_css'], component_notes($component));
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

echo "\nThe public component page\n";

// Re-established, because the list view above loops `as $component` and a
// template's variables land in this scope — which is exactly how the first run
// of this section ended up rendering the page for the wrong component and
// reporting it as a failing assertion rather than as the muddle it was.
$component = [
    'id' => 7, 'slug' => 'button', 'name' => 'Button', 'group_name' => 'Actions',
    'status' => 'ready', 'summary' => null, 'figma_url' => null,
    'base_css' => '.btn { border-radius: var(--s-radius); }', 'base_js' => '',
    'docs' => '[]', 'notes_override' => '{}', 'sort_order' => 0,
    'updated_at' => '2026-09-27 10:00:00',
];

// Stated rather than inherited from the section above, for the same reason.
$spec = component_spec($component['base_css'], component_notes($component));
$specEditable = null;

// The page a reader sees. It shows the markup twice — live in the frame, and
// escaped in the code panel for copying — and the second is the one that would
// be stored XSS if it were ever written unescaped.
$open = $variants[1];                                  // the hostile one
$docs = [];
function can_edit(): bool { return false; }

ob_start();
require APP_DIR . '/views/component.php';
$public = ob_get_clean();

ok('the component page rendered',            strlen($public) > 1000);
ok('no live script in the code panel',       !str_contains($public, '<script>alert(2)'));
ok('no live onerror in the code panel',      !str_contains($public, 'onerror="alert'));
ok('the markup is offered, escaped',         str_contains($public, '&lt;img src=x'));
ok('every variant has a code panel',         substr_count($public, 'data-variant-code=') === count($variants));
ok('the variant chips are real links',       str_contains($public, 'href="/components/button?v='));

$sandbox = preg_match('/<iframe[^>]*\bsandbox="([^"]*)"/i', $public, $pm) ? trim($pm[1]) : null;
ok('its frame is sandboxed the same way',    $sandbox === 'allow-scripts');

echo "\nThe public index\n";

// Shaped as components_with_variants() leaves them: the variants attached, in
// one query, rather than the view asking per card.
$components = [
    ['id' => 7, 'slug' => 'button', 'name' => 'Button', 'group_name' => 'Actions',
     'status' => 'ready', 'summary' => 'The one most often misused.', 'variants' => $variants],
    ['id' => 8, 'slug' => 'card', 'name' => 'Card', 'group_name' => 'Commerce',
     'status' => 'deprecated', 'summary' => null, 'variants' => []],
];
$groups = components_by_group($components);

ob_start();
require APP_DIR . '/views/components.php';
$index = ob_get_clean();

ok('the index rendered',                     strlen($index) > 500);
ok('a card links to its component',          str_contains($index, 'href="/components/button"'));
ok('a thumbnail is a sandboxed frame',       str_contains($index, 'sandbox="allow-scripts"'));
ok('a thumbnail is lazy',                    str_contains($index, 'loading="lazy"'));
ok('a component with no variants says so',   str_contains($index, 'Nothing to show yet'));
ok('deprecated is labelled',                 str_contains($index, 'Deprecated'));

$components = [];
$groups = [];
ob_start();
require APP_DIR . '/views/components.php';
ok('the index renders when empty',           strlen(ob_get_clean()) > 200);

echo "\nThe derived spec\n";

$specCss = <<<'CSS'
/* a comment, which must not be read as a declaration */
.btn {
  border-radius: 6px;
  padding: 1.75rem;
  background: #7257B7;
  color: #fff;
  font-size: 0.9375rem;
  border: 1px solid var(--rule);
  gap: 13px;
  margin: 0 auto;
}
.btn--sand { background: #EFE9DD; border-radius: var(--s-radius); }
.btn:hover { background: #4A3382; }
@media (max-width: 30rem) { .btn { padding: 1rem; } }
CSS;

$derived = component_spec($specCss, ['deadbeef0000' => 'a note']);
$rows = [];
foreach ($derived['groups'] as $key => $group) {
    foreach ($group['rows'] as $row) {
        $rows[$row['property'] . ':' . $row['value']] = $row;
    }
}
$row = function (string $k) use ($rows): array { return $rows[$k] ?? []; };

ok('a comment is not read as a declaration', !isset($rows['a comment, which must not be read as a declaration:']));
ok('6px is recognised as --s-radius',         ($row('border-radius:6px')['token'] ?? null) === '--s-radius');
ok('1.75rem is recognised as --s-pad',        ($row('padding:1.75rem')['token'] ?? null) === '--s-pad');
ok('the accent hex is recognised',            ($row('background:#7257B7')['token'] ?? null) === '--accent');
ok('a palette colour is recognised',          ($row('background:#EFE9DD')['token'] ?? null) === '--sand');
ok('case does not matter for a hex',          ($row('background:#EFE9DD')['flagged'] ?? true) === false);
ok('#fff is flagged',                         ($row('color:#fff')['flagged'] ?? false) === true);
ok('13px is flagged',                         ($row('gap:13px')['flagged'] ?? false) === true);
ok('a rule inside a media query is read',     isset($rows['padding:1rem']));
ok('var() on its own is the token',           ($row('border-radius:var(--s-radius)')['token'] ?? null) === '--s-radius');
// The distinction that makes the table worth reading: a token inside a shorthand
// is not the same as the declaration being one, because the 1px is hardcoded.
ok('var() inside shorthand is only partial',  ($row('border:1px solid var(--rule)')['partial'] ?? false) === true);
ok('a partial is not claimed as on-system',   ($row('border:1px solid var(--rule)')['flagged'] ?? true) === false);
// Shorthand this does not take apart must not be reported as off-system, or the
// table is reporting its own limits as findings.
ok('"0 auto" is not flagged',                 ($row('margin:0 auto')['flagged'] ?? true) === false);
ok('hover is found',                          in_array('Hover', $derived['states'], true));
ok('the width query is found',                in_array('Responds to width', $derived['states'], true));
ok('missing focus is not claimed as present', !in_array('Keyboard focus', $derived['states'], true));
ok('empty CSS gives an empty spec',           component_spec('')['groups'] === []);

// Normalising, which is what lets three spellings of one colour match.
ok('#FFF and #ffffff normalise alike',        spec_normalise_colour('#FFF') === spec_normalise_colour('#ffffff'));
ok('an alpha channel is ignored',             spec_normalise_colour('#7257B7FF') === '#7257b7');
ok('0, 0px and 0rem normalise alike',         spec_normalise_length('0px') === spec_normalise_length('0rem'));
ok('px is not matched against rem',           spec_normalise_length('6px') !== spec_normalise_length('6rem'));

echo "\nTypefaces a pasted component asks for\n";

// The single likeliest reason an imported component is "nearly right": the face
// is not on the server, so it renders in a fallback and stops matching.
$fonts = component_spec(".a{font-family:'More Years',serif}.b{font-family:Inter,sans-serif}.c{font-family:system-ui}");
$byValue = [];
foreach ($fonts['groups'] as $group) {
    foreach ($group['rows'] as $r) { $byValue[$r['value']] = $r; }
}
ok('a face on the server is not flagged',
    ($byValue["'More Years',serif"]['flagged'] ?? true) === false);
ok('a face that is not is flagged',
    ($byValue['Inter,sans-serif']['flagged'] ?? false) === true);
ok('and is named as missing, not as off-token',
    ($byValue['Inter,sans-serif']['missing_font'] ?? false) === true);
ok('a generic family counts as available',
    ($byValue['system-ui']['flagged'] ?? true) === false);
ok('only the first family in a stack is judged',
    spec_font_is_available('Inter, "More Years", sans-serif') === false);

ob_start();
$spec = $fonts;
$specEditable = null;
require APP_DIR . '/views/component-spec.php';
$fontTable = ob_get_clean();
ok('the table says a font is missing',       str_contains($fontTable, 'not on the server'));

echo "\nThe spec table as rendered\n";

// A note, and a selector, are both author text and both reach this page.
$spec = component_spec('.btn { border-radius: 6px; content: "x"; }', []);
$specEditable = null;
ob_start();
require APP_DIR . '/views/component-spec.php';
$table = ob_get_clean();
ok('the spec table rendered',                strlen($table) > 200);
ok('it carries the honesty line',            str_contains($table, 'does not work out which rule wins'));

// Hostile text in a selector must not escape into the page.
$spec = component_spec('.btn<script>alert(3)</script> { border-radius: 9px; }', []);
ob_start();
require APP_DIR . '/views/component-spec.php';
$table = ob_get_clean();
ok('a hostile selector is escaped',          !str_contains($table, '<script>alert(3)'));

// And in a written note, which is the one field a person types freely.
$spec = component_spec('.btn { border-radius: 9px; }', []);
foreach ($spec['groups'] as $k => $g) {
    foreach ($g['rows'] as $i => $r) {
        $spec['groups'][$k]['rows'][$i]['note'] = '<script>alert(4)</script>';
    }
}
ob_start();
require APP_DIR . '/views/component-spec.php';
$table = ob_get_clean();
ok('a hostile note is escaped',              !str_contains($table, '<script>alert(4)'));

$specEditable = '/admin/components/7/notes';
ob_start();
require APP_DIR . '/views/component-spec.php';
$editable = ob_get_clean();
ok('the editable table offers a field',      str_contains($editable, 'name="note['));
ok('and it carries a CSRF token',            str_contains($editable, 'name="_csrf"'));
ok('a hostile note is escaped in the field', !str_contains($editable, '<script>alert(4)'));
$specEditable = null;

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

echo "\nWorking out what was pasted\n";

$pastes = [
    // label, text, expected kind, expects markup, expects css
    ['Figma\'s clipboard', '<meta charset="utf-8"><span data-metadata="<!--(figmeta)eyJ(/figmeta)-->"></span>'
        . '<span data-buffer="<!--(figma)AAA(/figma)-->"></span>', 'figma-clipboard', false, false],
    ['a node link',    'https://www.figma.com/design/AbCdEf123456/Years?node-id=1234-5678', 'figma-link', false, false],
    ['a file link',    'https://www.figma.com/design/AbCdEf123456/Years',                   'figma-link', false, false],
    ['some other link', 'https://example.com/thing',                                        'figma-link', false, false],
    ['an SVG',         '<svg viewBox="0 0 24 24"><path d="M4 4h16"/></svg>',                'svg',        true,  false],
    ['Dev Mode code',  "<button class=\"btn\">Buy</button>\n<style>.btn { background: #7257B7; }</style>", 'devmode', true, true],
    ['markup then CSS', "<button class=\"btn\">Buy</button>\n\n.btn { background: #7257B7; }", 'devmode',  true,  true],
    ['markup alone',   '<button class="btn">Buy</button>',                                  'html',       true,  false],
    ['CSS alone',      '.btn { background: #7257B7; border-radius: 6px; }',                 'css',        false, true],
    ['prose',          'make the button purple please',                                     'unknown',    false, false],
    ['nothing',        '   ',                                                               'empty',      false, false],
];

foreach ($pastes as [$label, $text, $kind, $wantsMarkup, $wantsCss]) {
    $got = component_import_detect($text);
    ok(
        sprintf('%-18s reads as %s', $label, $kind),
        $got['kind'] === $kind
        && ($got['markup'] !== '') === $wantsMarkup
        && ($got['css'] !== '') === $wantsCss
    );
}

// The one that has to fail loudly rather than quietly, because it is the obvious
// thing to try and a blank result would read as the feature being broken.
$clip = component_import_detect('<span data-buffer="<!--(figma)AAA(/figma)-->"></span>');
ok('the binary clipboard is refused',        $clip['refused'] === true);
ok('and it says what to paste instead',      str_contains($clip['note'], 'Dev Mode'));

// A link that cannot be followed must not be stored, since it becomes an href.
$bad = component_import_detect('javascript:alert(1)');
ok('javascript: is not taken as a link',     ($bad['figma']['url'] ?? null) === null);

$node = component_import_detect('https://www.figma.com/design/AbCdEf123456/Years?node-id=1234-5678');
ok('the node is read from a link',           ($node['figma']['node_id'] ?? null) === '1234-5678');
$file = component_import_detect('https://www.figma.com/design/AbCdEf123456/Years');
ok('a whole-file link says so',              str_contains($file['note'], 'Copy link to selection'));

echo "\nThe import review screen\n";

$found = component_import_detect("<button class=\"btn\">$nasty</button>\n<style>.btn { border-radius: 9px; }</style>");
$spec  = component_spec($found['css'], []);
$variants = [['id' => 2, 'label' => 'Primary']];

ob_start();
require APP_DIR . '/views/admin/component-import.php';
$review = ob_get_clean();

ok('the review screen rendered',             strlen($review) > 800);
ok('it says nothing has been saved',         str_contains($review, 'Nothing has been saved yet'));
ok('the pasted markup is escaped',           !str_contains($review, '<script>alert(2)'));
ok('and no live img either',                 !str_contains($review, '<img src=x'));
ok('it offers a variant to land in',         str_contains($review, 'name="variant"'));
ok('accepting is a separate post',           str_contains($review, '/import/apply'));
ok('it carries a CSRF token',                str_contains($review, 'name="_csrf"'));

// The refusal path must offer no way to accept anything.
$found = component_import_detect('<span data-buffer="<!--(figma)AAA(/figma)-->"></span>');
$spec  = component_spec('', []);
ob_start();
require APP_DIR . '/views/admin/component-import.php';
$refusal = ob_get_clean();
ok('a refusal offers no Accept',             !str_contains($refusal, 'Accept<'));
ok('and explains itself',                    str_contains($refusal, 'internal format'));

echo "\nThe guidance editor\n";

// It reuses the page editor, which needs its configuration in data attributes and
// a #page-settings-template to show when no block is selected — without that the
// inspector throws on first render and the screen is unusable.
//
// $component is stated again because the index view above loops `as $component`,
// and a template's variables land in this scope. The first run of this section
// asserted against the last card on the index instead, which is the third time
// that has happened in this file and the reason it is written up in the traps.
$component = ['id' => 7, 'slug' => 'button', 'name' => 'Button', 'docs' => '[]'];
$blocks = [];
ob_start();
require APP_DIR . '/views/admin/component-docs.php';
$docsEditor = ob_get_clean();

ok('the guidance editor rendered',           strlen($docsEditor) > 1000);
ok('it saves to its own endpoint',           str_contains($docsEditor, 'data-save-url="/admin/components/7/docs/save"'));
ok('its canvas is its own preview',          str_contains($docsEditor, '/admin/components/7/docs/preview?edit=1'));
ok('it carries the block schema',            str_contains($docsEditor, 'data-schema='));
// editor.js requires this template; without it the inspector throws.
ok('the inspector has something to show',    str_contains($docsEditor, 'id="page-settings-template"'));

echo "\nComponents in search\n";

/**
 * A stubbed database, so the index can be built without one. $DB is what the
 * next query answers with; the queries are told apart by what they select, which
 * is crude and enough — this file is the only caller.
 */
$DB = ['pages' => ['n' => 3, 'last' => '2026-09-27 10:00:00'],
       'components' => ['n' => 2, 'last' => '2026-09-27 10:00:00']];

function db_one(string $sql, array $params = []): ?array
{
    global $DB;
    if (str_contains($sql, 'FROM components')) { return $DB['components']; }
    if (str_contains($sql, 'FROM pages'))      { return $DB['pages']; }
    return ['id' => 1];
}
function db_all(string $sql, array $params = []): array
{
    if (str_contains($sql, 'FROM component_variants')) {
        return [['component_id' => 7, 'label' => 'Primary'], ['component_id' => 7, 'label' => 'Disabled']];
    }
    if (str_contains($sql, 'FROM components')) {
        return [[
            'id' => 7, 'slug' => 'button', 'name' => 'Button', 'group_name' => 'Actions',
            'status' => 'ready', 'summary' => 'The one most often misused.',
            'docs' => json_encode([[
                'type' => 'rich_text',
                'data' => ['heading' => 'When not to use it', 'body' => 'Never two on one screen.'],
                'settings' => ['anchor' => 'when-not'],
            ]]),
        ]];
    }
    return [];                       // no pages, and no palette colours
}
// components_installed() is the real one: it asks db_one for a row, which the
// stub above answers, so it reports the tables as present without a stub of
// its own. Redeclaring it would be a fatal error anyway.

require APP_DIR . '/lib/search.php';

$entries = search_entries(false);
$component = $entries[0] ?? [];

ok('a component is in the index',            ($component['kind'] ?? null) === 'component');
ok('it is found by its name',                str_contains($component['text'] ?? '', 'button'));
ok('and by a variant label',                 str_contains($component['text'] ?? '', 'disabled'));
ok('and by its written guidance',            str_contains($component['text'] ?? '', 'never two on one screen'));
ok('a heading in the guidance is its own result',
    ($component['marks'][0]['title'] ?? '') === 'When not to use it');
ok('the heading keeps its anchor',            ($component['marks'][0]['anchor'] ?? '') === 'when-not');
// The markup and CSS are deliberately out: searching "button" should not match
// every component whose stylesheet happens to mention one.
ok('the CSS is not indexed',                 !str_contains($component['text'] ?? '', 'border-radius'));

// The one that would fail silently: keyed on pages alone, a component edit would
// serve a stale index for as long as nobody touched a page, and nothing would
// look wrong.
$before = search_cache_path(false);
$DB['components']['last'] = '2026-09-27 11:00:00';
$after = search_cache_path(false);
ok('editing a component changes the cache key', $before !== $after);

$DB['components']['last'] = '2026-09-27 10:00:00';
ok('and changes back when nothing has',      search_cache_path(false) === $before);
ok('an editor gets a different index',       search_cache_path(true) !== search_cache_path(false));

echo $failed === 0
    ? "\nEvery component safeguard holds.\n"
    : "\n$failed check(s) failed. Do not deploy.\n";
exit($failed === 0 ? 0 : 1);
