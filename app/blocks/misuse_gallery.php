<?php
/** @var array $data @var array $options */
$columns = block_option($options, 'columns', 'two');
?>
<section class="block block--misuse">
  <?php if (!empty($data['heading'])): ?><h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3><?php endif; ?>
  <ul class="misuse misuse--<?= e($columns) ?>">
    <?php foreach (($data['items'] ?? []) as $item): ?>
      <?php $asset = ((int) ($item['asset'] ?? 0)) > 0 ? asset_find((int) $item['asset']) : null; ?>
      <li class="misuse__item misuse__item--<?= e($item['stance'] ?? 'do') ?>">
        <?php if ($asset): ?>
          <span class="misuse__frame">
            <img src="<?= e(asset_image_url($asset, 800)) ?>" alt="<?= e($item['caption'] ?? '') ?>" loading="lazy">
          </span>
        <?php endif; ?>
        <p class="misuse__verdict"><?= ($item['stance'] ?? 'do') === 'do' ? 'Correct' : 'Incorrect' ?></p>
        <?php if (!empty($item['caption'])): ?><p class="misuse__why"><?= e($item['caption']) ?></p><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
