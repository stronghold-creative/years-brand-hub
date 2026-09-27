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

/**
 * The response headers for the preview frame.
 *
 * The frame renders author-supplied HTML, CSS and JavaScript, so the defence is
 * layered and each layer is here for its own reason:
 *
 *   - The iframe carries `sandbox="allow-scripts"` and NOT `allow-same-origin`.
 *     Those two together are equivalent to no sandbox at all, because a frame
 *     with both can reach into its own element and remove the attribute. Without
 *     same-origin the document is a unique opaque origin: it cannot read the
 *     parent, the session cookie, or anything in storage.
 *   - `default-src 'none'` then allows back only what a component legitimately
 *     needs. `connect-src 'none'` is the important one: inline script has to be
 *     allowed for the height bridge and for a component's own behaviour, so the
 *     limit that matters is that nothing it runs can send anything anywhere.
 *   - `form-action 'none'` stops a pasted form posting off-site, and
 *     `frame-ancestors 'self'` stops another site framing this and passing it
 *     off as ours.
 *   - `X-Frame-Options` is dropped, though `.htaccess` will put SAMEORIGIN back:
 *     `Header set` in mod_headers runs after PHP. That is harmless — SAMEORIGIN
 *     permits framing by a same-origin ancestor, which is the only kind this ever
 *     has — and `frame-ancestors 'self'` above says the same thing in the policy
 *     browsers now prefer. The `header_remove` stays because if that directive
 *     is ever tightened to DENY, this route is the one place that would break,
 *     and it should break in Apache's config rather than silently here.
 *
 * Google Fonts is named explicitly because `font_head()` links it until the
 * faces are self-hosted. When they are, these two hosts can come out.
 */
function component_frame_csp(): string
{
    // Separate from the sending so it can be asserted on rather than trusted.
    return implode('; ', [
        "default-src 'none'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' data: https://fonts.gstatic.com",
        "img-src 'self' data: blob:",
        "media-src 'self' data: blob:",
        "script-src 'unsafe-inline'",
        "connect-src 'none'",
        "form-action 'none'",
        "base-uri 'none'",
        "frame-src 'none'",
        "object-src 'none'",
        "frame-ancestors 'self'",
    ]);
}

function component_frame_headers(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, must-revalidate');
    header('Referrer-Policy: no-referrer');
    header_remove('X-Frame-Options');
    header('Content-Security-Policy: ' . component_frame_csp());
}

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

function component_variant_find(int $componentId, int $variantId): ?array
{
    return components_installed()
        ? db_one('SELECT * FROM component_variants WHERE id = ? AND component_id = ?', [$variantId, $componentId])
        : null;
}

/**
 * The variant a component opens on: the one marked default, or the first.
 *
 * Never assumes one is marked. A component whose default was deleted still has
 * to render, and returning null there would put a "no variants" state in front
 * of someone who plainly has one.
 */
function component_default_variant(array $variants): ?array
{
    foreach ($variants as $variant) {
        if ((int) $variant['is_default'] === 1) {
            return $variant;
        }
    }
    return $variants[0] ?? null;
}

/**
 * Adds a variant. The first one added is the default, because a component with
 * variants and no default is a state nothing benefits from.
 */
