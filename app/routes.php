<?php
declare(strict_types=1);

/** @var array $config */

$path   = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---------------------------------------------------------------- public site

if ($path === '/') {
    if (site_is_private($config) && !current_user()) {
        redirect('/admin/sign-in');
    }
    $pages = db_all("SELECT slug, title, section, summary, cover_asset_id FROM pages
                      WHERE status = 'published' AND show_on_home = 1
                      ORDER BY sort_order, title");
    $sections = [];
    foreach ($pages as $page) {
        $sections[$page['section']][] = $page;
    }
    $sections = sort_sections($sections);
    view('home', ['title' => $config['site']['name'], 'sections' => $sections, 'config' => $config]);
    exit;
}

// The search index, built once and filtered in the browser. Gated exactly as
// the pages are: nothing here that the reader could not already open.
if ($path === '/search.json') {
    if (site_is_private($config) && !current_user()) {
        not_found();
    }
    header('Content-Type: application/json');
    // Private to the reader, because an editor's copy carries drafts.
    header('Cache-Control: private, max-age=300');
    echo search_index_json(can_edit());
    exit;
}

// Design tokens generated from the guidelines themselves.
if ($path === '/tokens.css') {
    header('Content-Type: text/css');
    echo ":root {\n";
    // The same list the component spec table matches against, so a colour cannot
    // be a token here and an unknown value there.
    foreach (design_colour_tokens() as $name => $hex) {
        printf("  %s: %s;\n", $name, $hex);
    }
    echo "}\n";
    exit;
}

/**
 * One variant of one component, rendered alone for an iframe.
 *
 * Gated exactly as the guidelines are, plus: a component that is not `ready` is
 * visible only to an editor, and the area switch is ignored for an editor so the
 * preview works while the area is still hidden from readers.
 *
 * It answers 404 rather than redirecting, because this is only ever loaded into
 * a frame and a sign-in page inside one is no use to anybody.
 */
if (preg_match('#^/components/([a-z0-9\-]+)/frame/(\d+)$#', $path, $m)) {
    if (site_is_private($config) && !current_user()) {
        not_found();
    }
    if (!components_enabled() && !can_edit()) {
        not_found();
    }

    $component = component_find_slug($m[1]);
    if (!component_is_visible($component)) {
        not_found();
    }

    $variant = component_variant_find((int) $component['id'], (int) $m[2]);
    if (!$variant) {
        not_found();
    }

    component_frame_headers();
    view('component-frame', [
        'component' => $component,
        'variant'   => $variant,
        // Only the two values are honoured, so the attribute cannot be steered
        // into anything else by the query string.
        'scheme'    => ($_GET['scheme'] ?? '') === 'dark' ? 'dark' : 'light',
    ], null);
    exit;
}

if ($path === '/components') {
    if (site_is_private($config) && !current_user()) {
        redirect('/admin/sign-in');
    }
    // An editor gets in while the area is still switched off, or the area could
    // never be filled before being turned on.
    if (!components_enabled() && !can_edit()) {
        not_found();
    }
    $components = components_with_variants(components_visible());
    view('components', [
        'title'       => 'Components',
        'currentArea' => 'components',
        'config'      => $config,
        'components'  => $components,
        'groups'      => components_by_group($components),
    ]);
    exit;
}

// One component: the real thing, its variants, its code and the rules.
if (preg_match('#^/components/([a-z0-9\-]+)$#', $path, $m)) {
    if (site_is_private($config) && !current_user()) {
        redirect('/admin/sign-in');
    }
    if (!components_enabled() && !can_edit()) {
        not_found();
    }

    $component = component_find_slug($m[1]);
    if (!component_is_visible($component)) {
        not_found();
    }

    $variants = component_variants((int) $component['id']);

    // ?v= opens a named variant, so a link to one state can be shared. An id
    // that is not this component's falls back to the default rather than 404ing:
    // a stale link should still land on the component.
    $open = component_default_variant($variants);
    foreach ($variants as $variant) {
        if ((int) $variant['id'] === (int) ($_GET['v'] ?? 0)) {
            $open = $variant;
            break;
        }
    }

    view('component', [
        'title'       => $component['name'],
        'description' => $component['summary'] ?? '',
        'currentArea' => 'components',
        'config'      => $config,
        'component'   => $component,
        'variants'    => $variants,
        'open'        => $open,
        // The spec covers the base CSS plus the open variant's, which is the same
        // code the panel below it offers to copy.
        'spec'        => component_spec(
            trim($component['base_css'] . "\n" . (string) ($open['variant_css'] ?? '')),
            component_notes($component)
        ),
        // The preview needs components.js, and no other public page does.
        'needsComponentJs' => true,
    ]);
    exit;
}

// A whole section on one page. Each page within it is an anchor, so the rail
// scrolls rather than navigating, which is how people actually read guidelines.
if (preg_match('#^/s/([a-z0-9\-]+)$#', $path, $m)) {
    if (site_is_private($config) && !current_user()) {
        redirect('/admin/sign-in');
    }

    $visible = can_edit()
        ? db_all('SELECT * FROM pages ORDER BY sort_order, title')
        : db_all("SELECT * FROM pages WHERE status = 'published' ORDER BY sort_order, title");

    $bySection = [];
    foreach ($visible as $row) {
        $bySection[$row['section']][] = $row;
    }
    $bySection = sort_sections($bySection);

    $current = null;
    foreach (array_keys($bySection) as $name) {
        if (slugify($name) === $m[1]) {
            $current = $name;
        }
    }
    if ($current === null) {
        not_found();
    }

    // A hero at the very start of the section opens it, above the rail and
    // the chapters, rather than appearing partway down the scroll.
    $pages = $bySection[$current];
    $hero  = take_section_hero($pages);

    view('section', [
        'title'   => $current,
        'section' => $current,
        'pages'   => $pages,
        'hero'    => $hero,
        'nav'     => $bySection,
        'config'  => $config,
        // The rail is the navigation here, so the bar steps aside; see layout.php.
        'chrome'  => 'reading',
    ]);
    exit;
}

if (preg_match('#^/g/([a-z0-9\-]+)$#', $path, $m)) {
    if (site_is_private($config) && !current_user()) {
        redirect('/admin/sign-in');
    }
    $page = db_one('SELECT * FROM pages WHERE slug = ?', [$m[1]]);
    if (!$page || ($page['status'] !== 'published' && !can_edit())) {
        not_found();
    }
    // Old links, and links from the index, land at the right point in the
    // section rather than on a page of their own.
    redirect('/s/' . slugify($page['section']) . '#' . $page['slug']);
}

// ---------------------------------------------------------------------- admin

if ($path === '/admin/sign-in') {
    if ($method === 'POST') {
        csrf_check();
        $email = (string) ($_POST['email'] ?? '');

        $wait = sign_in_locked_for($email);
        if ($wait > 0) {
            view('admin/sign-in', [
                'title' => 'Sign in',
                'error' => 'Too many attempts. Try again in ' . max(1, (int) ceil($wait / 60)) . ' minutes.',
            ], null);
            exit;
        }

        if (attempt_sign_in($email, $_POST['password'] ?? '')) {
            redirect('/admin');
        }

        // A short delay makes guessing slow even before the limit is reached.
        usleep(400000);
        view('admin/sign-in', ['title' => 'Sign in', 'error' => 'That email and password do not match.'], null);
        exit;
    }
    view('admin/sign-in', ['title' => 'Sign in', 'error' => null], null);
    exit;
}

if ($path === '/admin/sign-out') {
    sign_out();
    redirect('/');
}

if ($path === '/admin') {
    require_editor();
    $pages = db_all('SELECT id, slug, title, section, summary, status, cover_asset_id, updated_at FROM pages ORDER BY section, sort_order, title');
    view('admin/pages', ['title' => 'Pages', 'pages' => $pages], 'admin/layout');
    exit;
}

if ($path === '/admin/pages/new' && $method === 'POST') {
    require_editor();
    csrf_check();
    $title = trim($_POST['title'] ?? '') ?: 'Untitled page';
    $slug  = slugify($title);
    $n = 1;
    while (db_one('SELECT id FROM pages WHERE slug = ?', [$slug])) {
        $slug = slugify($title) . '-' . (++$n);
    }
    db_run(
        'INSERT INTO pages (slug, title, section, status, blocks, updated_by) VALUES (?, ?, ?, ?, ?, ?)',
        [$slug, $title, trim($_POST['section'] ?? '') ?: 'General', 'draft', '[]', current_user()['id']]
    );
    redirect('/admin/pages/' . db()->lastInsertId());
}

if (preg_match('#^/admin/pages/(\d+)$#', $path, $m)) {
    require_editor();
    $page = db_one('SELECT * FROM pages WHERE id = ?', [$m[1]]);
    if (!$page) {
        not_found();
    }
    view('admin/edit', [
        'title'     => $page['title'],
        'page'      => $page,
        'blocks'    => block_upgrade(json_decode($page['blocks'], true) ?: []),
        'fullBleed' => true,
    ], 'admin/layout');
    exit;
}

// Autosave endpoint. Writes a version snapshot on every save.
if (preg_match('#^/admin/pages/(\d+)/save$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    $payload = json_in();
    $page = db_one('SELECT id FROM pages WHERE id = ?', [$m[1]]);
    if (!$page) {
        json_out(['ok' => false, 'error' => 'That page no longer exists.'], 404);
    }

    $blocks = json_encode(sanitise_blocks($payload['blocks'] ?? []), JSON_UNESCAPED_SLASHES);
    $title  = trim($payload['title'] ?? '') ?: 'Untitled page';
    $userId = current_user()['id'];

    db()->beginTransaction();
    $cover = (int) ($payload['cover'] ?? 0);
    db_run(
        'UPDATE pages SET title = ?, section = ?, summary = ?, status = ?, cover_asset_id = ?, show_on_home = ?, blocks = ?, updated_by = ? WHERE id = ?',
        [
            $title,
            trim($payload['section'] ?? '') ?: 'General',
            str_trim_to(trim($payload['summary'] ?? ''), 400),
            ($payload['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
            $cover > 0 ? $cover : null,
            !empty($payload['show_on_home']) ? 1 : 0,
            $blocks,
            $userId,
            $page['id'],
        ]
    );
    // Records a version only when something actually changed, and collapses
    // rapid saves, so a writing session does not fill the database.
    version_record((int) $page['id'], $title, $blocks, $userId);
    db()->commit();

    json_out(['ok' => true, 'saved_at' => date('H:i')]);
}

// Live preview pane: renders the draft exactly as the public page would.
if (preg_match('#^/admin/pages/(\d+)/preview$#', $path, $m)) {
    require_editor();
    $page = db_one('SELECT * FROM pages WHERE id = ?', [$m[1]]);
    if (!$page) {
        not_found();
    }
    view('preview', [
        'title'   => $page['title'],
        'page'    => $page,
        'blocks'  => block_upgrade(json_decode($page['blocks'], true) ?: []),
        'config'  => $config,
        'editing' => isset($_GET['edit']),
    ], null);
    exit;
}

if (preg_match('#^/admin/pages/(\d+)/cover$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    $page = db_one('SELECT id FROM pages WHERE id = ?', [(int) $m[1]]);
    if (!$page) {
        not_found();
    }
    // A cover can be cleared, which stores null rather than zero.
    $asset = (int) ($_POST['asset'] ?? 0);
    db_run('UPDATE pages SET cover_asset_id = ? WHERE id = ?', [$asset > 0 ? $asset : null, $page['id']]);
    json_out(['ok' => true, 'asset' => $asset]);
}

// Duplicating a page. With thirty pages to write, starting each one from the
// same skeleton is a common move, and rebuilding it block by block is not.
if (preg_match('#^/admin/pages/(\d+)/duplicate$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    $page = db_one('SELECT * FROM pages WHERE id = ?', [(int) $m[1]]);
    if (!$page) {
        not_found();
    }

    $title = str_trim_to($page['title'] . ' copy', 200);
    $slug  = slugify($title);
    $n = 1;
    while (db_one('SELECT id FROM pages WHERE slug = ?', [$slug])) {
        $slug = slugify($title) . '-' . (++$n);
    }

    // Always a draft, whatever the original was. A copy is not ready to read.
    db_run(
        'INSERT INTO pages (slug, title, section, summary, status, blocks, cover_asset_id, show_on_home, sort_order, updated_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $slug,
            $title,
            $page['section'],
            $page['summary'],
            'draft',
            $page['blocks'],
            $page['cover_asset_id'],
            0,
            $page['sort_order'],
            current_user()['id'],
        ]
    );
    redirect('/admin/pages/' . db()->lastInsertId());
}

if (preg_match('#^/admin/pages/(\d+)/delete$#', $path, $m) && $method === 'POST') {
    require_admin();
    csrf_check();
    db_run('DELETE FROM pages WHERE id = ?', [$m[1]]);
    redirect('/admin');
}

// ------------------------------------------------------------ version history

if (preg_match('#^/admin/pages/(\d+)/versions$#', $path, $m)) {
    require_editor();
    $page = db_one('SELECT id, title, slug FROM pages WHERE id = ?', [$m[1]]);
    if (!$page) {
        not_found();
    }
    // The blocks come back too, so the list can say what actually changed
    // rather than only when it was saved.
    $versions = db_all(
        'SELECT v.id, v.title, v.blocks, v.created_at, u.name AS author
           FROM page_versions v LEFT JOIN users u ON u.id = v.created_by
          WHERE v.page_id = ? ORDER BY v.created_at DESC LIMIT 50',
        [$page['id']]
    );
    view('admin/versions', ['title' => 'History', 'page' => $page, 'versions' => $versions], 'admin/layout');
    exit;
}

// One version, rendered as the page would read. Restoring used to be blind:
// a date, a title, and a button.
if (preg_match('#^/admin/pages/(\d+)/versions/(\d+)/preview$#', $path, $m)) {
    require_editor();
    $version = db_one('SELECT * FROM page_versions WHERE id = ? AND page_id = ?', [(int) $m[2], (int) $m[1]]);
    if (!$version) {
        not_found();
    }
    view('preview', [
        'title'   => $version['title'],
        'page'    => ['title' => $version['title'], 'summary' => ''],
        'blocks'  => json_decode($version['blocks'], true) ?: [],
        'config'  => $config,
        'editing' => false,
    ], null);
    exit;
}

if (preg_match('#^/admin/pages/(\d+)/restore/(\d+)$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    $version = db_one('SELECT * FROM page_versions WHERE id = ? AND page_id = ?', [$m[2], $m[1]]);
    if (!$version) {
        not_found();
    }
    // Restoring is itself a save, so the current state is never lost.
    db_run(
        'UPDATE pages SET title = ?, blocks = ?, updated_by = ? WHERE id = ?',
        [$version['title'], $version['blocks'], current_user()['id'], $m[1]]
    );
    db_run(
        'INSERT INTO page_versions (page_id, title, blocks, created_by) VALUES (?, ?, ?, ?)',
        [$m[1], $version['title'], $version['blocks'], current_user()['id']]
    );
    redirect('/admin/pages/' . $m[1]);
}

// ------------------------------------------------------------- page ordering

if (preg_match('#^/admin/pages/(\d+)/move/(up|down)$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    $page = db_one('SELECT id, section, sort_order FROM pages WHERE id = ?', [$m[1]]);
    if (!$page) {
        not_found();
    }
    // Normalise ordering within the section first, so rows created before
    // sort_order existed still move predictably.
    $siblings = db_all(
        'SELECT id FROM pages WHERE section = ? ORDER BY sort_order, title',
        [$page['section']]
    );
    $ids = array_column($siblings, 'id');
    $index = array_search((int) $page['id'], array_map('intval', $ids), true);
    $target = $m[2] === 'up' ? $index - 1 : $index + 1;

    if ($index !== false && $target >= 0 && $target < count($ids)) {
        array_splice($ids, $target, 0, array_splice($ids, $index, 1));
    }
    foreach ($ids as $position => $id) {
        db_run('UPDATE pages SET sort_order = ? WHERE id = ?', [$position, $id]);
    }
    redirect('/admin');
}

if (preg_match('#^/admin/sections/move/(up|down)$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    $names = array_column(db_all('SELECT DISTINCT section FROM pages ORDER BY section'), 'section');
    $bySection = array_fill_keys($names, []);
    section_move(trim($_POST['section'] ?? ''), $m[1], array_keys(sort_sections($bySection)));
    redirect('/admin');
}

if ($path === '/admin/sections/rename' && $method === 'POST') {
    require_editor();
    csrf_check();
    $from = trim($_POST['from'] ?? '');
    $to   = trim($_POST['to'] ?? '');
    if ($from !== '' && $to !== '') {
        if ($from !== $to) {
            db_run('UPDATE pages SET section = ? WHERE section = ?', [$to, $from]);
        }
        section_icon_rename($from, $to);
        if (isset($_POST['icon'])) {
            section_icon_save($to, (string) $_POST['icon']);
        }
    }
    redirect('/admin');
}

// ---------------------------------------------------------------- components

// The public /components area stays behind components_enabled, but the admin
// does not: the switch exists so readers cannot see an empty area, and it would
// be perverse if it also stopped the person filling it.

if ($path === '/admin/components') {
    require_editor();
    view('admin/components', [
        'title'      => 'Components',
        'components' => components_all(),
        'groups'     => component_groups(),
        'installed'  => components_installed(),
    ], 'admin/layout');
    exit;
}

if ($path === '/admin/components/new' && $method === 'POST') {
    require_editor();
    csrf_check();
    if (!components_installed()) {
        redirect('/admin/components');
    }
    component_create(
        $_POST['name'] ?? '',
        $_POST['group'] ?? '',
        current_user()['id']
    );
    redirect('/admin/components');
}

// The component screen. One component, its base CSS and its variants, with the
// live frame beside them. Plain form posts for now; autosave is phase 3.
if (preg_match('#^/admin/components/(\d+)$#', $path, $m)) {
    require_editor();
    $component = component_find((int) $m[1]);
    if (!$component) {
        not_found();
    }
    $variants = component_variants((int) $component['id']);
    view('admin/component', [
        'title'     => $component['name'],
        'component' => $component,
        'variants'  => $variants,
        'open'      => component_default_variant($variants),
        'groups'    => component_groups(),
        'spec'      => component_spec($component['base_css'], component_notes($component)),
    ], 'admin/layout');
    exit;
}

// The written guidance on a component, in the same block editor the guidelines
// use. Same blocks, same sanitiser, same canvas — a different row.
if (preg_match('#^/admin/components/(\d+)/docs$#', $path, $m)) {
    require_editor();
    $component = component_find((int) $m[1]);
    if (!$component) {
        not_found();
    }
    view('admin/component-docs', [
        'title'     => $component['name'] . ' guidance',
        'component' => $component,
        'blocks'    => block_upgrade(json_decode((string) $component['docs'], true) ?: []),
        'fullBleed' => true,
    ], 'admin/layout');
    exit;
}

if (preg_match('#^/admin/components/(\d+)/docs/save$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    if (!component_find((int) $m[1])) {
        json_out(['ok' => false, 'error' => 'That component no longer exists.'], 404);
    }
    // Through the same sanitiser as a page's blocks, so stored JSON here can only
    // contain keys the block definitions know about.
    $blocks = json_encode(sanitise_blocks(json_in()['blocks'] ?? []), JSON_UNESCAPED_SLASHES);
    $userId = current_user()['id'];

    db_run('UPDATE components SET docs = ?, updated_by = ? WHERE id = ?', [$blocks, $userId, (int) $m[1]]);
    component_after_write((int) $m[1], $userId);

    json_out(['ok' => true, 'saved_at' => date('H:i')]);
}

if (preg_match('#^/admin/components/(\d+)/docs/preview$#', $path, $m)) {
    require_editor();
    $component = component_find((int) $m[1]);
    if (!$component) {
        not_found();
    }
    view('component-docs-preview', [
        'component' => $component,
        'blocks'    => block_upgrade(json_decode((string) $component['docs'], true) ?: []),
        'editing'   => isset($_GET['edit']),
    ], null);
    exit;
}

// Importing. Two steps on purpose: the first only looks at what was pasted and
// shows what it would do, the second writes. Dev Mode's output is close but
// rarely shippable, so it is never saved on the strength of a paste alone.
if (preg_match('#^/admin/components/(\d+)/import$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    $component = component_find((int) $m[1]);
    if (!$component) {
        not_found();
    }

    $found = component_import_detect((string) ($_POST['pasted'] ?? ''));

    view('admin/component-import', [
        'title'     => 'Import into ' . $component['name'],
        'component' => $component,
        'variants'  => component_variants((int) $component['id']),
        'found'     => $found,
        // What the incoming CSS would look like once it is in, which is where a
        // hardcoded Dev Mode colour shows up — before it is accepted, not after.
        'spec'      => component_spec($found['css'], []),
    ], 'admin/layout');
    exit;
}

if (preg_match('#^/admin/components/(\d+)/import/apply$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    if (!component_find((int) $m[1])) {
        not_found();
    }
    $landed = component_import_apply((int) $m[1], [
        'markup'    => $_POST['markup'] ?? '',
        'css'       => $_POST['css'] ?? '',
        'figma_url' => $_POST['figma_url'] ?? '',
        'variant'   => $_POST['variant'] ?? 0,
        'label'     => $_POST['label'] ?? '',
    ], current_user()['id']);

    redirect('/admin/components/' . (int) $m[1] . ($landed ? '?v=' . $landed : ''));
}

if (preg_match('#^/admin/components/(\d+)/notes$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    if (!component_find((int) $m[1])) {
        not_found();
    }
    $notes = $_POST['note'] ?? [];
    component_notes_save((int) $m[1], is_array($notes) ? $notes : [], current_user()['id']);
    redirect('/admin/components/' . (int) $m[1]);
}

if (preg_match('#^/admin/components/(\d+)/meta$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    if (!component_find((int) $m[1])) {
        not_found();
    }
    component_save_meta((int) $m[1], [
        'name'       => $_POST['name'] ?? '',
        'slug'       => $_POST['slug'] ?? '',
        'group_name' => $_POST['group_name'] ?? '',
        'status'     => $_POST['status'] ?? '',
        'summary'    => $_POST['summary'] ?? '',
        'figma_url'  => $_POST['figma_url'] ?? '',
        'base_js'    => $_POST['base_js'] ?? '',
    ], current_user()['id']);
    redirect('/admin/components/' . (int) $m[1]);
}

if (preg_match('#^/admin/components/(\d+)/versions$#', $path, $m)) {
    require_editor();
    $component = component_find((int) $m[1]);
    if (!$component) {
        not_found();
    }
    view('admin/component-versions', [
        'title'     => $component['name'] . ' history',
        'component' => $component,
        'versions'  => component_version_list((int) $component['id']),
    ], 'admin/layout');
    exit;
}

if (preg_match('#^/admin/components/(\d+)/restore/(\d+)$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    component_restore((int) $m[1], (int) $m[2], current_user()['id']);
    redirect('/admin/components/' . (int) $m[1]);
}

if (preg_match('#^/admin/components/(\d+)/css$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    if (!component_find((int) $m[1])) {
        not_found();
    }
    component_save_css((int) $m[1], (string) ($_POST['base_css'] ?? ''), current_user()['id']);
    redirect('/admin/components/' . (int) $m[1]);
}

if (preg_match('#^/admin/components/(\d+)/variants/new$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    if (!component_find((int) $m[1])) {
        not_found();
    }
    component_variant_create((int) $m[1], $_POST['label'] ?? '', current_user()['id']);
    redirect('/admin/components/' . (int) $m[1]);
}

if (preg_match('#^/admin/components/(\d+)/variants/(\d+)/save$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    component_variant_save((int) $m[1], (int) $m[2], [
        'label'       => $_POST['label'] ?? '',
        'markup'      => $_POST['markup'] ?? '',
        'variant_css' => $_POST['variant_css'] ?? '',
        'notes'       => $_POST['notes'] ?? '',
    ], current_user()['id']);
    redirect('/admin/components/' . (int) $m[1] . '?v=' . (int) $m[2]);
}

if (preg_match('#^/admin/components/(\d+)/variants/(\d+)/default$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    component_variant_make_default((int) $m[1], (int) $m[2], current_user()['id']);
    redirect('/admin/components/' . (int) $m[1] . '?v=' . (int) $m[2]);
}

if (preg_match('#^/admin/components/(\d+)/variants/(\d+)/move/(up|down)$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    component_variant_move((int) $m[1], (int) $m[2], $m[3], current_user()['id']);
    redirect('/admin/components/' . (int) $m[1] . '?v=' . (int) $m[2]);
}

if (preg_match('#^/admin/components/(\d+)/variants/(\d+)/delete$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    component_variant_delete((int) $m[1], (int) $m[2], current_user()['id']);
    redirect('/admin/components/' . (int) $m[1]);
}

if (preg_match('#^/admin/components/(\d+)/move/(up|down)$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    component_move((int) $m[1], $m[2]);
    redirect('/admin/components');
}

if (preg_match('#^/admin/components/(\d+)/delete$#', $path, $m) && $method === 'POST') {
    require_admin();
    csrf_check();
    component_delete((int) $m[1]);
    redirect('/admin/components');
}

// -------------------------------------------------------------------- people

if ($path === '/admin/users') {
    require_admin();
    $users = db_all('SELECT id, name, email, role, created_at FROM users ORDER BY name');
    view('admin/users', ['title' => 'People', 'users' => $users, 'notice' => $_GET['notice'] ?? null], 'admin/layout');
    exit;
}

if ($path === '/admin/users/create' && $method === 'POST') {
    require_admin();
    csrf_check();
    $name     = trim($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role     = in_array($_POST['role'] ?? '', ['admin', 'editor', 'viewer'], true) ? $_POST['role'] : 'viewer';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
        redirect('/admin/users?notice=Check+the+name,+email+and+a+password+of+at+least+12+characters.');
    }
    if (db_one('SELECT id FROM users WHERE email = ?', [$email])) {
        redirect('/admin/users?notice=Someone+already+uses+that+email.');
    }
    db_run(
        'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
        [$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]
    );
    redirect('/admin/users?notice=Account+created.');
}

if (preg_match('#^/admin/users/(\d+)/update$#', $path, $m) && $method === 'POST') {
    require_admin();
    csrf_check();
    $target = db_one('SELECT id, role FROM users WHERE id = ?', [$m[1]]);
    if (!$target) {
        not_found();
    }
    $role = in_array($_POST['role'] ?? '', ['admin', 'editor', 'viewer'], true) ? $_POST['role'] : $target['role'];

    // Never leave the site without an administrator.
    $admins = (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role = 'admin'")['n'];
    if ($target['role'] === 'admin' && $role !== 'admin' && $admins <= 1) {
        redirect('/admin/users?notice=That+is+the+only+admin,+so+the+role+cannot+change.');
    }
    db_run('UPDATE users SET role = ? WHERE id = ?', [$role, $target['id']]);

    $password = (string) ($_POST['password'] ?? '');
    if ($password !== '') {
        if (strlen($password) < 12) {
            redirect('/admin/users?notice=Passwords+need+at+least+12+characters.');
        }
        db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $target['id']]);
    }
    redirect('/admin/users?notice=Saved.');
}

if (preg_match('#^/admin/users/(\d+)/delete$#', $path, $m) && $method === 'POST') {
    require_admin();
    csrf_check();
    if ((int) $m[1] === (int) current_user()['id']) {
        redirect('/admin/users?notice=You+cannot+delete+your+own+account.');
    }
    $target = db_one('SELECT id, role FROM users WHERE id = ?', [$m[1]]);
    if (!$target) {
        not_found();
    }
    $admins = (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role = 'admin'")['n'];
    if ($target['role'] === 'admin' && $admins <= 1) {
        redirect('/admin/users?notice=That+is+the+only+admin+account.');
    }
    db_run('DELETE FROM users WHERE id = ?', [$target['id']]);
    redirect('/admin/users?notice=Account+removed.');
}

// ------------------------------------------------------------------ settings

// Images and video shown inside pages. Separate from /d/ because these display
// rather than download, and are not counted as downloads. Anchored, so
// /i/9/anything cannot reach here on a partial match.
if (preg_match('#^/i/(\d+)(?:/(\d+))?$#', $path, $m)) {
    if (site_is_private($config) && !current_user()) {
        not_found();
    }
    $asset = asset_find((int) $m[1]);
    if (!$asset || !asset_is_inline_media($asset)) {
        not_found();
    }
    asset_send_inline($asset, isset($m[2]) ? (int) $m[2] : null);
}

// Feeds the asset picker in the editor. Carries video as well as images, with
// a kind on each one, because the video block needs to offer uploaded files and
// an image field must not offer them.
if ($path === '/admin/assets.json') {
    require_editor();
    $out = [];
    foreach (db_all('SELECT * FROM assets ORDER BY uploaded_at DESC') as $asset) {
        if (!asset_is_inline_media($asset)) {
            continue;
        }
        $out[] = [
            'id'    => (int) $asset['id'],
            'label' => $asset['label'],
            'file'  => $asset['original_name'],
            'url'   => asset_image_url($asset),
            'kind'  => asset_is_inline_video($asset) ? 'video' : 'image',
            'tags'  => (string) $asset['tags'],
        ];
    }
    json_out(['images' => $out]);
}

// -------------------------------------------------------------------- assets

if ($path === '/admin/assets') {
    require_editor();
    $assets = db_all(
        'SELECT a.*, (SELECT COUNT(*) FROM downloads d WHERE d.asset_id = a.id) AS downloads
           FROM assets a ORDER BY a.label, a.original_name'
    );
    view('admin/assets', [
        'title'  => 'Assets',
        'assets' => $assets,
        'tags'   => asset_all_tags(),
        'notice' => $_GET['notice'] ?? null,
    ], 'admin/layout');
    exit;
}

if ($path === '/admin/assets/upload' && $method === 'POST') {
    require_editor();
    csrf_check();
    $file = $_FILES['file'] ?? [];
    $error = asset_validation_error($file);
    if ($error) {
        redirect('/admin/assets?notice=' . urlencode($error));
    }

    $stored = asset_stored_name((string) $file['name']);
    if (!is_dir(asset_storage_dir())) {
        @mkdir(asset_storage_dir(), 0775, true);
    }
    if (!move_uploaded_file((string) $file['tmp_name'], asset_storage_dir() . '/' . $stored)) {
        redirect('/admin/assets?notice=' . urlencode('Could not save the file. Check storage permissions.'));
    }

    $mime = asset_detect_mime(asset_storage_dir() . '/' . $stored);
    asset_make_variants($stored, $mime);

    db_run(
        'INSERT INTO assets (stored_name, original_name, label, mime, bytes, version, tags, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $stored,
            basename((string) $file['name']),
            trim($_POST['label'] ?? '') ?: pathinfo((string) $file['name'], PATHINFO_FILENAME),
            $mime,
            (int) $file['size'],
            trim($_POST['version'] ?? '') ?: '1.0',
            asset_tags_string($_POST['tags'] ?? ''),
            current_user()['id'],
        ]
    );
    asset_clear_bundles();
    redirect('/admin/assets?notice=' . urlencode('Uploaded.'));
}

// Upload from inside the block editor, answering with JSON so the picker can
// select the new file straight away. Same validation and variant generation as
// the Assets screen; only the reply differs.
if ($path === '/admin/assets/add' && $method === 'POST') {
    require_editor();
    csrf_check();
    $file = $_FILES['file'] ?? [];
    $error = asset_validation_error($file);
    if ($error) {
        json_out(['ok' => false, 'error' => $error], 422);
    }

    $stored = asset_stored_name((string) $file['name']);
    if (!is_dir(asset_storage_dir())) {
        @mkdir(asset_storage_dir(), 0775, true);
    }
    if (!move_uploaded_file((string) $file['tmp_name'], asset_storage_dir() . '/' . $stored)) {
        json_out(['ok' => false, 'error' => 'Could not save the file. Check storage permissions.'], 500);
    }

    $mime = asset_detect_mime(asset_storage_dir() . '/' . $stored);
    asset_make_variants($stored, $mime);

    db_run(
        'INSERT INTO assets (stored_name, original_name, label, mime, bytes, version, tags, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $stored,
            basename((string) $file['name']),
            pathinfo((string) $file['name'], PATHINFO_FILENAME),
            $mime,
            (int) $file['size'],
            '1.0',
            '',
            current_user()['id'],
        ]
    );
    asset_clear_bundles();

    $asset = asset_find((int) db()->lastInsertId());
    if (!$asset || !asset_is_inline_media($asset)) {
        json_out(['ok' => false, 'error' => 'That file uploaded, but it cannot be shown in a page. Find it under Assets.'], 422);
    }
    json_out(['ok' => true, 'asset' => [
        'id'    => (int) $asset['id'],
        'label' => $asset['label'],
        'file'  => $asset['original_name'],
        'url'   => asset_image_url($asset),
        'kind'  => asset_is_inline_video($asset) ? 'video' : 'image',
        'tags'  => '',
    ]]);
}

if (preg_match('#^/admin/assets/(\d+)/update$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    $asset = db_one('SELECT id FROM assets WHERE id = ?', [$m[1]]);
    if (!$asset) {
        not_found();
    }
    db_run(
        'UPDATE assets SET label = ?, tags = ?, version = ? WHERE id = ?',
        [
            trim($_POST['label'] ?? '') ?: 'Untitled',
            asset_tags_string($_POST['tags'] ?? ''),
            trim($_POST['version'] ?? '') ?: '1.0',
            $asset['id'],
        ]
    );
    asset_clear_bundles();
    redirect('/admin/assets?notice=' . urlencode('Saved.'));
}

// Replacing keeps the same asset id, so links in pages never break.
if (preg_match('#^/admin/assets/(\d+)/replace$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    $asset = db_one('SELECT * FROM assets WHERE id = ?', [$m[1]]);
    if (!$asset) {
        not_found();
    }
    $file = $_FILES['file'] ?? [];
    $error = asset_validation_error($file);
    if ($error) {
        redirect('/admin/assets?notice=' . urlencode($error));
    }

    $stored = asset_stored_name((string) $file['name']);
    if (!move_uploaded_file((string) $file['tmp_name'], asset_storage_dir() . '/' . $stored)) {
        redirect('/admin/assets?notice=' . urlencode('Could not save the replacement file.'));
    }
    $old = asset_path($asset);
    $mime = asset_detect_mime(asset_storage_dir() . '/' . $stored);
    asset_make_variants($stored, $mime);

    db_run(
        'UPDATE assets SET stored_name = ?, original_name = ?, mime = ?, bytes = ?, version = ?, uploaded_at = NOW(), uploaded_by = ? WHERE id = ?',
        [
            $stored,
            basename((string) $file['name']),
            $mime,
            (int) $file['size'],
            trim($_POST['version'] ?? '') ?: $asset['version'],
            current_user()['id'],
            $asset['id'],
        ]
    );
    if (is_file($old)) {
        @unlink($old);
    }
    asset_remove_variants($asset['stored_name']);
    asset_clear_bundles();
    redirect('/admin/assets?notice=' . urlencode('Replaced. Anywhere this file is linked now serves the new version.'));
}

if (preg_match('#^/admin/assets/(\d+)/delete$#', $path, $m) && $method === 'POST') {
    require_editor();
    csrf_check();
    $asset = db_one('SELECT * FROM assets WHERE id = ?', [$m[1]]);
    if (!$asset) {
        not_found();
    }
    $file = asset_path($asset);
    db_run('DELETE FROM assets WHERE id = ?', [$asset['id']]);
    if (is_file($file)) {
        @unlink($file);
    }
    asset_remove_variants($asset['stored_name']);
    asset_clear_bundles();
    redirect('/admin/assets?notice=' . urlencode('Removed.'));
}

// ----------------------------------------------------------------- downloads

// Every file is served through here, so gating and logging always apply.
if (preg_match('#^/d/(\d+)$#', $path, $m)) {
    if (site_is_private($config) && !current_user()) {
        redirect('/admin/sign-in');
    }
    $asset = db_one('SELECT * FROM assets WHERE id = ?', [$m[1]]);
    if (!$asset) {
        not_found();
    }
    asset_record_download((int) $asset['id'], current_user()['id'] ?? null);
    asset_send_file(asset_path($asset), $asset['original_name'], $asset['mime']);
}

if (preg_match('#^/bundle/([a-z0-9\-]+)$#', $path, $m)) {
    if (site_is_private($config) && !current_user()) {
        redirect('/admin/sign-in');
    }
    if (!asset_zip_supported()) {
        http_response_code(501);
        exit('Bundles are not available on this server.');
    }
    $bundle = asset_bundle_path($m[1]);
    if (!$bundle) {
        not_found();
    }
    foreach (assets_by_tag($m[1]) as $asset) {
        asset_record_download((int) $asset['id'], current_user()['id'] ?? null);
    }
    asset_send_file($bundle, 'years-' . $m[1] . '.zip', 'application/zip');
}

if ($path === '/admin/migrate' && $method === 'POST') {
    require_admin();
    csrf_check();
    try {
        $ran = migrations_run();
        $notice = $ran ? 'Applied ' . implode(', ', $ran) : 'Nothing to apply.';
    } catch (Throwable $e) {
        error_log('migration failed: ' . $e->getMessage());
        $notice = 'Migration failed: ' . $e->getMessage();
    }
    redirect('/admin/settings?notice=' . urlencode($notice));
}

/**
 * Restoring from a backup. Two steps, always: the first reads the file and says
 * what it would do, the second does it. A restore is the most destructive thing
 * in the admin, and the review screen is the only place to find out that the
 * file is the wrong one before rather than after.
 */
if ($path === '/admin/restore' && $method !== 'POST') {
    require_admin();
    view('admin/restore', ['title' => 'Restore'], 'admin/layout');
    exit;
}

if ($path === '/admin/restore' && $method === 'POST') {
    require_admin();
    csrf_check();

    $upload = $_FILES['backup'] ?? null;
    if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        view('admin/restore', [
            'title' => 'Restore',
            'error' => ($upload['error'] ?? null) === UPLOAD_ERR_INI_SIZE
                ? 'That file is larger than the server accepts for an upload.'
                : 'No file arrived. Choose the JSON file you downloaded and try again.',
        ], 'admin/layout');
        exit;
    }

    // is_uploaded_file first: a path in $_FILES is not by itself proof that the
    // request put it there.
    $tmp = (string) $upload['tmp_name'];
    $json = is_uploaded_file($tmp) ? (string) file_get_contents($tmp) : '';
    $read = restore_read($json);

    if (!$read['ok']) {
        view('admin/restore', ['title' => 'Restore', 'error' => $read['error']], 'admin/layout');
        exit;
    }

    view('admin/restore', [
        'title'   => 'Restore',
        'payload' => $read['payload'],
        'plan'    => restore_plan($read['payload']),
        // Carried back to the apply step in the form, so the file is read once
        // and what is applied is exactly what was reviewed.
        'json'    => $json,
    ], 'admin/layout');
    exit;
}

if ($path === '/admin/restore/apply' && $method === 'POST') {
    require_admin();
    csrf_check();

    $read = restore_read((string) ($_POST['payload'] ?? ''));
    if (!$read['ok']) {
        view('admin/restore', ['title' => 'Restore', 'error' => $read['error']], 'admin/layout');
        exit;
    }

    $done = restore_apply($read['payload'], !empty($_POST['remove_extra']), current_user()['id']);

    view('admin/restore', [
        'title' => 'Restore',
        'done'  => $done,
    ], 'admin/layout');
    exit;
}

if ($path === '/admin/export') {
    require_admin();
    $name = 'years-brand-hub-' . date('Y-m-d') . '.json';
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    echo json_encode(export_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// The sample page inside the Appearance preview. Rendered by the real block
// renderers with the real stylesheet, so what is previewed cannot drift.
if ($path === '/admin/styles/preview') {
    require_admin();
    view('admin/style-preview', ['title' => 'Appearance preview'], null);
    exit;
}

if ($path === '/admin/styles') {
    require_admin();
    if ($method === 'POST') {
        csrf_check();

        // Putting everything back is a separate intent from saving, and it has
        // to clear the stored values rather than write the defaults over them,
        // so a default that changes later still reaches a site that never
        // overrode it.
        if (isset($_POST['reset_all'])) {
            foreach (array_keys(style_flat()) as $key) {
                setting_save('style.' . $key, '');
            }
            redirect('/admin/styles?notice=' . urlencode('Appearance put back to its defaults.'));
        }

        foreach (array_keys(style_flat()) as $key) {
            style_save($key, (string) ($_POST[$key] ?? ''));
        }
        redirect('/admin/styles?notice=' . urlencode('Appearance saved.'));
    }
    view('admin/styles', ['title' => 'Appearance', 'notice' => $_GET['notice'] ?? null], 'admin/layout');
    exit;
}

if ($path === '/admin/settings') {
    require_admin();
    if ($method === 'POST') {
        csrf_check();

        // Each form on this screen saves only its own settings. Writing all of
        // them on every post meant the Site form, which has no Areas fields,
        // silently turned the areas toggle off and reset both labels to their
        // defaults: rename your areas, then change the site name a week later,
        // and the renaming was gone. The forms used to carry hidden copies of
        // each other's values to work around it, which only held while the page
        // was freshly loaded.
        $form = $_POST['form'] ?? 'site';

        if ($form === 'areas') {
            setting_save('components_enabled', isset($_POST['components_enabled']) ? '1' : '0');
            setting_save('area_one_label', trim($_POST['area_one_label'] ?? '') ?: 'Brand');
            setting_save('area_two_label', trim($_POST['area_two_label'] ?? '') ?: 'Components');
            redirect('/admin/settings?notice=' . urlencode('Areas saved.'));
        }

        setting_save('require_login_to_view', isset($_POST['require_login_to_view']) ? '1' : '0');
        setting_save('site_name', trim($_POST['site_name'] ?? '') ?: 'Years brand hub');
        setting_save('footer_note', trim($_POST['footer_note'] ?? ''));
        setting_save('site_logo', (string) (int) ($_POST['site_logo'] ?? 0));
        redirect('/admin/settings?notice=Saved.');
    }
    view('admin/settings', [
        'title'   => 'Settings',
        'config'  => $config,
        'notice'  => $_GET['notice'] ?? null,
        'pending' => migrations_pending(),
    ], 'admin/layout');
    exit;
}

not_found();
