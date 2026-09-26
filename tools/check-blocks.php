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

function check_sample(array $field, string $key)
{
    switch ($field['type']) {
        case 'select':   return (string) array_key_first($field['options']);
        case 'colour':   return $key === 'background' ? '#1B1B1B' : '#7257B7';
        case 'asset':    return ($field['accept'] ?? 'image') === 'video' ? '8' : '7';
        case 'longtext': return "First paragraph.\n\nSecond paragraph.";
        case 'textarea': return 'A short note about this.';
        case 'repeater':
            $rows = [];
            for ($i = 0; $i < 2; $i++) {
                $row = [];
                foreach ($field['fields'] as $subKey => $sub) {
                    $row[$subKey] = check_sample($sub, $subKey);
                }
                $rows[] = $row;
            }
            return $rows;
        default:
            return match ($key) {
                'tag'   => 'logo',
                'url'   => 'https://www.youtube.com/watch?v=aaaaaaaaaaa',
                'email' => 'brand@years.com',
                default => 'Sample ' . str_replace('_', ' ', $key),
            };
    }
}

/* -------------------------------------------------------------------- the run */

$problems = [];

foreach (['empty', 'filled'] as $mode) {
    printf("\n%s\n%s\n", strtoupper($mode), str_repeat('-', 60));

    foreach (block_types() as $type => $def) {
        $data = block_defaults($type);
        if ($mode === 'filled') {
            foreach ($def['fields'] as $key => $field) {
                $data[$key] = check_sample($field, $key);
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
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
if ($problems) {
    echo count($problems) . " problem(s):\n";
    foreach (array_unique($problems) as $problem) {
        echo "  - $problem\n";
    }
    exit(1);
}
echo "Every block renders cleanly, empty and full.\n";
exit(0);
