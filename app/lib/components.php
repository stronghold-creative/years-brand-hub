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
        // 'self' so the inspector can be a file rather than a few hundred lines
        // inlined into a view. Inline is already allowed — the height bridge
        // needs it — so this widens nothing that was closed.
        "script-src 'self' 'unsafe-inline'",
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

/**
 * The ones to show on the public index.
 *
 * An editor sees drafts as well, exactly as the search index gives an editor a
 * copy with drafts in it — the alternative is building a component, opening the
 * public page to check it, and finding nothing there.
 *
 * Deprecated components stay listed for everyone. That is the point of the
 * status: it says do not use this for anything new, which is information a
 * reader needs, and delisting it only means someone copies it out of the theme
 * instead.
 */
function components_visible(): array
{
    if (!components_installed()) {
        return [];
    }
    if (can_edit()) {
        return db_all(
            'SELECT id, slug, name, group_name, status, summary
               FROM components ORDER BY group_name, sort_order, name'
        );
    }
    return db_all(
        "SELECT id, slug, name, group_name, status, summary
           FROM components WHERE status IN ('ready', 'deprecated')
          ORDER BY group_name, sort_order, name"
    );
}

/**
 * Attaches each component's variants, in one query rather than one per card.
 *
 * The index needs them for the thumbnail and the count, and asking per component
 * is a query per card — fine at five components, not at fifty, and the sort of
 * thing that is never noticed until the index is slow and nobody remembers why.
 *
 * Only the columns the index uses are fetched. `markup` and `variant_css` are
 * whole documents and the index renders neither: it points an iframe at the
 * frame route, which loads them itself.
 */
function components_with_variants(array $components): array
{
    if (!$components || !components_installed()) {
        return $components;
    }
    $ids = array_map('intval', array_column($components, 'id'));
    $marks = implode(',', array_fill(0, count($ids), '?'));

    $byComponent = [];
    foreach (db_all(
        "SELECT id, component_id, label, is_default, sort_order
           FROM component_variants WHERE component_id IN ($marks) ORDER BY sort_order, id",
        $ids
    ) as $variant) {
        $byComponent[(int) $variant['component_id']][] = $variant;
    }

    foreach ($components as $i => $component) {
        $components[$i]['variants'] = $byComponent[(int) $component['id']] ?? [];
    }
    return $components;
}

/** Grouped for the index, in the order the admin list puts them. */
function components_by_group(array $components): array
{
    $out = [];
    foreach ($components as $component) {
        $out[$component['group_name']][] = $component;
    }
    ksort($out);
    return $out;
}

/**
 * Whether this component may be shown to whoever is asking.
 *
 * One function rather than the same two-part condition written out at each of
 * the three public routes, because the day one of them is written slightly
 * differently is the day a draft component is public.
 */
function component_is_visible(?array $component): bool
{
    if (!$component) {
        return false;
    }
    return can_edit() || in_array($component['status'], ['ready', 'deprecated'], true);
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
function component_variant_create(int $componentId, string $label, ?int $userId): int
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
    $id = (int) db()->lastInsertId();
    component_after_write($componentId, $userId);
    return $id;
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
function component_variant_save(int $componentId, int $variantId, array $fields, ?int $userId): void
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
    component_after_write($componentId, $userId);
}

/** Exactly one default per component, so the switch cannot land nowhere. */
function component_variant_make_default(int $componentId, int $variantId, ?int $userId): void
{
    if (!component_variant_find($componentId, $variantId)) {
        return;
    }
    db_run('UPDATE component_variants SET is_default = 0 WHERE component_id = ?', [$componentId]);
    db_run('UPDATE component_variants SET is_default = 1 WHERE id = ? AND component_id = ?', [$variantId, $componentId]);
    component_after_write($componentId, $userId);
}

/**
 * Deletes a variant, and hands the default on if it held it. Otherwise a
 * component would be left with variants, none of them default, and the page
 * would open on whichever happened to sort first — which is not wrong, but is
 * not a decision anyone made either.
 */
