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

// Design tokens generated from the guidelines themselves.
if ($path === '/tokens.css') {
    header('Content-Type: text/css');
    $rows = db_all("SELECT blocks FROM pages WHERE status = 'published'");
    echo ":root {\n";
    foreach ($rows as $row) {
        foreach (json_decode($row['blocks'], true) ?: [] as $block) {
            if (($block['type'] ?? '') !== 'colour_palette') {
                continue;
            }
            foreach ($block['data']['colours'] ?? [] as $colour) {
                printf("  --%s: %s;\n", slugify($colour['name']), $colour['hex']);
            }
        }
    }
    echo "}\n";
    exit;
}

if ($path === '/components') {
    if (site_is_private($config) && !current_user()) {
        redirect('/admin/sign-in');
    }
    if (!components_enabled()) {
        not_found();
    }
    view('components', ['title' => 'Components', 'currentArea' => 'components', 'config' => $config]);
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
        'blocks'    => json_decode($page['blocks'], true) ?: [],
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
        'blocks'  => json_decode($page['blocks'], true) ?: [],
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
