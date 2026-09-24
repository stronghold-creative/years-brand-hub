<?php /** @var array $data */ ?>
<?php $spec = block_option($options, 'spec', 'beside'); ?>
<section class="block block--type block--spec-<?= e($spec) ?>">
  <?php if (!empty($data['heading'])): ?>
    <h3><?= e($data['heading']) ?></h3>
  <?php endif; ?>
  <?php foreach (($data['styles'] ?? []) as $style): ?>
    <article class="specimen">
      <p class="specimen__sample" style="font-family: <?= e($style['family']) ?>, Poppins, sans-serif;
         font-weight: <?= e($style['weight'] ?: '400') ?>;
         font-size: <?= e($style['size'] ?: '2rem') ?>;
         line-height: <?= e($style['line_height'] ?: '1.2') ?>;
         letter-spacing: <?= e($style['tracking'] ?: 'normal') ?>;">
        <?= e($style['sample']) ?>
      </p>
      <div class="specimen__spec">
        <h4><?= e($style['name']) ?></h4>
        <p><?= e($style['family']) ?> <?= e($style['weight']) ?> &middot; <?= e($style['size']) ?><?php
          if (!empty($style['line_height'])) { echo ' / ' . e($style['line_height']); } ?></p>
        <?php if (!empty($style['use_for'])): ?><p class="specimen__use"><?= e($style['use_for']) ?></p><?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</section>