function component_variant_delete(int $componentId, int $variantId, ?int $userId): void
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
    component_after_write($componentId, $userId);
}

function component_variant_move(int $componentId, int $variantId, string $direction, ?int $userId): void
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
    component_after_write($componentId, $userId);
}

/**
 * Pulls the file key and node id out of a pasted Figma URL.
 *
 * Nothing is fetched. The point of keeping these apart from the URL is that the
 * REST API can be added later without a migration and without asking anyone to
 * re-paste thirty links. Figma's own URL shapes:
 *
 *   figma.com/design/{key}/{name}?node-id=123-456     current
 *   figma.com/file/{key}/{name}?node-id=123%3A456     older, colon-escaped
 *
 * The node id is normalised to the dashed form, since that is what the API and
 * every current URL use, and a stored value in two spellings would have to be
 * normalised at every read instead.
 *
 * Returns null for both parts rather than refusing the URL: a link to a whole
 * file with no selection is still worth keeping as a link.
 */
function component_figma_parse(?string $url): array
{
    $url = trim((string) $url);
    if ($url === '' || component_link($url) === null) {
        return ['url' => null, 'file_key' => null, 'node_id' => null];
    }

    $key = null;
    if (preg_match('#^https?://(?:www\.)?figma\.com/(?:design|file|proto)/([A-Za-z0-9]{10,64})#i', $url, $m)) {
        $key = $m[1];
    }

    $node = null;
    // Delimited with ~ rather than #, because the pattern needs a literal # in a
    // character class and the delimiter would end the pattern at it. The error is
    // "Unknown modifier", which points nowhere near the cause.
    if (preg_match('~[?&]node-id=([^&#]+)~i', $url, $m)) {
        // %3A is a colon; both spellings mean the same node.
        $raw = str_replace([':', '%3A', '%3a'], '-', urldecode($m[1]));
        if (preg_match('#^[0-9]+-[0-9]+$#', $raw)) {
            $node = $raw;
        }
    }

    return [
        'url'      => str_trim_to($url, 500),
        'file_key' => $key,
        'node_id'  => $node,
    ];
}

/**
 * Saves the component's own fields. Everything on the screen that is not CSS or
 * a variant.
 *
 * The slug follows the name only while nobody has typed a slug of their own,
 * because a component's URL gets pasted into Slack and a rename should not
 * quietly break it. Renaming Button to Primary button keeps /components/button.
 */
function component_save_meta(int $id, array $fields, ?int $userId): void
{
    $component = component_find($id);
    if (!$component) {
        return;
    }

    $name   = str_trim_to(trim((string) ($fields['name'] ?? '')), 190) ?: $component['name'];
    $status = array_key_exists((string) ($fields['status'] ?? ''), component_statuses())
        ? (string) $fields['status']
        : $component['status'];

    $slugWanted = trim((string) ($fields['slug'] ?? ''));
    $slug = $slugWanted !== ''
        ? component_slug($slugWanted, $id)
        : $component['slug'];

    $figma = component_figma_parse($fields['figma_url'] ?? null);

    db_run(
        'UPDATE components SET name = ?, slug = ?, group_name = ?, status = ?, summary = ?,
                figma_url = ?, figma_file_key = ?, figma_node_id = ?, base_js = ?, updated_by = ?
          WHERE id = ?',
        [
            $name,
            $slug,
            str_trim_to(trim((string) ($fields['group_name'] ?? '')), 120) ?: $component['group_name'],
            $status,
            str_trim_to(trim((string) ($fields['summary'] ?? '')), 400) ?: null,
            $figma['url'],
            $figma['file_key'],
            $figma['node_id'],
            (string) ($fields['base_js'] ?? ''),
            $userId,
            $id,
        ]
    );
    component_after_write($id, $userId);
}

// ------------------------------------------------------------------- history

/**
 * Everything about a component that is worth restoring, as JSON.
 *
 * The variants are in it, because a component and its variants are one thing to
 * whoever is editing and restoring half of it would be worse than not offering
 * a restore at all. `updated_at` and `updated_by` are deliberately left out: a
 * snapshot that carried them would differ on every save and the no-change rule
 * below would never fire.
 */
