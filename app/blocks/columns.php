<?php
/**
 * Two, three or four columns, side by side.
 *
 * Every part of a column is optional except its heading, so one block covers a
 * row of values with icons, a set of principles numbered above the heading, and
 * three cards leading with a picture. Three block types that differed only in
 * what they showed would be three things to maintain and three to choose between.
 *
 * The column count is a maximum rather than a promise: `--across` is capped at
 * the number of items, because three columns holding two items leaves a hole,
 * and a hole looks like something failed to load.
 *
 * @var array $data @var array $options
 */
$across  = (int) block_option($options, 'across', '3');
$style   = block_option($options, 'style', 'plain');
$align   = block_option($options, 'align', 'left');
$iconSize = block_option($options, 'icon_size', 'medium');

// Keys are kept rather than renumbered: an editable() path is a route into the
// stored data, and a filtered, renumbered list would edit the wrong column.
$items = array_filter(
    (array) ($data['items'] ?? []),
    fn($item) => trim((string) ($item['title'] ?? '')) !== ''
              || trim((string) ($item['body'] ?? '')) !== ''
);
if (!$items) {
    return;
}
$across = max(1, min($across, count($items)));
?>
<section class="block block--columns cols cols--<?= e($style) ?> cols--<?= e($align) ?> cols--icon-<?= e($iconSize) ?>"
         style="--across: <?= $across ?>">
  <?php if (!empty($data['heading'])): ?>
    <h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3>
  <?php endif; ?>
  <?php if (!empty($data['intro'])): ?>
    <div class="cols__intro"<?= editable('intro', true) ?>><?= paragraphs($data['intro']) ?></div>
  <?php endif; ?>

  <ul class="cols__list">
    <?php foreach ($items as $i => $item): ?>
      <?php
        $image = ((int) ($item['image'] ?? 0)) > 0 ? asset_find((int) $item['image']) : null;
        $icon  = ((int) ($item['icon'] ?? 0)) > 0 ? asset_find((int) $item['icon']) : null;
        // Only http and https become links, as everywhere else here; a path on
        // this site is allowed because it cannot leave it.
        $url   = trim((string) ($item['link_url'] ?? ''));
        $safe  = preg_match('~^(https?://|/)[^\s"\'<>]*$~i', $url) === 1 ? $url : '';
        $label = trim((string) ($item['link_text'] ?? ''));
      ?>
      <li class="cols__item">
        <?php if ($image && asset_is_inline_image($image)): ?>
          <?php // An image leads the column and replaces the icon. Both set is a
                // change of mind mid-edit rather than a layout anyone wants. ?>
          <span class="cols__image">
            <img src="<?= e(asset_image_url($image, 800)) ?>" alt="" loading="lazy" decoding="async">
          </span>
        <?php elseif ($icon && asset_is_inline_image($icon)): ?>
          <span class="cols__icon">
            <img src="<?= e(asset_image_url($icon, 200)) ?>" alt="" loading="lazy" decoding="async">
          </span>
        <?php endif; ?>

        <?php if (!empty($item['eyebrow'])): ?>
          <span class="cols__eyebrow"<?= editable("items.$i.eyebrow") ?>><?= e($item['eyebrow']) ?></span>
        <?php endif; ?>

        <h4 class="cols__title"<?= editable("items.$i.title") ?>><?= e($item['title'] ?? '') ?></h4>

        <?php if (!empty($item['body'])): ?>
          <div class="cols__body"<?= editable("items.$i.body", true) ?>><?= paragraphs($item['body']) ?></div>
        <?php endif; ?>

        <?php if ($safe !== '' && $label !== ''): ?>
          <a class="cols__link" href="<?= e($safe) ?>"<?= str_starts_with($safe, 'http') ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= e($label) ?></a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
