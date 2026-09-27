<?php
declare(strict_types=1);

/**
 * The component library.
 *
 * One row per component, its variants in their own table. A component exists so
 * that design and development are looking at the same thing: the rendered
 * component, its states, its code, and a spec derived from that code rather than
 * retyped from the design. See docs/COMPONENTS.md for the whole shape.
 *
 * This file is phase 1: the rows, and what can be done to them. The preview
 * frame, the editor, the derived spec and the Figma import each arrive in their
 * own phase and nothing here anticipates them beyond the columns they will use.
 */

/** The lifecycle. A reader sees `ready` only; an editor sees all three. */
function component_statuses(): array
{
    return [
        'draft'      => 'Draft',
        'ready'      => 'Ready',
        'deprecated' => 'Deprecated',
    ];
}

function component_status_label(string $status): string
{
    return component_statuses()[$status] ?? 'Draft';
}

/**
 * Whether the tables exist yet.
 *
 * The migration ships with the code, but it is applied by hand from Settings
 * after the deploy is green — so there is always a window where the code is
 * live and the tables are not. Everything that reads them asks this first and
 * says "apply updates", rather than throwing a 500 at whoever opened the page.
 */
function components_installed(): bool
{
    static $known = null;
    if ($known !== null) {
        return $known;
    }
    try {
        db_one('SELECT id FROM components LIMIT 1');
        $known = true;
    } catch (Throwable $e) {
        $known = false;
    }
    return $known;
}

/**
 * A stored URL, but only if it is one a browser should follow.
 *
 * The Figma link is the first field here that becomes an `href`, and the rule in
 * the conventions is that only http and https ever do — a `javascript:` value
 * pasted into it would otherwise run on whoever clicked it. Applied at render,
 * not only on save, so a value written before this existed cannot slip through.
 * Same test as `link_list`.
 */
function component_link(?string $url): ?string
{
    $url = trim((string) $url);
    return preg_match('#^https?://#i', $url) === 1 ? $url : null;
}

/** Every component, for the admin list. Ordered as the index will show them. */
function components_all(): array
{
    if (!components_installed()) {
        return [];
    }
    return db_all(
        'SELECT id, slug, name, group_name, status, summary, figma_url, sort_order, updated_at
           FROM components ORDER BY group_name, sort_order, name'
    );
}

/** Just the ones a reader may see. */
function components_published(): array
{
    if (!components_installed()) {
        return [];
    }
    return db_all(
        "SELECT id, slug, name, group_name, status, summary
           FROM components WHERE status = 'ready' ORDER BY group_name, sort_order, name"
    );
}

function component_find(int $id): ?array
{
    return components_installed()
        ? db_one('SELECT * FROM components WHERE id = ?', [$id])
        : null;
}

function component_find_slug(string $slug): ?array
{
    return components_installed()
        ? db_one('SELECT * FROM components WHERE slug = ?', [$slug])
        : null;
}

/** The variants of one component, in order, the default first among equals. */
function component_variants(int $componentId): array
{
    if (!components_installed()) {
        return [];
    }
    return db_all(
        'SELECT * FROM component_variants WHERE component_id = ? ORDER BY sort_order, id',
        [$componentId]
    );
}

/** Existing group names, for the datalist on the Add form. */
function component_groups(): array
{
    if (!components_installed()) {
        return [];
    }
    return array_column(
        db_all('SELECT DISTINCT group_name FROM components ORDER BY group_name'),
        'group_name'
    );
}

/**
 * A slug not already taken. Same approach as pages: append a number rather than
 * refusing, because two components called Card is a real thing that happens and
 * being told off for it is not helpful.
 */
function component_slug(string $name, ?int $ignoreId = null): string
{
    $base = slugify($name);
    $slug = $base;
    $n = 1;
    while (true) {
        $clash = db_one('SELECT id FROM components WHERE slug = ?', [$slug]);
        if (!$clash || (int) $clash['id'] === (int) $ignoreId) {
            return $slug;
        }
        $slug = $base . '-' . (++$n);
    }
}

/**
 * Creates a component and returns its id.
 *
 * Every text column is NOT NULL, so the blanks are written as blanks rather than
 * left to the column default. `docs` and `notes_override` hold JSON and start as
 * an empty array and an empty object, which is what the block engine and the
 * override lookup each expect to decode — not an empty string, which decodes to
 * null and would need guarding at every read.
 */
function component_create(string $name, string $group, ?int $userId): int
{
    $name  = str_trim_to(trim($name), 190) ?: 'Untitled component';
    $group = str_trim_to(trim($group), 120) ?: 'Components';

    db_run(
        'INSERT INTO components
           (slug, name, group_name, status, base_css, base_js, docs, notes_override, sort_order, updated_by)
         VALUES (?, ?, ?, ?, \'\', \'\', \'[]\', \'{}\', ?, ?)',
        [component_slug($name), $name, $group, 'draft', component_next_sort($group), $userId]
    );
    return (int) db()->lastInsertId();
}

/** Last position in the group, so a new component lands at the end of its own. */
function component_next_sort(string $group): int
{
    $row = db_one('SELECT MAX(sort_order) AS top FROM components WHERE group_name = ?', [$group]);
    return (int) ($row['top'] ?? 0) + 1;
}

function component_delete(int $id): void
{
    // Variants and versions go with it, by ON DELETE CASCADE.
    db_run('DELETE FROM components WHERE id = ?', [$id]);
}

/**
 * Moves a component one place within its group.
 *
 * The positions are normalised across the whole group first, exactly as pages
 * does it, so rows that share a sort_order — which they will, since two created
 * in the same group before either was moved both land at the end — still move
 * one place rather than jumping or refusing.
 */
function component_move(int $id, string $direction): void
{
    $component = db_one('SELECT id, group_name FROM components WHERE id = ?', [$id]);
    if (!$component) {
        return;
    }

    $ids = array_map('intval', array_column(
        db_all('SELECT id FROM components WHERE group_name = ? ORDER BY sort_order, name', [$component['group_name']]),
        'id'
    ));
    $index = array_search((int) $id, $ids, true);
    if ($index === false) {
        return;
    }

    $target = $direction === 'up' ? $index - 1 : $index + 1;
    if ($target >= 0 && $target < count($ids)) {
        array_splice($ids, $target, 0, array_splice($ids, $index, 1));
    }
    foreach ($ids as $position => $rowId) {
        db_run('UPDATE components SET sort_order = ? WHERE id = ?', [$position, $rowId]);
    }
}
