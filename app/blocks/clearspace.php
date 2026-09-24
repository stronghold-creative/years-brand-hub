<?php
/** @var array $data @var array $options */
$asset = ((int) ($data['asset'] ?? 0)) > 0 ? asset_find((int) $data['asset']) : null;
$ratio = (int) ($data['ratio'] ?? 50);
?>
<section class="block block--clearspace">
  <?php if (!empty($data['heading'])): ?><h3><?= e($data['heading']) ?></h3><?php endif; ?>
  <?php if ($asset): ?>
    <figure class="clearspace" style="--pad: <?= $ratio ?>%">
      <div class="clearspace__frame">
        <div class="clearspace__mark">
          <img src="<?= e(asset_image_url($asset, 800)) ?>" alt="<?= e($data['heading'] ?? 'The mark') ?>" loading="lazy">
        </div>
      </div>
      <?php if (!empty($data['measure'])): ?>
        <figcaption class="clearspace__measure"><?= e($data['measure']) ?></figcaption>
      <?php endif; ?>
    </figure>
  <?php endif; ?>
  <?php if (!empty($data['note'])): ?><p class="block__note"><?= e($data['note']) ?></p><?php endif; ?>
</section>
