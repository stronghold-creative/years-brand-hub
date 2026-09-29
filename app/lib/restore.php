<?php
declare(strict_types=1);

/**
 * Restoring from a backup file.
 *
 * The export has existed since the beginning and there was no way back in, which
 * makes it a file that looks like insurance without being any. This is the way
 * back in.
 *
 * Four decisions shape the whole thing, and each is there to stop a restore
 * doing more damage than whatever prompted it:
 *
 *   1. PAGES ARE MATCHED BY SLUG AND UPDATED IN PLACE, never deleted and
 *      reinserted. `page_versions` has ON DELETE CASCADE on its page, so a
 *      delete-and-reinsert restore would silently destroy every page's entire
 *      history — including the versions from before the backup, which are the
 *      thing most likely to be wanted next. Updating in place also keeps each
 *      page's id, so covers and links still point at it.
 *
 *   2. THE CURRENT STATE IS RECORDED AS A VERSION FIRST, so restoring is itself
 *      undoable. A restore run by mistake is otherwise the one action here with
 *      no way back.
 *
 *   3. NOTHING IS DELETED UNLESS ASKED FOR. A page on the site but not in the
 *      backup was almost certainly written after it, and quietly removing
 *      someone's work in the name of "revert" is the worst thing this could do.
 *      It is listed, and removing it is a separate, explicit choice.
 *
 *   4. USERS AND ASSETS ARE NEVER WRITTEN. The export carries no password
 *      hashes, so restoring users would create accounts nobody can sign in to
 *      and could remove the only admin. It carries no uploaded files either,
 *      only the register describing them, so writing asset rows would point the
 *      site at files that may not be on disk. Both are reported instead, which
 *      is what the register is for.
 */

/** How large a backup file may be. Thirty pages of blocks is well under a megabyte. */
function restore_max_bytes(): int
{
    return 8 * 1024 * 1024;
}

/**
 * Reads and checks an uploaded file.
 *
 * Returns ['ok' => bool, 'error' => string, 'payload' => array]. Every failure
 * says what is wrong with the file rather than that something went wrong, since
 * the usual causes — the wrong file, a truncated download — are all
 * distinguishable and all fixable by the person holding it.
 */
function restore_read(string $json): array
{
    $fail = fn(string $why) => ['ok' => false, 'error' => $why, 'payload' => []];

    if (trim($json) === '') {
        return $fail('That file is empty.');
    }
    if (strlen($json) > restore_max_bytes()) {
        return $fail('That file is larger than a backup should ever be.');
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return $fail('That is not valid JSON. If it was edited by hand, something in it is malformed; '
                   . 'otherwise the download may have been cut short.');
    }
    if (!isset($data['pages']) || !is_array($data['pages'])) {
        return $fail('That JSON has no pages in it, so it is not a backup of this site.');
    }
    foreach ($data['pages'] as $page) {
        if (!is_array($page) || !isset($page['slug'], $page['blocks'])) {
            return $fail('One of the pages in that file has no slug or no blocks, so it cannot be restored.');
        }
    }

    return ['ok' => true, 'error' => '', 'payload' => $data];
}

/**
 * What restoring this file would do, without doing any of it.
 *
 * The same two-step as the Figma import, for the same reason: this is not a
 * decision to take on the strength of a filename.
 */
