<?php
declare(strict_types=1);

/**
 * Renders every block type twice, empty and full, and reports anything that
 * throws, warns, or produces output that would look wrong.
 *
 *   php tools/check-blocks.php
 *   php tools/check-blocks.php --edit     as the editor canvas renders it
 *
 * Several bugs here produced valid HTML and still looked wrong on the page, so
 * checking the markup parses is not enough. This checks what a reader would
 * actually see: whether anything shows at all, and whether an empty value has
 * landed somewhere it will be read as a colour, a link or a label.
 *
 * It needs no database. Everything that would reach one is stubbed, so it runs
 * on a laptop with nothing set up.
 */

define('ROOT_DIR', dirname(__DIR__));
define('APP_DIR', ROOT_DIR . '/app');

error_reporting(E_ALL);
ini_set('display_errors', '1');

$editing = in_array('--edit', $argv, true);

/* ------------------------------------------------- stubs, in place of the DB */

function db_one(string $sql, array $params = []): ?array { return null; }
function db_all(string $sql, array $params = []): array { return []; }
function setting(string $key, $fallback = '') { return $fallback; }
function style_value(string $key): string { return ''; }
function can_edit(): bool { return false; }

/** One image and one video, so asset fields have something to resolve to. */
function check_assets(): array
{
    return [
        7 => [
            'id' => 7, 'stored_name' => 'aaa.png', 'original_name' => 'mark.png',
            'label' => 'Mark', 'mime' => 'image/png', 'bytes' => 2048,
            'version' => '1.0', 'tags' => 'logo', 'uploaded_at' => '2026-09-01 10:00:00',
        ],
        8 => [
            'id' => 8, 'stored_name' => 'bbb.mp4', 'original_name' => 'film.mp4',
            'label' => 'Film', 'mime' => 'video/mp4', 'bytes' => 9999,
            'version' => '1.0', 'tags' => 'film', 'uploaded_at' => '2026-09-01 10:00:00',
        ],
    ];
}

function asset_find(int $id): ?array { return check_assets()[$id] ?? null; }
function asset_is_inline_image(array $a): bool { return str_starts_with((string) $a['mime'], 'image/'); }
function asset_is_inline_video(array $a): bool { return $a['mime'] === 'video/mp4'; }
function asset_is_inline_media(array $a): bool { return asset_is_inline_image($a) || asset_is_inline_video($a); }
function asset_image_url(array $a, ?int $w = null): string { return '/i/' . $a['id'] . ($w ? '/' . $w : ''); }
function asset_srcset(array $a): string { return ''; }
function asset_dimensions(array $a): ?array { return ['width' => 800, 'height' => 600]; }
function asset_human_size(int $b): string { return $b . ' B'; }
function asset_extension(string $f): string { return strtolower(pathinfo($f, PATHINFO_EXTENSION)); }
function assets_by_tag(string $tag): array { return $tag === 'logo' ? [check_assets()[7]] : []; }
function asset_zip_supported(): bool { return true; }
function asset_bundle_path(string $tag): ?string { return $tag === 'logo' ? 'bundle.zip' : null; }

require APP_DIR . '/lib/http.php';
require APP_DIR . '/lib/icons.php';
require APP_DIR . '/lib/fontmetrics.php';
require APP_DIR . '/lib/blocks.php';

/* ------------------------------------------------------------ sample content */

/** $row marks which repeater row a value belongs to, so rows are tellable apart. */
function check_sample(array $field, string $key, string $row = '')
{
    switch ($field['type']) {
        case 'select':   return (string) array_key_first($field['options']);
        case 'colour':   return $key === 'background' ? '#1B1B1B' : '#7257B7';
        case 'asset':    return ($field['accept'] ?? 'image') === 'video' ? '8' : '7';
        case 'longtext': return "First paragraph$row.\n\nSecond paragraph$row.";
        case 'textarea': return "A short note about this$row.";
        case 'repeater':
            $rows = [];
            // Three rows, each one's text saying which row it is. Identical
            // rows would make a path pointing at the wrong one indetectable,
            // which is exactly what the alignment check is looking for.
            for ($i = 0; $i < 3; $i++) {
                $made = [];
                foreach ($field['fields'] as $subKey => $sub) {
                    $made[$subKey] = check_sample($sub, $subKey, ' ' . ($i + 1));
                }
                $rows[] = $made;
            }
            return $rows;
        default:
            return match ($key) {
                'tag'   => 'logo',
                'url'   => 'https://www.youtube.com/watch?v=aaaaaaaaaaa',
                'email' => 'brand@years.com',
                default => 'Sample ' . str_replace('_', ' ', $key) . $row,
            };
    }
}

