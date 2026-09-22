<?php
declare(strict_types=1);

/** @var array $config */

$path   = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---------------------------------------------------------------- public site

if ($path === '/') {
    if (!empty($config['site']['require_login_to_view']) && !current_user()) {
        redirect('/admin/sign-in');
    }
    $pages = db_all("SELECT slug, title, section, summary FROM pages WHERE status = 'published' ORDER BY section, sort_order, title");
    $sections = [];
    foreach ($pages as $page) {
        $sections[$page['section']][] = $page;
    }
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

if (preg_match('#^/g/([a-z0-9\-]+)$#', $path, $m)) {
    if (!empty($config['site']['require_login_to_view']) && !current_user()) {
        redirect('/admin/sign-in');
    }
    $page = db_one('SELECT * FROM pages WHERE slug = ?', [$m[1]]);
    if (!$page || ($page['status'] !== 'published' && !can_edit())) {
        not_found();
    }
    $nav = db_all("SELECT slug, title, section FROM pages WHERE status = 'published' ORDER BY section, sort_order, title");
    view('page', [
        'title'  => $page['title'],
        'page'   => $page,
        'blocks' => json_decode($page['blocks'], true) ?: [],
        'nav'    => $nav,
        'config' => $config,
    ]);
    exit;
}

// ---------------------------------------------------------------------- admin

if ($path === '/admin/sign-in') {
    if ($method === 'POST') {
        csrf_check();
        if (attempt_sign_in($_POST['email'] ?? '', $_POST['password'] ?? '')) {
            redirect('/admin');
        }
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
    $pages = db_all('SELECT id, slug, title, section, status, updated_at FROM pages ORDER BY section, sort_order, title');
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
        'title'  => $page['title'],
        'page'   => $page,
        'blocks' => json_decode($page['blocks'], true) ?: [],
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
    db_run(
        'UPDATE pages SET title = ?, section = ?, summary = ?, status = ?, blocks = ?, updated_by = ? WHERE id = ?',
        [
            $title,
            trim($payload['section'] ?? '') ?: 'General',
            str_trim_to(trim($payload['summary'] ?? ''), 400),
            ($payload['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
            $blocks,
            $userId,
            $page['id'],
        ]
    );
    db_run(
        'INSERT INTO page_versions (page_id, title, blocks, created_by) VALUES (?, ?, ?, ?)',
        [$page['id'], $title, $blocks, $userId]
    );
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
        'title'  => $page['title'],
        'page'   => $page,
        'blocks' => json_decode($page['blocks'], true) ?: [],
        'config' => $config,
    ], null);
    exit;
}

if (preg_match('#^/admin/pages/(\d+)/delete$#', $path, $m) && $method === 'POST') {
    require_admin();
    csrf_check();
    db_run('DELETE FROM pages WHERE id = ?', [$m[1]]);
    redirect('/admin');
}

not_found();