function component_snapshot(int $id): string
{
    $component = component_find($id);
    if (!$component) {
        return '';
    }

    $keep = ['name', 'slug', 'group_name', 'status', 'summary', 'figma_url',
             'figma_file_key', 'figma_node_id', 'base_css', 'base_js', 'docs', 'notes_override'];

    $out = ['component' => [], 'variants' => []];
    foreach ($keep as $field) {
        $out['component'][$field] = $component[$field];
    }
    foreach (component_variants($id) as $variant) {
        $out['variants'][] = [
            'label'       => $variant['label'],
            'markup'      => $variant['markup'],
            'variant_css' => $variant['variant_css'],
            'notes'       => $variant['notes'],
            'is_default'  => (int) $variant['is_default'],
            'sort_order'  => (int) $variant['sort_order'],
        ];
    }
    return json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
}

/**
 * Records a version, following the same three rules as a page's history — a save
 * that changes nothing records nothing, the same person within five minutes
 * updates the last entry rather than adding one, and old entries are thinned.
 * The window and the cap come from `versions.php`, so the two histories cannot
 * drift apart on policy.
 */
function component_version_record(int $id, ?int $userId): string
{
    $snapshot = component_snapshot($id);
    if ($snapshot === '') {
        return 'nothing';
    }
    $name = (string) (component_find($id)['name'] ?? '');

    $latest = db_one(
        'SELECT id, snapshot, created_by, created_at FROM component_versions
          WHERE component_id = ? ORDER BY created_at DESC, id DESC LIMIT 1',
        [$id]
    );

    if ($latest && $latest['snapshot'] === $snapshot) {
        return 'unchanged';
    }

    $latestAt = $latest ? (int) strtotime((string) ($latest['created_at'] ?? '')) : 0;
    if (
        $latest
        && (int) $latest['created_by'] === (int) $userId
        && $latestAt > 0
        && (time() - $latestAt) < version_window_seconds()
    ) {
        db_run(
            'UPDATE component_versions SET name = ?, snapshot = ?, created_at = NOW() WHERE id = ?',
            [$name, $snapshot, $latest['id']]
        );
        return 'collapsed';
    }

    db_run(
        'INSERT INTO component_versions (component_id, name, snapshot, created_by) VALUES (?, ?, ?, ?)',
        [$id, $name, $snapshot, $userId]
    );
    version_prune_history('component_versions', 'component_id', $id);
    return 'created';
}

function component_version_list(int $id): array
{
    if (!components_installed()) {
        return [];
    }
    return db_all(
        'SELECT v.id, v.name, v.snapshot, v.created_at, u.name AS author
           FROM component_versions v
           LEFT JOIN users u ON u.id = v.created_by
          WHERE v.component_id = ? ORDER BY v.created_at DESC, v.id DESC',
        [$id]
    );
}

/**
 * Puts a component back to a snapshot.
 *
 * The variants are replaced wholesale rather than matched up and updated, because
 * their ids are not in the snapshot — deliberately, since an id that no longer
 * exists cannot be restored to and matching on label would silently merge two
 * variants that had been renamed. Replacing means a restored variant is a new
 * row, which is correct: it is not the row that was deleted.
 *
 * The current state is recorded first, so restoring is itself undoable. Without
 * that, a restore chosen by mistake destroys the work it replaced.
 */