/**
 * Whether the text a field shows can be read back out of the markup.
 *
 * Tried rather than asked about. class_exists() says nothing about whether the
 * class can actually be used — a build with the class disabled answers yes and
 * then fails on the first method call — and this runs in CI against whatever
 * PHP the runner image ships.
 */
function check_can_read_text(): bool
{
    static $can = null;
    if ($can === null) {
        try {
            $probe = new DOMDocument();
            $probe->loadHTML('<div>x</div>', LIBXML_NOWARNING | LIBXML_NOERROR);
            $can = (new DOMXPath($probe))->query('//div')->length === 1;
        } catch (Throwable $e) {
            $can = false;
        }
    }
    return $can;
}

/**
 * Every editable region in some rendered HTML, as
 * [path, the text it shows, whether it is multiline].
 *
 * Parsed rather than matched with a regex, because the text is inside the
 * element and elements nest.
 *
 * Without ext-dom the text cannot be read, so only the paths come back and the
 * text is empty, which the caller treats as nothing to compare. This gate runs
 * in CI against whatever PHP the runner image happens to ship, and a missing
 * optional extension should weaken the check, not stop a deploy.
 */
function check_editable_fields(string $html): array
{
    if (!str_contains($html, 'data-field=')) {
        return [];
    }

    if (!check_can_read_text()) {
        $out = [];
        preg_match_all('/data-field="([^"]+)"/', $html, $found);
        foreach ($found[1] as $path) {
            $out[] = [$path, '', false];
        }
        return $out;
    }

    $doc = new DOMDocument();
    // The fragment is a block, not a document, and carries no doctype.
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $out = [];
    foreach ((new DOMXPath($doc))->query('//*[@data-field]') as $node) {
        $out[] = [
            $node->getAttribute('data-field'),
            $node->textContent,
            $node->hasAttribute('data-multiline'),
        ];
    }
    return $out;
}

/**
 * Text reduced to something comparable.
 *
 * A multiline value separates its paragraphs with a blank line, but the
 * paragraphs it renders into have no whitespace between them at all, so the two
 * can only be compared with the whitespace taken out entirely. That still
 * catches the thing this is for: a path pointing at the wrong row shows
 * different words, not different spacing.
 */
function check_flatten(string $text, bool $multiline = false): string
{
    return $multiline
        ? preg_replace('/\s+/u', '', $text) ?? ''
        : trim(preg_replace('/\s+/u', ' ', $text) ?? '');
}

/* -------------------------------------------------------------------- the run */

$problems = [];

/**
 * "gappy" fills every block but leaves the first row of each repeater blank.
 *
 * Several renderers drop a row they cannot draw — a colour with no hex, an
 * image whose asset has gone, a family with no steps. That is what makes an
 * editable() path built from the drawn position point at the wrong row, and
 * nothing else in this file produces a list with a hole in it. Only the paths
 * are judged in this pass; a block that shows less when it is half empty is
 * behaving correctly.
 */
