<?php
declare(strict_types=1);

/**
 * Search.
 *
 * The index is built here and sent to the browser once, rather than the
 * browser asking the server on every keystroke. Thirty pages of guidelines is
 * a small corpus — a few hundred kilobytes of text — and a round trip per
 * keystroke on shared hosting is a tenth of a second each time, which is the
 * difference between search that feels instant and search that feels like a
 * form. Filtering a few hundred entries in the browser is immediate.
 *
 * It is cached to storage, keyed on when a page was last edited, so it is
 * rebuilt when something changes and read from disk the rest of the time. The
 * same approach as the asset bundles.
 */

/**
 * The searchable text in one block, as a heading and a body.
 *
 * Which fields hold text is read from the block definitions rather than listed
 * here, so a new block type is searchable the moment it exists. Selects,
 * colours and asset ids are not text anyone would search for, and a repeater
 * is walked for the text inside it.
 */
function search_block_text(array $block): array
{
    $type = (string) ($block['type'] ?? '');
    $def  = block_types()[$type] ?? null;
    if (!$def) {
        return ['heading' => '', 'body' => ''];
    }

    $heading = '';
    $body    = [];

    $walk = function (array $fields, array $data, int $depth = 0) use (&$walk, &$body, &$heading): void {
        foreach ($fields as $key => $field) {
            $value = $data[$key] ?? null;

            if ($field['type'] === 'repeater') {
                if ($depth < 3 && is_array($value)) {
                    foreach ($value as $row) {
                        $walk($field['fields'], is_array($row) ? $row : [], $depth + 1);
                    }
                }
                continue;
            }
            // Anything that is not free text is not worth searching: a select
            // holds a keyword the reader never sees, an asset holds an id.
            // A grid is free text too: a table of words is exactly the sort of
            // thing someone searches for, and leaving it out would mean a term
            // that is plainly on the page not being findable.
            if (!in_array($field['type'], ['text', 'textarea', 'longtext', 'grid'], true)) {
                continue;
            }
            $text = trim((string) (is_scalar($value) ? $value : ''));
            if ($text === '') {
                continue;
            }
            // The block's own heading is worth more than its body, so it is
            // kept apart rather than mixed in.
            if ($key === 'heading' && $depth === 0 && $heading === '') {
                $heading = $text;
                continue;
            }
            $body[] = $text;
        }
    };

    $walk($def['fields'], is_array($block['data'] ?? null) ? $block['data'] : []);

    return [
        'heading' => $heading,
        'body'    => str_trim_to(trim(implode(' ', $body)), 2000),
    ];
}

/**
 * Every page as a searchable entry, with the headings inside it.
 *
 * A heading is its own result, because "clear space" is a heading halfway down
 * the logo page and landing on the page is not the same as landing on the rule.
 */
function search_entries(bool $includeDrafts): array
{
    $rows = $includeDrafts
        ? db_all('SELECT slug, title, section, summary, status, blocks FROM pages ORDER BY sort_order, title')
        : db_all("SELECT slug, title, section, summary, status, blocks FROM pages WHERE status = 'published' ORDER BY sort_order, title");

    $entries = [];

    foreach ($rows as $row) {
        $blocks = json_decode((string) $row['blocks'], true) ?: [];
        $text   = [];
        $marks  = [];

        foreach ($blocks as $block) {
            $found = search_block_text($block);
            if ($found['heading'] !== '') {
                $marks[] = [
                    'title'  => $found['heading'],
                    // The anchor a block was given, if it has one. Without it
                    // the heading still points at the page it is on.
                    'anchor' => (string) ($block['settings']['anchor'] ?? ''),
                ];
            }
            $text[] = trim($found['heading'] . ' ' . $found['body']);
        }

        $entries[] = [
            'title'   => (string) $row['title'],
            'section' => (string) $row['section'],
            'slug'    => (string) $row['slug'],
            'summary' => (string) ($row['summary'] ?? ''),
            'draft'   => $row['status'] !== 'published',
            'marks'   => $marks,
            // One flattened string to match against, lowercased once here
            // rather than on every keystroke in the browser.
            'text'    => strtolower(str_trim_to(trim(implode(' ', array_filter($text))), 8000)),
        ];
    }

    return array_merge($entries, search_component_entries($includeDrafts));
}

