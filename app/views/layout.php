<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Years brand hub') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/site.css')) ?>">
<style><?= style_css() ?></style>
</head>
<body<?= style_body_attributes() ?>>
<header class="masthead">
  <a class="masthead__mark" href="/">Years</a>
  <p class="masthead__title">Brand guidelines</p>
  <nav class="masthead__tools">
    <?php if (can_edit()): ?>
      <a href="/admin">Edit</a>
      <a href="/admin/sign-out">Sign out</a>
    <?php else: ?>
      <a href="/admin/sign-in">Sign in</a>
    <?php endif; ?>
  </nav>
</header>
<?= $content ?>
<footer class="foot">
  <p><?= e((string) setting('footer_note', 'Questions about anything here, or something missing, speak to the brand team.')) ?></p>
</footer>
<script src="<?= e(asset_url('/assets/js/site.js')) ?>"></script>
</body>
</html>
