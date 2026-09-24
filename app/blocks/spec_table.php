<?php
/** @var array $data @var array $options */
$density = block_option($options, 'density', 'normal');
?>
<section class="block block--spec">
  <?php if (!empty($data['heading'])): ?><h3><?= e($data['heading']) ?></h3><?php endif; ?>
  <dl class="spec spec--<?= e($density) ?>">
    <?php foreach (($data['rows'] ?? []) as $row): ?>
      <div class="spec__row">
        <dt><?= e($row['label']) ?></dt>
        <dd>
          <?= e($row['value']) ?>
          <?php if (!empty($row['note'])): ?><span class="spec__note"><?= e($row['note']) ?></span><?php endif; ?>
        </dd>
      </div>
    <?php endforeach; ?>
  </dl>
</section>