/**
 * Components, in the same index.
 *
 * Someone half-remembering a rule does not know whether it is written up as
 * guidance or built as a component, and should not have to. A component result
 * carries `kind` so the list can label it; a page result has none, which reads
 * as the default rather than needing one.
 *
 * What is searchable: the name, the summary, the variant labels, and the text of
 * the written guidance — through the same block walker the pages use, so a new
 * block type is searchable here the moment it exists. Not the markup or the CSS:
 * a search for "button" should not match every component whose CSS mentions one.
 */
function search_component_entries(bool $includeDrafts): array
{
    if (!components_installed()) {
        return [];
    }

    $rows = $includeDrafts
        ? db_all('SELECT id, slug, name, group_name, status, summary, docs FROM components ORDER BY group_name, sort_order, name')
        : db_all("SELECT id, slug, name, group_name, status, summary, docs FROM components
                   WHERE status IN ('ready', 'deprecated') ORDER BY group_name, sort_order, name");

    if (!$rows) {
        return [];
    }

    $labels = [];
    foreach (db_all('SELECT component_id, label FROM component_variants ORDER BY sort_order, id') as $variant) {
        $labels[(int) $variant['component_id']][] = (string) $variant['label'];
    }

    $entries = [];
    foreach ($rows as $row) {
        $text  = [(string) $row['name'], (string) ($row['summary'] ?? ''), (string) $row['group_name']];
        $marks = [];

        foreach ($labels[(int) $row['id']] ?? [] as $label) {
            $text[] = $label;
        }

        foreach (json_decode((string) $row['docs'], true) ?: [] as $block) {
            $found = search_block_text($block);
            if ($found['heading'] !== '') {
                $marks[] = ['title' => $found['heading'], 'anchor' => (string) ($block['settings']['anchor'] ?? '')];
            }
            $text[] = trim($found['heading'] . ' ' . $found['body']);
        }

        $entries[] = [
            'title'   => (string) $row['name'],
            'section' => (string) $row['group_name'],
            'slug'    => (string) $row['slug'],
            'summary' => (string) ($row['summary'] ?? ''),
            'draft'   => $row['status'] === 'draft',
            'kind'    => 'component',
            'marks'   => $marks,
            'text'    => strtolower(str_trim_to(trim(implode(' ', array_filter($text))), 8000)),
        ];
    }

    return $entries;
}

/** Where the built index is kept, and what makes it stale. */
function search_cache_path(bool $includeDrafts): ?string
{
    $state = db_one('SELECT COUNT(*) AS n, COALESCE(MAX(updated_at), 0) AS last FROM pages');
    if (!$state) {
        return null;
    }

    /**
     * The index covers components too, so the key has to move when one changes.
     *
     * Keyed on pages alone, editing a component would serve a stale index for as
     * long as nobody touched a page — and nothing would look wrong, which is the
     * worst kind of wrong. `component_after_write()` moves their `updated_at`,
     * including when only a variant changed, so this is enough.
     */
    $components = components_installed()
        ? db_one('SELECT COUNT(*) AS n, COALESCE(MAX(updated_at), 0) AS last FROM components')
        : ['n' => 0, 'last' => 0];

    $dir = ROOT_DIR . '/storage/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    // Any edit moves updated_at, so the name changes and the old file is
    // simply never read again.
    $key = sha1(implode('|', [
        $state['n'], $state['last'],
        $components['n'] ?? 0, $components['last'] ?? 0,
        $includeDrafts ? 'all' : 'published',
    ]));
    return $dir . '/search-' . substr($key, 0, 16) . '.json';
}

/**
 * The index as JSON, built if it is not already on disk.
 *
 * Drafts are in it only for someone who can edit, which is the same rule the
 * section view follows.
 */
function search_index_json(bool $includeDrafts): string
{
    $path = search_cache_path($includeDrafts);

    if ($path !== null && is_file($path)) {
        $cached = file_get_contents($path);
        if ($cached !== false && $cached !== '') {
            return $cached;
        }
    }

    $json = json_encode(
        ['pages' => search_entries($includeDrafts)],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) ?: '{"pages":[]}';

    if ($path !== null) {
        // A failed write is not worth an error: the index is simply rebuilt
        // next time, which is slower but correct.
        @file_put_contents($path, $json);
        search_forget_old_indexes($path);
    }

    return $json;
}

/** Clears indexes from before the latest edit, so storage does not fill up. */
function search_forget_old_indexes(string $keep): void
{
    foreach (glob(ROOT_DIR . '/storage/cache/search-*.json') ?: [] as $file) {
        if ($file !== $keep) {
            @unlink($file);
        }
    }
}
