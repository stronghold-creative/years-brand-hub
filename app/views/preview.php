<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Preview</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/site.css')) ?>">
<style><?= style_css() ?></style>
</head>
<body class="is-preview"<?= style_body_attributes() ?>>
<main class="reader__body">
  <h1><?= e($page['title']) ?></h1>
  <?php if (!empty($page['summary'])): ?><p class="reader__summary"><?= e($page['summary']) ?></p><?php endif; ?>
  <?php render_blocks($blocks); ?>
</main>
<script src="<?= e(asset_url('/assets/js/site.js')) ?>"></script>
</body>
</html>