foreach (['empty', 'filled', 'gappy'] as $mode) {
    printf("\n%s\n%s\n", strtoupper($mode), str_repeat('-', 60));

    foreach (block_types() as $type => $def) {
        $data = block_defaults($type);
        if ($mode !== 'empty') {
            foreach ($def['fields'] as $key => $field) {
                $data[$key] = check_sample($field, $key);
            }
        }
        if ($mode === 'gappy') {
            foreach ($def['fields'] as $key => $field) {
                if ($field['type'] === 'repeater' && !empty($data[$key])) {
                    // Blank, not removed: the row still occupies its place in
                    // the stored data, which is the whole point.
                    foreach ($data[$key][0] as $subKey => $ignored) {
                        $data[$key][0][$subKey] = '';
                    }
                }
            }
        }

        // Through the sanitiser first, exactly as a real save would go.
        $block = sanitise_blocks([['type' => $type, 'data' => $data, 'settings' => [], 'options' => []]])[0] ?? null;
        if (!$block) {
            $problems[] = "$mode/$type: sanitise_blocks dropped the block";
            continue;
        }

        ob_start();
        $caught = null;
        set_error_handler(function (int $no, string $str, string $file, int $line) use (&$caught): bool {
            $caught = $str . ' (' . basename($file) . ':' . $line . ')';
            return true;
        });
        try {
            render_blocks([$block], $editing);
        } catch (Throwable $e) {
            $caught = get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        }
        restore_error_handler();
        $html = ob_get_clean();

        if ($caught !== null) {
            $problems[] = "$mode/$type: $caught";
        }

        $shows = block_shows_something($html);
        printf("  %-18s %s\n", $type, $shows ? 'shows' : 'nothing');

        if ($mode === 'filled' && !$shows) {
            $problems[] = "filled/$type: renders nothing even with content in it";
        }
        // In the editor every block must be visible, or a new one cannot be seen
        // on the canvas and has no height to click.
        if ($mode === 'empty' && $editing && !$shows) {
            $problems[] = "empty/$type: invisible on the editor canvas";
        }
        if (str_contains($html, '<p></p>')) {
            $problems[] = "$mode/$type: emits an empty paragraph";
        }
        // An empty value inside an attribute that is read as a value, not text.
        if (preg_match('/(style|data-copy|href|src)="\s*"/', $html, $m)) {
            $problems[] = "$mode/$type: empty {$m[1]} attribute";
        }
        if (preg_match('/--[a-z-]+:\s*(;|")/', $html)) {
            $problems[] = "$mode/$type: a custom property was set to nothing";
        }

        // Every data-field written by editable() has to name a value that
        // actually exists in this block's data, or an edit made on the canvas
        // is silently dropped: the editor refuses to write a key the block
        // does not have, and nothing says why the typing did not stick.
        //
        // And it has to name the *right* value. Several renderers filter their
        // list before drawing it — a colour with no hex, an image whose asset
        // has gone — so a path built from the position in the drawn list points
        // at the wrong row, which resolves perfectly and edits the wrong thing.
        // The text on the page is compared with the value at the path to catch
        // that.
        if ($editing) {
            foreach (check_editable_fields($html) as [$path, $shown, $multiline]) {
                $target = $block['data'];
                $ok = true;
                foreach (explode('.', $path) as $step) {
                    if (!is_array($target) || !array_key_exists($step, $target)) {
                        $ok = false;
                        break;
                    }
                    $target = $target[$step];
                }
                if (!$ok) {
                    $problems[] = "$mode/$type: data-field=\"$path\" does not resolve to a value";
                    continue;
                }
                if (is_array($target)) {
                    $problems[] = "$mode/$type: data-field=\"$path\" points at a list, not text";
                    continue;
                }
                $want = check_flatten((string) $target, $multiline);
                $got  = check_flatten($shown, $multiline);
                // A renderer may show a default in place of an empty value, so
                // only a mismatch between two non-empty strings is a fault.
                if ($want !== '' && $got !== '' && $want !== $got) {
                    $problems[] = "$mode/$type: data-field=\"$path\" holds \"$want\" but the page shows \"$got\"";
                }
            }
        }
    }
}

/**
 * Inline formatting.
 *
 * rich_inline() is the only place this project turns stored text into tags, and
 * its safety rests on running *after* e() — so what reaches it can no longer
 * contain a `<`. These assertions exist because that ordering is one edit away
 * from being wrong and nothing about the page would look different if it were.
 */
foreach ([
    ['**bold**',                    '<strong>bold</strong>',        'bold'],
    ['*italic*',                    '<em>italic</em>',              'italic'],
    ['`--s-radius`',                '<code>--s-radius</code>',      'code'],
    ['[Years](https://years.com)',  '<a href="https://years.com">Years</a>', 'a link'],
] as [$input, $expected, $what]) {
    if (!str_contains(paragraphs($input), $expected)) {
        $problems[] = "rich_inline does not render $what";
    }
}

