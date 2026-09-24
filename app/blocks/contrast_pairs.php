<?php
/** @var array $data @var array $options */
$layout = block_option($options, 'layout', 'cards');
?>
<section class="block block--contrast">
  <?php if (!empty($data['heading'])): ?><h3><?= e($data['heading']) ?></h3><?php endif; ?>
  <ul class="contrast contrast--<?= e($layout) ?>">
    <?php foreach (($data['pairs'] ?? []) as $pair): ?>
      <?php
        // Measured, not asserted: the ratio is calculated from the two colours.
        $ratio   = contrast_ratio((string) $pair['foreground'], (string) $pair['background']);
        $verdict = contrast_verdict($ratio);
      ?>
      <li class="contrast__item">
        <div class="contrast__sample" style="background: <?= e($pair['background']) ?>; color: <?= e($pair['foreground']) ?>">
          <?= e($pair['sample'] ?? 'The quick brown fox') ?>
        </div>
        <div class="contrast__meta">
          <?php if (!empty($pair['label'])): ?><h4><?= e($pair['label']) ?></h4><?php endif; ?>
          <p class="contrast__ratio"><?= e(number_format($ratio, 2)) ?>:1</p>
          <p class="contrast__verdict contrast__verdict--<?= $verdict['pass'] ? 'pass' : 'fail' ?>">
            <?= e($verdict['level']) ?> <span><?= e($verdict['note']) ?></span>
          </p>
          <p class="contrast__values"><?= e($pair['foreground']) ?> on <?= e($pair['background']) ?></p>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
