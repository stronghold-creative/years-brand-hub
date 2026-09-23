<?php
$currentUser = current_user();
$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

$destinations = [
    ['href' => '/admin',          'label' => 'Pages',    'icon' => 'pages',    'match' => ['/admin', '/admin/pages']],
    ['href' => '/admin/assets',   'label' => 'Assets',   'icon' => 'assets',   'match' => ['/admin/assets']],
    ['href' => '/admin/users',    'label' => 'People',   'icon' => 'people',   'match' => ['/admin/users'], 'admin' => true],
    ['href' => '/admin/styles',   'label' => 'Appearance', 'icon' => 'palette-ui', 'match' => ['/admin/styles'], 'admin' => true],
    ['href' => '/admin/settings', 'label' => 'Settings', 'icon' => 'settings', 'match' => ['/admin/settings'], 'admin' => true],
];

$isCurrent = function (array $item) use ($path): bool {
    foreach ($item['match'] as $prefix) {
        if ($path === $prefix || ($prefix !== '/admin' && str_starts_with($path, $prefix . '/'))) {
            return true;
        }
    }
    return false;
};
?>
<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title><?= e($title ?? 'Admin') ?> &middot; Years brand hub</title>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/admin.css')) ?>">
</head>
<body class="m3-shell<?= !empty($fullBleed) ? ' m3-shell--full' : '' ?>">

<a class="m3-skip" href="#main">Skip to content</a>

<!-- Navigation. A drawer on wide screens, a sheet behind a button on narrow. -->
<nav class="m3-drawer" id="drawer" aria-label="Sections">
  <div class="m3-drawer__brand">
    <span class="m3-drawer__mark">Years</span>
    <span class="m3-drawer__sub">Brand hub</span>
    <button class="m3-icon-button m3-state m3-drawer__dismiss" type="button" data-drawer-close aria-label="Close navigation">
      <?= ui_icon('close') ?>
    </button>
  </div>

  <ul class="m3-drawer__list">
    <?php foreach ($destinations as $item): ?>
      <?php if (!empty($item['admin']) && ($currentUser['role'] ?? '') !== 'admin') { continue; } ?>
      <li>
        <a class="m3-drawer__item m3-state" href="<?= $item['href'] ?>"
           <?= $isCurrent($item) ? 'aria-current="page"' : '' ?>>
          <?= ui_icon($item['icon']) ?>
          <span><?= $item['label'] ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <!-- Account and context, deliberately separated from the destinations. -->
  <div class="m3-drawer__foot">
    <a class="m3-drawer__item m3-drawer__item--quiet m3-state" href="/" target="_blank" rel="noopener">
      <?= ui_icon('view') ?><span>View site</span>
    </a>
    <div class="m3-account">
      <span class="m3-account__avatar" aria-hidden="true"><?= e(str_initial($currentUser['name'] ?? '')) ?></span>
      <span class="m3-account__who">
        <strong><?= e($currentUser['name'] ?? '') ?></strong>
        <span><?= e(ucfirst($currentUser['role'] ?? '')) ?></span>
      </span>
      <a class="m3-icon-button m3-state" href="/admin/sign-out" aria-label="Sign out" title="Sign out"><?= ui_icon('signout') ?></a>
    </div>
  </div>
</nav>

<div class="m3-scrim m3-scrim--drawer" data-drawer-close hidden></div>

<div class="m3-shell__body">
  <!-- Narrow screens only: a bar carrying the menu button and the page title. -->
  <header class="m3-compact-bar">
    <button class="m3-icon-button m3-state" type="button" data-drawer-open aria-label="Open navigation" aria-controls="drawer">
      <?= ui_icon('menu') ?>
    </button>
    <span class="m3-compact-bar__title"><?= e($title ?? '') ?></span>
  </header>

  <main id="main" class="m3-shell__main">
    <?= $content ?>
  </main>
</div>

<script>window.CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="<?= e(asset_url('/assets/js/admin.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/editor.js')) ?>"></script>
</body>
</html>
