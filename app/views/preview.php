<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Preview</title>
<?php // Same font source as the public page, or the preview is not a preview. ?>
<?php if (!fonts_are_local()): ?>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/site.css')) ?>">
<style><?= style_css() ?></style>
</head>
<body class="is-preview"<?= style_body_attributes() ?>>
<?php
// A hero at the top of a page becomes the section opener on the live site, so
// the preview shows it the same way: full width, above everything, outside the
// reading column. It is lifted off the list to do that, which is why the rest
// are rendered from index 1 onwards. Without that offset every block in the
// canvas reported an index one too low and every click selected its neighbour.
$opener = null;
if (($blocks[0]['type'] ?? '') === 'hero') {
    $opener = array_shift($blocks);
}
?>
<?php if ($opener): ?>
  <div class="opener"<?= !empty($editing) ? ' data-block="0"' : '' ?>><?php render_bare_block($opener, !empty($editing)); ?></div>
  <p class="opener__note">This opens the section on the live site.</p>
<?php endif; ?>
<main class="reader__body">
  <?php if (!$opener): ?>
    <h1><?= e($page['title']) ?></h1>
    <?php if (!empty($page['summary'])): ?><p class="reader__summary"><?= e($page['summary']) ?></p><?php endif; ?>
  <?php endif; ?>
  <?php render_blocks($blocks, !empty($editing), $opener ? 1 : 0); ?>
</main>
<script src="<?= e(asset_url('/assets/js/vendor/motion.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/motion-ui.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/site.js')) ?>"></script>
<?php if (!empty($editing)): ?>
  <script src="<?= e(asset_url('/assets/js/preview-bridge.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
