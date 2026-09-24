<?php
declare(strict_types=1);

/**
 * Version history.
 *
 * The editor autosaves 700ms after you stop typing, so writing one page can
 * produce hundreds of saves. Storing a full copy of the page for each one
 * would put hundreds of megabytes in the database for a single draft.
 *
 * Three rules keep it useful and small:
 *   1. A save that changes nothing records nothing.
 *   2. Saves by the same person within a few minutes collapse into one entry,
 *      so a writing session leaves a handful of versions rather than hundreds.
 *   3. Old versions are pruned: everything from the last day, then one a day,
 *      then one a week, keeping a hard maximum.
 */

/** How long before a new save starts a new version rather than updating one. */
function version_window_seconds(): int
{
    return 300;   // five minutes
}

function version_keep_max(): int
{
    return 40;
}

/**
 * Records a version, if it is worth recording. Returns what it did, which is
 * useful for the response and for tests.
 */
function version_record(int $pageId, string $title, string $blocks, ?int $userId): string
{
    $latest = db_one(
        'SELECT id, title, blocks, created_by, created_at FROM page_versions
          WHERE page_id = ? ORDER BY created_at DESC, id DESC LIMIT 1',
        [$pageId]
    );

    // 1. Nothing changed, so there is nothing worth keeping.
    if ($latest && $latest['title'] === $title && $latest['blocks'] === $blocks) {
        return 'unchanged';
    }

    // 2. The same person, still in the same session, so update in place rather
    //    than adding another row.
    // A missing timestamp is treated as old, so a row with no date can never
    // swallow a new version.
    $latestAt = $latest ? (int) strtotime((string) ($latest['created_at'] ?? '')) : 0;

    if (
        $latest
        && (int) $latest['created_by'] === (int) $userId
        && $latestAt > 0
        && (time() - $latestAt) < version_window_seconds()
    ) {
        db_run(
            'UPDATE page_versions SET title = ?, blocks = ?, created_at = NOW() WHERE id = ?',
            [$title, $blocks, $latest['id']]
        );
        return 'collapsed';
    }

    db_run(
        'INSERT INTO page_versions (page_id, title, blocks, created_by) VALUES (?, ?, ?, ?)',
        [$pageId, $title, $blocks, $userId]
    );
    version_prune($pageId);
    return 'created';
}

/**
 * Thins out a page's history: everything from the last day is kept, then one
 * per day for a month, then one per week. A hard cap catches anything else.
 */
function version_prune(int $pageId): void
{
    $rows = db_all(
        'SELECT id, created_at FROM page_versions WHERE page_id = ? ORDER BY created_at DESC, id DESC',
        [$pageId]
    );
    if (count($rows) <= version_keep_max()) {
        return;
    }

    $now = time();
    $keep = [];
    $seenDay = [];
    $seenWeek = [];

    foreach ($rows as $index => $row) {
        $at  = (int) strtotime((string) ($row['created_at'] ?? ''));
        $age = $at > 0 ? $now - $at : PHP_INT_MAX;
        $id  = (int) $row['id'];

        if ($index < 5 || $age < 86400) {            // the newest few, and today
            $keep[$id] = true;
            continue;
        }
        if ($age < 2592000) {                        // a month: one a day
            $day = date('Y-m-d', $at);
            if (!isset($seenDay[$day])) {
                $seenDay[$day] = true;
                $keep[$id] = true;
            }
            continue;
        }
        $week = $at > 0 ? date('o-W', $at) : 'undated';       // beyond: one a week
        if (!isset($seenWeek[$week])) {
            $seenWeek[$week] = true;
            $keep[$id] = true;
        }
    }

    // Thinning alone can still leave more than the cap on a long-lived page,
    // so the newest survivors win and the rest go.
    $kept = 0;
    foreach ($rows as $row) {                       // newest first
        $id = (int) $row['id'];
        if (!isset($keep[$id])) {
            continue;
        }
        $kept++;
        if ($kept > version_keep_max()) {
            unset($keep[$id]);
        }
    }

    $drop = [];
    foreach ($rows as $row) {
        if (!isset($keep[(int) $row['id']])) {
            $drop[] = (int) $row['id'];
        }
    }
    if (!$drop) {
        return;
    }

    // Chunked, so a very long history cannot build an enormous statement.
    foreach (array_chunk($drop, 100) as $chunk) {
        $marks = implode(',', array_fill(0, count($chunk), '?'));
        db_run("DELETE FROM page_versions WHERE page_id = ? AND id IN ($marks)", array_merge([$pageId], $chunk));
    }
}

/** Rough size of a page's stored history, for the admin. */
function version_bytes(int $pageId): int
{
    $row = db_one('SELECT COALESCE(SUM(LENGTH(blocks)), 0) AS bytes FROM page_versions WHERE page_id = ?', [$pageId]);
    return (int) ($row['bytes'] ?? 0);
}
