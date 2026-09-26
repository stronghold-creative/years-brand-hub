<?php
/** @var array $data @var array $options */
$show = block_option($options, 'bar', 'bars');
?>
<section class="block block--spacing">
  <?php if (!empty($data['heading'])): ?><h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3><?php endif; ?>
  <ul class="scale scale--<?= e($show) ?>">
    <?php foreach (($data['steps'] ?? []) as $i => $step): ?>
      <li class="scale__row">
        <span class="scale__name"<?= editable("steps.$i.name") ?>><?= e($step['name']) ?></span>
        <?php if ($show === 'bars'): ?>
          <?php // The bar is drawn at the real measurement, so the scale is visible, not just stated. ?>
          <span class="scale__bar" style="width: <?= e(preg_replace('/[^0-9a-z.%]/i', '', (string) $step['value'])) ?>"></span>
        <?php endif; ?>
        <?php // The value is not editable here: it is also the width of the bar
              // beside it, so a half-typed measurement would redraw the scale
              // on every keystroke. ?>
        <span class="scale__value"><?= e($step['value']) ?></span>
        <?php if (!empty($step['use'])): ?><span class="scale__use"<?= editable("steps.$i.use") ?>><?= e($step['use']) ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