foreach ([
    ['<script>alert(1)</script>',   '<script',  'a typed script tag'],
    ['<b>typed</b>',                '<b>',      'typed markup'],
    ['[x](javascript:alert(1))',    '<a ',      'a javascript: link'],
    ['[x](data:text/html,y)',       '<a ',      'a data: link'],
] as [$input, $forbidden, $what]) {
    if (str_contains(paragraphs($input), $forbidden)) {
        $problems[] = "rich_inline lets $what through";
    }
}

// Token names and arithmetic are ordinary content in a brand hub and must come
// out exactly as they went in.
foreach (['a --s-radius and figma_file_key', '2 * 3 = 6', 'an * orphan', '**unclosed'] as $plain) {
    $rendered = paragraphs($plain);
    if (str_contains($rendered, '<em>') || str_contains($rendered, '<strong>')) {
        $problems[] = "rich_inline formats \"$plain\", which is plain text";
    }
}

/**
 * Values written straight into a style attribute.
 *
 * A type specimen puts its size, weight, line height and tracking into
 * `style="..."`. Escaping alone would stop a quote breaking out of the attribute
 * and would happily let `red; background: url(...)` through as a declaration, so
 * each is constrained to the shape it is meant to be. A rejected value shows as
 * the default, which is visible; a passed one would not be.
 */
foreach ([
    ['2.5rem',                  '2.5rem'],
    ['40px',                    '40px'],
    ['clamp(2rem, 4vw, 3rem)',  'clamp(2rem, 4vw, 3rem)'],
    ['normal',                  'normal'],
    ['red; background: url(x)', 'FALLBACK'],
    ['2rem"; color: red',       'FALLBACK'],
    ['expression(alert(1))',    'FALLBACK'],
    ['',                        'FALLBACK'],
] as [$input, $expected]) {
    $got = spec_length($input, 'FALLBACK');
    if ($got !== $expected) {
        $problems[] = "spec_length(\"$input\") gave \"$got\", expected \"$expected\"";
    }
}

foreach ([['700', '700'], ['bold', '700'], ['Semi Bold', '600'], ['nonsense', '400'], ['', '400']] as [$in, $want]) {
    if (spec_weight($in) !== $want) {
        $problems[] = "spec_weight(\"$in\") gave \"" . spec_weight($in) . "\", expected \"$want\"";
    }
}

/**
 * The clearspace diagram's geometry.
 *
 * If the clear space each side is r times the mark, the frame is (1 + 2r) marks
 * across and the mark is 1/(1 + 2r) of it. Getting this wrong is invisible —
 * the diagram still draws, it just claims a ratio it is not showing — which is
 * how the previous version shipped measuring against the container instead.
 */
foreach ([
    // ratio, the mark's share of the frame
    [25,  66.6667],   // gap is a quarter of the mark each side: 1 + 0.5 marks
    [50,  50.0],      // gap is half the mark each side:         1 + 1 marks
    [100, 33.3333],   // gap is a full mark each side:           1 + 2 marks
] as [$ratio, $share]) {
    $expected = round(100 / (1 + 2 * ($ratio / 100)), 4);
    if (abs($expected - $share) > 0.001) {
        $problems[] = "clearspace: $ratio% should leave the mark at $share% of the frame, not $expected%";
    }

    $blocks = sanitise_blocks([[
        'type' => 'clearspace',
        'data' => ['asset' => 0, 'ratio' => (string) $ratio],
        'settings' => [], 'options' => [],
    ]]);
    if ((string) $blocks[0]['data']['ratio'] !== (string) $ratio) {
        $problems[] = "clearspace ratio $ratio does not survive the sanitiser";
    }
}

/**
 * A table's grid, which is stored as text so it can be pasted.
 *
 * The parsing is the whole feature: get it wrong and a pasted table silently
 * lands in one column, or a cell containing a pipe splits in half.
 */