function restore_plan(array $payload): array
{
    $bySlug = [];
    foreach (db_all('SELECT id, slug, title, updated_at FROM pages') as $row) {
        $bySlug[$row['slug']] = $row;
    }

    $plan = [
        'exported_at' => (string) ($payload['exported_at'] ?? ''),
        'update'      => [],
        'create'      => [],
        'extra'       => [],     // on the site, not in the backup
        'settings'    => [],
        'assets_missing_row'  => [],
        'assets_missing_file' => [],
        'users'       => count($payload['users'] ?? []),
    ];

    $seen = [];
    foreach ($payload['pages'] as $page) {
        $slug = (string) $page['slug'];
        $seen[$slug] = true;
        $entry = ['slug' => $slug, 'title' => (string) ($page['title'] ?? $slug)];

        if (isset($bySlug[$slug])) {
            $entry['was'] = $bySlug[$slug]['title'];
            $entry['edited_since'] = strtotime((string) $bySlug[$slug]['updated_at'])
                                   > strtotime((string) ($page['updated_at'] ?? '1970-01-01'));
            $plan['update'][] = $entry;
        } else {
            $plan['create'][] = $entry;
        }
    }

    foreach ($bySlug as $slug => $row) {
        if (!isset($seen[$slug])) {
            $plan['extra'][] = ['slug' => $slug, 'title' => $row['title']];
        }
    }

    // Settings, and only the ones whose value would actually change.
    $current = [];
    foreach (db_all('SELECT name, value FROM settings') as $row) {
        $current[$row['name']] = $row['value'];
    }
    foreach ($payload['settings'] ?? [] as $setting) {
        $name = (string) ($setting['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $value = (string) ($setting['value'] ?? '');
        if (!array_key_exists($name, $current) || $current[$name] !== $value) {
            $plan['settings'][] = ['name' => $name, 'from' => $current[$name] ?? null, 'to' => $value];
        }
    }

    // The asset register, reported rather than written. This is the one thing
    // the backup can tell you that nothing else can: what the site used to have.
    $have = [];
    foreach (db_all('SELECT id, stored_name FROM assets') as $row) {
        $have[(int) $row['id']] = $row['stored_name'];
    }
    foreach ($payload['assets'] ?? [] as $asset) {
        $id = (int) ($asset['id'] ?? 0);
        $label = (string) ($asset['label'] ?? $asset['original_name'] ?? ('asset ' . $id));
        if (!isset($have[$id])) {
            $plan['assets_missing_row'][] = ['id' => $id, 'label' => $label];
            continue;
        }
        $file = ROOT_DIR . '/storage/assets/' . basename((string) $asset['stored_name']);
        if (!is_file($file)) {
            $plan['assets_missing_file'][] = ['id' => $id, 'label' => $label];
        }
    }

    return $plan;
}

/**
 * Applies the restore.
 *
 * `$removeExtra` deletes pages that are on the site but not in the backup, and
 * is false unless someone has deliberately said otherwise on the review screen.
 * Deleting a page takes its version history with it, which is the one
 * irreversible thing in here and is why it is opt-in and says so.
 */
function restore_apply(array $payload, bool $removeExtra, ?int $userId): array
{
    $done = ['updated' => 0, 'created' => 0, 'removed' => 0, 'settings' => 0];

    foreach ($payload['pages'] as $page) {
        $slug   = (string) $page['slug'];
        $title  = str_trim_to(trim((string) ($page['title'] ?? '')), 190) ?: 'Untitled page';
        // Through the sanitiser and the upgrade, exactly as a save would be, so a
        // backup taken before a block changed shape restores into the current
        // one and can only contain keys the definitions know about.
        $blocks = json_encode(
            sanitise_blocks(block_upgrade(json_decode((string) $page['blocks'], true) ?: [])),
            JSON_UNESCAPED_SLASHES
        );

        $existing = db_one('SELECT id FROM pages WHERE slug = ?', [$slug]);

        if ($existing) {
            // The state being replaced, kept first, so this is undoable.
            $current = db_one('SELECT title, blocks FROM pages WHERE id = ?', [$existing['id']]);
            version_record((int) $existing['id'], (string) $current['title'], (string) $current['blocks'], $userId);

            db_run(
                'UPDATE pages SET title = ?, section = ?, summary = ?, status = ?, sort_order = ?,
                        show_on_home = ?, cover_asset_id = ?, blocks = ?, updated_by = ?
                  WHERE id = ?',
                [
                    $title,
                    str_trim_to(trim((string) ($page['section'] ?? 'General')), 120) ?: 'General',
                    ($page['summary'] ?? null) !== null ? str_trim_to((string) $page['summary'], 400) : null,
                    ($page['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
                    (int) ($page['sort_order'] ?? 0),
                    !empty($page['show_on_home']) ? 1 : 0,
                    ((int) ($page['cover_asset_id'] ?? 0)) > 0 ? (int) $page['cover_asset_id'] : null,
                    $blocks,
                    $userId,
                    $existing['id'],
                ]
            );
            $done['updated']++;
        } else {
            db_run(
                'INSERT INTO pages (slug, title, section, summary, status, sort_order, show_on_home, cover_asset_id, blocks, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $slug,
                    $title,
                    str_trim_to(trim((string) ($page['section'] ?? 'General')), 120) ?: 'General',
                    ($page['summary'] ?? null) !== null ? str_trim_to((string) $page['summary'], 400) : null,
                    ($page['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
                    (int) ($page['sort_order'] ?? 0),
                    !empty($page['show_on_home']) ? 1 : 0,
                    ((int) ($page['cover_asset_id'] ?? 0)) > 0 ? (int) $page['cover_asset_id'] : null,
                    $blocks,
                    $userId,
                ]
            );
            $done['created']++;
        }
    }

    if ($removeExtra) {
        $keep = array_map(fn($p) => (string) $p['slug'], $payload['pages']);
        foreach (db_all('SELECT id, slug FROM pages') as $row) {
            if (!in_array($row['slug'], $keep, true)) {
                db_run('DELETE FROM pages WHERE id = ?', [$row['id']]);
                $done['removed']++;
            }
        }
    }

    foreach ($payload['settings'] ?? [] as $setting) {
        $name = (string) ($setting['name'] ?? '');
        if ($name === '') {
            continue;
        }
        setting_save($name, (string) ($setting['value'] ?? ''));
        $done['settings']++;
    }

    // The search index is keyed on the page count and the latest updated_at,
    // both of which this has just moved, so it rebuilds on the next request
    // without being told. The old files are cleared rather than left behind.
    if (function_exists('search_forget_old_indexes')) {
        search_forget_old_indexes('');
    }

    return $done;
}
