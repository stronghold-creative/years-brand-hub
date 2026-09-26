<?php /** @var array $data */ ?>
<?php $spec = block_option($options, 'spec', 'beside'); ?>
<section class="block block--type block--spec-<?= e($spec) ?>">
  <?php if (!empty($data['heading'])): ?>
    <h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3>
  <?php endif; ?>
  <?php foreach (($data['styles'] ?? []) as $i => $style): ?>
    <article class="specimen">
      <p class="specimen__sample" style="font-family: <?= e($style['family']) ?>, Poppins, sans-serif;
         font-weight: <?= e($style['weight'] ?: '400') ?>;
         font-size: <?= e($style['size'] ?: '2rem') ?>;
         line-height: <?= e($style['line_height'] ?: '1.2') ?>;
         letter-spacing: <?= e($style['tracking'] ?: 'normal') ?>;">
        <span<?= editable("styles.$i.sample") ?>><?= e($style['sample']) ?></span>
      </p>
      <div class="specimen__spec">
        <h4<?= editable("styles.$i.name") ?>><?= e($style['name']) ?></h4>
        <p><?= e($style['family']) ?> <?= e($style['weight']) ?> &middot; <?= e($style['size']) ?><?php
          if (!empty($style['line_height'])) { echo ' / ' . e($style['line_height']); } ?></p>
        <?php // The family, weight and size are not editable here: each one is
              // written straight into the sample's style attribute, so a
              // half-typed value would break the specimen as it was typed. ?>
        <?php if (!empty($style['use_for'])): ?><p class="specimen__use"<?= editable("styles.$i.use_for") ?>><?= e($style['use_for']) ?></p><?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</section>
