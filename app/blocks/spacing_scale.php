<?php
/** @var array $data @var array $options */
$show = block_option($options, 'bar', 'bars');
?>
<section class="block block--spacing">
  <?php if (!empty($data['heading'])): ?><h3><?= e($data['heading']) ?></h3><?php endif; ?>
  <ul class="scale scale--<?= e($show) ?>">
    <?php foreach (($data['steps'] ?? []) as $step): ?>
      <li class="scale__row">
        <span class="scale__name"><?= e($step['name']) ?></span>
        <?php if ($show === 'bars'): ?>
          <?php // The bar is drawn at the real measurement, so the scale is visible, not just stated. ?>
          <span class="scale__bar" style="width: <?= e(preg_replace('/[^0-9a-z.%]/i', '', (string) $step['value'])) ?>"></span>
        <?php endif; ?>
        <span class="scale__value"><?= e($step['value']) ?></span>
        <?php if (!empty($step['use'])): ?><span class="scale__use"><?= e($step['use']) ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