function component_restore(int $id, int $versionId, ?int $userId): bool
{
    $version = db_one(
        'SELECT snapshot FROM component_versions WHERE id = ? AND component_id = ?',
        [$versionId, $id]
    );
    if (!$version) {
        return false;
    }
    $data = json_decode((string) $version['snapshot'], true);
    if (!is_array($data) || !isset($data['component'])) {
        return false;
    }

    component_version_record($id, $userId);

    $fields = $data['component'];
    $slug = trim((string) ($fields['slug'] ?? ''));

    db_run(
        'UPDATE components SET name = ?, slug = ?, group_name = ?, status = ?, summary = ?,
                figma_url = ?, figma_file_key = ?, figma_node_id = ?,
                base_css = ?, base_js = ?, docs = ?, notes_override = ?, updated_by = ?
          WHERE id = ?',
        [
            str_trim_to((string) ($fields['name'] ?? 'Untitled component'), 190),
            $slug !== '' ? component_slug($slug, $id) : component_slug((string) ($fields['name'] ?? 'component'), $id),
            str_trim_to((string) ($fields['group_name'] ?? 'Components'), 120),
            array_key_exists((string) ($fields['status'] ?? ''), component_statuses()) ? $fields['status'] : 'draft',
            $fields['summary'] !== null ? str_trim_to((string) $fields['summary'], 400) : null,
            $fields['figma_url'] ?? null,
            $fields['figma_file_key'] ?? null,
            $fields['figma_node_id'] ?? null,
            (string) ($fields['base_css'] ?? ''),
            (string) ($fields['base_js'] ?? ''),
            // A snapshot from a broken write must not put invalid JSON into a
            // column the block engine will decode.
            json_decode((string) ($fields['docs'] ?? '[]'), true) !== null ? (string) $fields['docs'] : '[]',
            json_decode((string) ($fields['notes_override'] ?? '{}'), true) !== null ? (string) $fields['notes_override'] : '{}',
            $userId,
            $id,
        ]
    );

    db_run('DELETE FROM component_variants WHERE component_id = ?', [$id]);
    foreach ($data['variants'] ?? [] as $position => $variant) {
        if (!is_array($variant)) {
            continue;
        }
        db_run(
            'INSERT INTO component_variants (component_id, label, markup, variant_css, notes, is_default, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                str_trim_to(trim((string) ($variant['label'] ?? '')), 190) ?: 'Untitled variant',
                (string) ($variant['markup'] ?? ''),
                (string) ($variant['variant_css'] ?? ''),
                $variant['notes'] !== null ? str_trim_to((string) $variant['notes'], 400) : null,
                (int) ($variant['is_default'] ?? 0) === 1 ? 1 : 0,
                (int) ($variant['sort_order'] ?? $position),
            ]
        );
    }

    // A snapshot taken before the one-default rule existed, or hand-edited, could
    // have none or several. Settle it rather than leaving the switch nowhere.
    component_settle_default($id);
    return true;
}

/** Exactly one default, whatever the rows currently say. */
function component_settle_default(int $id): void
{
    $variants = component_variants($id);
    if (!$variants) {
        return;
    }
    $chosen = component_default_variant($variants);
    db_run('UPDATE component_variants SET is_default = 0 WHERE component_id = ?', [$id]);
    db_run('UPDATE component_variants SET is_default = 1 WHERE id = ?', [(int) $chosen['id']]);
}

/** Base CSS is shared by every variant, so it is the component's, not a variant's. */
function component_save_css(int $componentId, string $css, ?int $userId): void
{
    db_run(
        'UPDATE components SET base_css = ?, updated_by = ? WHERE id = ?',
        [$css, $userId, $componentId]
    );
    component_after_write($componentId, $userId);
}

/**
 * What every write to a component or its variants ends with: mark it edited, and
 * record a version.
 *
 * It lives here rather than in the routes so that a new write cannot forget it.
 * Six routes each remembering to call two functions is six chances to miss one,
 * and a missing version is invisible until someone needs it.
 *
 * `updated_at` is ON UPDATE CURRENT_TIMESTAMP on the components row only, so a
 * variant — a different table — would otherwise leave the list showing a
 * component as untouched for weeks while its variants were rewritten daily.
 */
function component_after_write(int $componentId, ?int $userId): void
{
    db_run('UPDATE components SET updated_at = NOW() WHERE id = ?', [$componentId]);
    component_version_record($componentId, $userId);
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
    $id = (int) db()->lastInsertId();
    // The empty state is worth a version too, so the first real edit has
    // something behind it to go back to.
    component_after_write($id, $userId);
    return $id;
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