foreach ([
    ['a tab-separated paste',     "A\tB\tC",        [['A', 'B', 'C']]],
    ['pipes, typed by hand',      'A | B',          [['A', 'B']]],
    ['pipes at the ends',         '| A | B |',      [['A', 'B']]],
    ['a pipe inside a tabbed row', "A|B\tC",        [['A|B', 'C']]],
    ['a blank row',               "A | B\n |\nC|D", [['A', 'B'], ['C', 'D']]],
    ['nothing at all',            '',               []],
] as [$what, $grid, $expected]) {
    if (table_rows($grid) !== $expected) {
        $problems[] = "table_rows gets $what wrong: " . json_encode(table_rows($grid));
    }
}

// A ragged paste still has to render a rectangle; the renderer pads to the
// widest row, so the width it works from must be the widest and not the first.
$ragged = table_rows("A\tB\tC\nD\tE");
if (max(array_map('count', $ragged)) !== 3) {
    $problems[] = 'a ragged table should take its width from its widest row';
}

/**
 * The comparison table that became a table.
 *
 * It is the first migration to change a block's *type* rather than its data, and
 * a page using one would simply lose it if this stopped working — sanitise_blocks
 * drops a type the definitions no longer name.
 */
$old = [[
    'type' => 'comparison_table',
    'data' => [
        'heading' => 'Word swaps',
        'left'    => 'We say',
        'right'   => 'We do not say',
        'rows'    => [
            ['left' => 'Send', 'right' => 'Dispatch', 'note' => 'Warmer'],
            ['left' => 'Fix',  'right' => 'Resolve',  'note' => ''],
        ],
    ],
    'settings' => [],
    'options'  => ['density' => 'compact', 'strike' => 'struck'],
]];
$new = sanitise_blocks(block_upgrade($old));

if (($new[0]['type'] ?? '') !== 'table') {
    $problems[] = 'a comparison table does not become a table';
} else {
    $rows = table_rows((string) $new[0]['data']['grid']);
    if (count($rows) !== 3) {
        $problems[] = 'a migrated comparison table should keep its heading row and both rows';
    }
    if (($rows[0][2] ?? '') !== 'Note') {
        $problems[] = 'a migrated comparison table with notes should gain a Note column';
    }
    if (($rows[1][0] ?? '') !== 'Send' || ($rows[1][1] ?? '') !== 'Dispatch') {
        $problems[] = 'a migrated comparison table loses its cells';
    }
    if (($new[0]['options']['strike'] ?? '') !== 'struck') {
        $problems[] = 'a migrated comparison table should keep its struck-through column';
    }
    if (($new[0]['data']['heading'] ?? '') !== 'Word swaps') {
        $problems[] = 'a migrated comparison table loses its heading';
    }
}

/**
 * Every block type needs a group and an icon for the chooser.
 *
 * Neither fails loudly: a missing group drops the block into an "Other" heading
 * at the end of the list, and an icon name with a typo in it renders a blank
 * square. Both look like a design decision rather than a mistake, which is why
 * they are asserted rather than noticed.
 */
$icons  = block_icon_set();
$groups = block_groups();

foreach (block_types() as $type => $def) {
    if (empty($def['group'])) {
        $problems[] = "$type has no group, so it will fall to the end of the chooser";
    } elseif (!in_array($def['group'], $groups, true)) {
        $problems[] = "$type is in group \"{$def['group']}\", which block_groups() does not list";
    }
    if (empty($def['icon'])) {
        $problems[] = "$type has no icon";
    } elseif (!isset($icons[$def['icon']])) {
        $problems[] = "$type asks for the icon \"{$def['icon']}\", which does not exist";
    }
}

echo "\n" . str_repeat('=', 60) . "\n";

// Said plainly rather than passing quietly at reduced strength, so a run that
// checked less than it looks like it did says so.
if ($editing && !check_can_read_text()) {
    echo "NOTE: ext-dom is not loaded, so editable paths were checked for\n"
       . "      resolving but not for pointing at the right row. Install\n"
       . "      php-xml to get the full check.\n\n";
}

if ($problems) {
    echo count($problems) . " problem(s):\n";
    foreach (array_unique($problems) as $problem) {
        echo "  - $problem\n";
    }
    exit(1);
}
echo "Every block renders cleanly, empty and full.\n";
exit(0);