function component_variant_create(int $componentId, string $label): int
{
    $label = str_trim_to(trim($label), 190) ?: 'Default';
    $row = db_one(
        'SELECT COUNT(*) AS n, MAX(sort_order) AS top FROM component_variants WHERE component_id = ?',
        [$componentId]
    );
    $first = (int) ($row['n'] ?? 0) === 0;

    db_run(
        'INSERT INTO component_variants (component_id, label, markup, variant_css, is_default, sort_order)
         VALUES (?, ?, \'\', \'\', ?, ?)',
        [$componentId, $label, $first ? 1 : 0, (int) ($row['top'] ?? 0) + 1]
    );
    return (int) db()->lastInsertId();
}

/**
 * Saves a variant's content.
 *
 * `markup` and `variant_css` are stored exactly as given. This is the only place
 * in the project that keeps author-supplied HTML unescaped, and it is safe only
 * because of where it is rendered: an opaque-origin sandboxed frame, written
 * only by someone who has already passed `require_editor()`. Read section 4 of
 * `docs/COMPONENTS.md` before doing anything else with this value — in
 * particular, never echo it into an admin or reader page.
 */
function component_variant_save(int $componentId, int $variantId, array $fields): void
{
    if (!component_variant_find($componentId, $variantId)) {
        return;
    }
    db_run(
        'UPDATE component_variants SET label = ?, markup = ?, variant_css = ?, notes = ?
          WHERE id = ? AND component_id = ?',
        [
            str_trim_to(trim((string) ($fields['label'] ?? '')), 190) ?: 'Untitled variant',
            (string) ($fields['markup'] ?? ''),
            (string) ($fields['variant_css'] ?? ''),
            str_trim_to(trim((string) ($fields['notes'] ?? '')), 400) ?: null,
            $variantId,
            $componentId,
        ]
    );
    component_touch($componentId);
}

/** Exactly one default per component, so the switch cannot land nowhere. */
function component_variant_make_default(int $componentId, int $variantId): void
{
    if (!component_variant_find($componentId, $variantId)) {
        return;
    }
    db_run('UPDATE component_variants SET is_default = 0 WHERE component_id = ?', [$componentId]);
    db_run('UPDATE component_variants SET is_default = 1 WHERE id = ? AND component_id = ?', [$variantId, $componentId]);
    component_touch($componentId);
}

/**
 * Deletes a variant, and hands the default on if it held it. Otherwise a
 * component would be left with variants, none of them default, and the page
 * would open on whichever happened to sort first — which is not wrong, but is
 * not a decision anyone made either.
 */
function component_variant_delete(int $componentId, int $variantId): void
{
    $variant = component_variant_find($componentId, $variantId);
    if (!$variant) {
        return;
    }
    db_run('DELETE FROM component_variants WHERE id = ? AND component_id = ?', [$variantId, $componentId]);

    if ((int) $variant['is_default'] === 1) {
        $next = db_one(
            'SELECT id FROM component_variants WHERE component_id = ? ORDER BY sort_order, id LIMIT 1',
            [$componentId]
        );
        if ($next) {
            db_run('UPDATE component_variants SET is_default = 1 WHERE id = ?', [$next['id']]);
        }
    }
    component_touch($componentId);
}

function component_variant_move(int $componentId, int $variantId, string $direction): void
{
    $ids = array_map('intval', array_column(component_variants($componentId), 'id'));
    $index = array_search($variantId, $ids, true);
    if ($index === false) {
        return;
    }
    $target = $direction === 'up' ? $index - 1 : $index + 1;
    if ($target >= 0 && $target < count($ids)) {
        array_splice($ids, $target, 0, array_splice($ids, $index, 1));
    }
    foreach ($ids as $position => $id) {
        db_run('UPDATE component_variants SET sort_order = ? WHERE id = ?', [$position, $id]);
    }
    component_touch($componentId);
}

/** Base CSS is shared by every variant, so it is the component's, not a variant's. */
function component_save_css(int $componentId, string $css, ?int $userId): void
{
    db_run(
        'UPDATE components SET base_css = ?, updated_by = ? WHERE id = ?',
        [$css, $userId, $componentId]
    );
}

/**
 * Marks the component edited when one of its variants changed.
 *
 * `updated_at` is ON UPDATE CURRENT_TIMESTAMP, which only fires when a
 * components row itself is written — a variant is a different table, so without
 * this the list would show a component as untouched for weeks while its variants
 * were being rewritten daily.
 */
function component_touch(int $componentId): void
{
    db_run('UPDATE components SET updated_at = NOW() WHERE id = ?', [$componentId]);
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
