<?php
/**
 * The canvas for a component's guidance: the blocks, rendered by PHP exactly as
 * the public component page renders them, so the preview cannot drift from the
 * result. The same arrangement as a page's preview, without the hero-opener
 * handling — a component's guidance has no section opener.
 *
 * Expects: $component, $blocks, $editing.
 */
?>
<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Guidance preview</title>
<?= site_icon_head() ?>
<?= font_head() ?>
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/site.css')) ?>">
<style><?= style_css() ?></style>
<script>document.documentElement.classList.add('js-hero');</script>
</head>
<body class="is-preview"<?= style_body_attributes() ?>>
<main class="reader__body component">
  <?php if (!$blocks): ?>
    <p class="component__empty">Nothing written yet. Add a block from the outline —
       what this component is for, when not to use it, anything the spec table
       cannot say.</p>
  <?php endif; ?>
  <?php render_blocks($blocks, !empty($editing)); ?>
</main>
<script src="<?= e(asset_url('/assets/js/vendor/motion.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/motion-ui.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/site.js')) ?>"></script>
<?php if (!empty($editing)): ?>
  <script src="<?= e(asset_url('/assets/js/preview-bridge.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
