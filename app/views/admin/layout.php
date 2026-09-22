<?php
$currentUser = current_user();
$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$nav = [
    '/admin'          => 'Pages',
    '/admin/assets'   => 'Assets',
    '/admin/users'    => 'People',
    '/admin/settings' => 'Settings',
];
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
<body>
<header class="m3-top-bar">
  <a class="m3-top-bar__title" href="/admin">Years brand hub</a>
  <nav class="m3-top-bar__nav">
    <?php foreach ($nav as $href => $label): ?>
      <?php if ($href === '/admin/users' || $href === '/admin/settings'): ?>
        <?php if (($currentUser['role'] ?? '') !== 'admin') { continue; } ?>
      <?php endif; ?>
      <a class="m3-navlink m3-state" href="<?= $href ?>"
         <?= str_starts_with($path, $href) && ($href !== '/admin' || $path === '/admin') ? 'aria-current="page"' : '' ?>><?= $label ?></a>
    <?php endforeach; ?>
    <a class="m3-navlink m3-state" href="/">View site</a>
    <span class="m3-top-bar__user"><?= e($currentUser['name'] ?? '') ?></span>
    <a class="m3-navlink m3-state" href="/admin/sign-out">Sign out</a>
  </nav>
</header>
<?= $content ?>
<script>window.CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="<?= e(asset_url('/assets/js/editor.js')) ?>"></script>
</body>
</html>
