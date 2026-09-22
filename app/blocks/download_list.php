<?php
/** @var array $data */
$assets = assets_by_tag($data['tag'] ?? '');
?>
<section class="block block--downloads">
  <?php if (!empty($data['heading'])): ?>
    <h2><?= e($data['heading']) ?></h2>
  <?php endif; ?>
  <?php if (!empty($data['intro'])): ?>
    <div class="prose"><?= paragraphs($data['intro']) ?></div>
  <?php endif; ?>

  <?php if (!$assets): ?>
    <p class="empty">Nothing tagged <?= e($data['tag'] ?? '') ?> yet.</p>
  <?php else: ?>
    <ul class="downloads">
      <?php foreach ($assets as $asset): ?>
        <li class="download">
          <a class="download__link" href="/d/<?= (int) $asset['id'] ?>">
            <span class="download__name"><?= e($asset['label']) ?></span>
            <span class="download__meta">
              <?= e(strtoupper(asset_extension($asset['original_name']))) ?>
              &middot; <?= e(asset_human_size((int) $asset['bytes'])) ?>
              &middot; v<?= e($asset['version']) ?>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if (($data['bundle'] ?? 'yes') === 'yes' && asset_zip_supported() && count($assets) > 1): ?>
      <p class="downloads__all">
        <a href="/bundle/<?= e(strtolower(trim($data['tag']))) ?>">Download all <?= count($assets) ?> files</a>
      </p>
    <?php endif; ?>
  <?php endif; ?>
</section>
