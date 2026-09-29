<?php
/** @var array $data @var array $options */
$icons = assets_by_tag($data['tag'] ?? '');
$size  = block_option($options, 'size', 'medium');
$names = block_option($options, 'names', 'show');
?>
<section class="block block--icons">
  <?php if (!empty($data['heading'])): ?><h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3><?php endif; ?>
  <?php if (!$icons): ?>
    <?php // An empty grid is almost always a tag that does not match, so the
          // empty state says what to do rather than only that nothing is here. ?>
    <p class="block__empty">
      <?php if (trim((string) ($data['tag'] ?? '')) === ''): ?>
        This grid has no tag yet. Give it the tag you used on the icons in Assets
        and every one carrying it will appear here.
      <?php else: ?>
        Nothing in Assets is tagged <strong><?= e($data['tag']) ?></strong>.
        Tags are matched exactly, so check the spelling against the asset.
      <?php endif; ?>
    </p>
  <?php else: ?>
    <ul class="icons icons--<?= e($size) ?>">
      <?php foreach ($icons as $icon): ?>
        <?php if (!asset_is_inline_image($icon)) { continue; } ?>
        <li class="icons__item">
          <span class="icons__tile">
            <img src="<?= e(asset_image_url($icon, 400)) ?>" alt="<?= e($icon['label']) ?>" loading="lazy">
          </span>
          <?php if ($names === 'show'): ?>
            <span class="icons__name"><?= e($icon['label']) ?></span>
          <?php endif; ?>
          <?php // /d/ is the gated, logged download route the other blocks use. ?>
          <a class="icons__get" href="/d/<?= (int) $icon['id'] ?>">Download</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php if (!empty($data['note'])): ?><p class="block__note"<?= editable('note') ?>><?= e($data['note']) ?></p><?php endif; ?>
</section>
