<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Admin') ?> &middot; Years brand hub</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/admin.css')) ?>">
</head>
<body class="admin">
<header class="bar">
  <a class="bar__mark" href="/admin">Years brand hub</a>
  <nav class="bar__nav">
    <a href="/admin">Pages</a>
    <a href="/admin/assets">Assets</a>
<?php if ((current_user()['role'] ?? '') === 'admin'): ?>
    <a href="/admin/users">People</a>
    <a href="/admin/settings">Settings</a>
<?php endif; ?>
    <a href="/">View site</a>
    <span class="bar__who"><?= e(current_user()['name'] ?? '') ?></span>
    <a href="/admin/sign-out">Sign out</a>
  </nav>
</header>
<?= $content ?>
<script>window.CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="<?= e(asset_url('/assets/js/editor.js')) ?>"></script>
</body>
</html>
