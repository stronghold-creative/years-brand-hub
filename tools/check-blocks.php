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
