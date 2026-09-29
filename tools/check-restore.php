<?php
declare(strict_types=1);

/**
 * Asserts the promises the restore makes.
 *
 * Restoring is the most destructive thing in the admin, and every one of its
 * safeguards is invisible when it fails: a restore that silently dropped version
 * history, or quietly deleted a page written after the backup, would look like a
 * successful restore. So they are asserted rather than reviewed.
 *
 * Needs no database — the writes are recorded rather than made.
 *
 *   php tools/check-restore.php
 */

define('APP_DIR', dirname(__DIR__) . '/app');
define('ROOT_DIR', dirname(__DIR__));

require APP_DIR . '/lib/http.php';
require APP_DIR . '/lib/icons.php';

function setting(string $key, $fallback = '') { return $fallback; }
function asset_find(int $id) { return null; }
function can_edit(): bool { return true; }
function style_value(string $key): string { return ''; }

$SITE = [
    ['id' => 1, 'slug' => 'kept',      'title' => 'Kept',      'updated_at' => '2026-01-01 10:00:00'],
    ['id' => 2, 'slug' => 'edited',    'title' => 'Edited',    'updated_at' => '2026-12-31 10:00:00'],
    ['id' => 3, 'slug' => 'newer',     'title' => 'Newer',     'updated_at' => '2026-12-31 10:00:00'],
];
$WRITES = [];

function db_all(string $sql, array $p = []): array {
    global $SITE;
    if (str_contains($sql, 'FROM pages'))    { return $SITE; }
    if (str_contains($sql, 'FROM settings')) { return [['name' => 'site_name', 'value' => 'Now']]; }
    return [];
}
function db_one(string $sql, array $p = []): ?array {
    global $SITE;
    if (str_contains($sql, 'FROM pages WHERE slug')) {
        foreach ($SITE as $row) { if ($row['slug'] === $p[0]) { return $row; } }
        return null;
    }
    if (str_contains($sql, 'FROM pages WHERE id')) { return ['title' => 'Now', 'blocks' => '[]']; }
    return null;
}
function db_run(string $sql, array $p = []) {
    global $WRITES;
    $verb = strtoupper((string) strtok(trim($sql), " \n"));
    $WRITES[] = $verb . (str_contains($sql, 'users') ? ' users' : (str_contains($sql, 'assets') ? ' assets' : ' pages'));
    return null;
}
function db() { return new class { public function lastInsertId() { return 9; } }; }
function version_record(int $id, string $t, string $b, ?int $u): string {
    global $WRITES; $WRITES[] = 'VERSION ' . $id; return 'created';
}
function setting_save(string $n, string $v): void { global $WRITES; $WRITES[] = 'SETTING'; }

require APP_DIR . '/lib/blocks.php';
require APP_DIR . '/lib/restore.php';

$failed = 0;
function ok(string $what, bool $pass): void
{
    global $failed;
    if (!$pass) { $failed++; }
    printf("  %-58s %s\n", $what, $pass ? 'ok' : 'FAILED');
}

/**
 * Any warning is a failure, and goes to STDERR.
 *
 * Both halves of that are lessons already paid for: display_errors is off on the
 * server, so an "undefined array key" in here would restore a page with its
 * summary silently emptied and say nothing — and a handler that printed to
 * stdout would have its output swallowed by an ob_start() in a later section.
 * The first run of this check found exactly such a bug in restore_apply().
 */
error_reporting(E_ALL);
set_error_handler(function (int $level, string $message, string $file, int $line): bool {
    global $failed;
    $failed++;
    fprintf(STDERR, "  !! %s in %s:%d\n", $message, basename($file), $line);
    return true;
});

echo "\nFiles that are not a usable backup\n";

foreach ([
    ['an empty file',            '',                          'empty'],
    ['something that is not JSON', 'hello',                   'valid JSON'],
    ['JSON that is not a backup', '{"hello":"world"}',        'no pages'],
    ['a page with no slug',       '{"pages":[{"blocks":"[]"}]}', 'no slug'],
    ['a page with no blocks',     '{"pages":[{"slug":"x"}]}',    'no slug'],
] as [$what, $json, $expect]) {
    $read = restore_read($json);
    ok($what . ' is refused', $read['ok'] === false && str_contains($read['error'], $expect));
}

