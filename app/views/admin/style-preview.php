<?php
/**
 * The sample page inside the Appearance preview.
 *
 * It is rendered by the real block renderers with the real stylesheet, for the
 * same reason the editor's canvas is: one implementation, so what is previewed
 * cannot drift from what publishes. Every block here is one that needs nothing
 * from the database, so the preview is cheap and cannot fail on a missing
 * asset.
 *
 * The parent sends values as they are changed and this applies them to :root,
 * which is where style_css() puts them, so the preview responds without a
 * round trip and without a second copy of the rules.
 */
$sample = sanitise_blocks([
    [
        'type' => 'rich_text',
        'data' => [
            'heading' => 'Tone of voice',
            'body'    => "We write the way we talk to someone who knows dogs but not us.\n\nShort sentences. Plain words. Nothing we would not say out loud.",
        ],
        'settings' => [], 'options' => [],
    ],
    [
        'type' => 'colour_palette',
        'data' => [
            'heading' => 'Core colours',
            'colours' => [
                ['name' => 'Purple', 'hex' => '#7257B7', 'cmyk' => '', 'pantone' => '2096 C', 'usage' => ''],
                ['name' => 'Pink',   'hex' => '#CC4767', 'cmyk' => '', 'pantone' => '1925 C', 'usage' => ''],
                ['name' => 'Gold',   'hex' => '#FFD47A', 'cmyk' => '', 'pantone' => '1345 C', 'usage' => ''],
            ],
        ],
        'settings' => [], 'options' => [],
    ],
    [
        'type' => 'callout',
        'data' => ['tone' => 'warning', 'body' => 'Never stretch the mark to fit a space.'],
        'settings' => [], 'options' => [],
    ],
    [
        'type' => 'spec_table',
        'data' => [
            'heading' => 'Specification',
            'rows'    => [
                ['label' => 'Minimum size', 'value' => '24mm wide', 'note' => 'Print'],
                ['label' => 'Clear space',  'value' => 'Half the mark', 'note' => ''],
            ],
        ],
        'settings' => [], 'options' => [],
    ],
]);
?>
<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Appearance preview</title>
<?= site_icon_head() ?>
<?= font_head() ?>
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/site.css')) ?>">
<style id="live"><?= style_css() ?></style>
<style>
  /* The preview is a page fragment, not a page, so it carries no masthead and
     no rail and sits closer to the edges than the real thing. */
  body { padding: 1.5rem; }
  .zone { margin-bottom: 1.5rem; }
</style>
</head>
<body<?= style_body_attributes() ?>>
<?php render_blocks($sample); ?>
<script>
(function () {
  'use strict';

  // Values arrive from the Appearance screen as they are changed. Both sides
  // check the origin, as the editor's canvas does.
  window.addEventListener('message', function (event) {
    if (event.origin !== window.location.origin) return;
    var data = event.data;
    if (!data || data.from !== 'styles') return;

    if (data.css) {
      var lines = [];
      Object.keys(data.css).forEach(function (name) {
        lines.push('  ' + name + ': ' + data.css[name] + ';');
      });
      document.getElementById('live').textContent = ':root {\n' + lines.join('\n') + '\n}';
    }

    if (data.body) {
      Object.keys(data.body).forEach(function (name) {
        document.body.setAttribute('data-' + name, data.body[name]);
      });
    }
  });

  window.parent.postMessage({ from: 'style-preview', action: 'ready' }, window.location.origin);
})();
</script>
</body>
</html>