ok('a real backup is accepted', restore_read(json_encode([
    'pages' => [['slug' => 'kept', 'title' => 'Kept', 'blocks' => '[]']],
]))['ok'] === true);

echo "\nWhat a restore plans to do\n";

$payload = [
    'exported_at' => '2026-06-01T00:00:00+00:00',
    'pages' => [
        ['slug' => 'kept',    'title' => 'Kept',    'blocks' => '[]', 'updated_at' => '2026-01-01 10:00:00'],
        ['slug' => 'edited',  'title' => 'Edited',  'blocks' => '[]', 'updated_at' => '2026-06-01 10:00:00'],
        ['slug' => 'restored','title' => 'Restored','blocks' => '[]', 'updated_at' => '2026-06-01 10:00:00'],
    ],
    'settings' => [['name' => 'site_name', 'value' => 'Then']],
    'users'    => [['name' => 'Someone', 'email' => 'a@b.c', 'role' => 'admin']],
    'assets'   => [['id' => 404, 'stored_name' => 'gone.png', 'label' => 'Gone']],
];

$plan = restore_plan($payload);
ok('an existing page is an update, not a create', count($plan['update']) === 2);
ok('a page only in the backup is a create',       count($plan['create']) === 1);
// The one that stops a restore quietly destroying work done since.
ok('a page only on the site is listed as extra',  count($plan['extra']) === 1 && $plan['extra'][0]['slug'] === 'newer');
ok('a page edited since the backup is flagged',
    count(array_filter($plan['update'], fn($p) => !empty($p['edited_since']))) === 1);
ok('a changed setting is listed',                 count($plan['settings']) === 1);
ok('an asset in the backup but not here is named', count($plan['assets_missing_row']) === 1);
ok('people in the backup are counted, not planned', $plan['users'] === 1);

echo "\nWhat a restore actually writes\n";

$WRITES = [];
$done = restore_apply($payload, false, 1);

ok('two pages updated, one created',  $done['updated'] === 2 && $done['created'] === 1);
// Without this a restore is the one action in the admin with no way back.
ok('a version is kept before each page it replaces',
    count(array_filter($WRITES, fn($w) => str_starts_with($w, 'VERSION'))) === 2);
// The whole reason pages are matched by slug rather than wiped and reinserted:
// page_versions cascades on delete, so a DELETE here destroys all history.
ok('no page is deleted',              !in_array('DELETE pages', $WRITES, true));
ok('no user row is written',          count(array_filter($WRITES, fn($w) => str_contains($w, 'users'))) === 0);
ok('no asset row is written',         count(array_filter($WRITES, fn($w) => str_contains($w, 'assets'))) === 0);
ok('a page written since is left alone', $done['removed'] === 0);

$WRITES = [];
$done = restore_apply($payload, true, 1);
ok('and is removed only when asked',  $done['removed'] === 1);

echo "\nContent surviving the round trip\n";

// Blocks go back in through the sanitiser and the shape migration, exactly as a
// save would. A backup taken before a block changed shape has to restore into
// the current one without losing anything.
$blocks = [
    ['type' => 'rich_text', 'data' => ['heading' => 'Hello', 'body' => "One.\n\nTwo."], 'settings' => [], 'options' => []],
    ['type' => 'do_dont', 'data' => ['pairs' => [
        ['stance' => 'do',   'rule' => 'Say hello',     'why' => 'Warmer'],
        ['stance' => 'dont', 'rule' => 'Say greetings', 'why' => ''],
    ]], 'settings' => [], 'options' => []],
    ['type' => 'quote', 'data' => ['text' => 'A line.', 'source' => 'Someone'], 'settings' => [], 'options' => []],
];
$out = sanitise_blocks(block_upgrade($blocks));

ok('every block survives',            count($out) === count($blocks));
ok('text survives',                   ($out[0]['data']['body'] ?? '') === "One.\n\nTwo.");
ok('an old block shape is migrated, not dropped',
    count($out[1]['data']['dos'] ?? []) === 1 && count($out[1]['data']['donts'] ?? []) === 1);
ok('and its text comes with it',      ($out[1]['data']['dos'][0]['text'] ?? '') === 'Say hello');

echo $failed === 0
    ? "\nEvery restore safeguard holds.\n"
    : "\n$failed check(s) failed. Do not deploy.\n";
exit($failed === 0 ? 0 : 1);
