<?php
/** @var array $data @var array $options */
$density = block_option($options, 'density', 'normal');
?>
<section class="block block--spec">
  <?php if (!empty($data['heading'])): ?><h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3><?php endif; ?>
  <dl class="spec spec--<?= e($density) ?>">
    <?php foreach (($data['rows'] ?? []) as $i => $row): ?>
      <div class="spec__row">
        <dt<?= editable("rows.$i.label") ?>><?= e($row['label']) ?></dt>
        <dd>
          <span<?= editable("rows.$i.value") ?>><?= e($row['value']) ?></span>
          <?php if (!empty($row['note'])): ?><span class="spec__note"<?= editable("rows.$i.note") ?>><?= e($row['note']) ?></span><?php endif; ?>
        </dd>
      </div>
    <?php endforeach; ?>
  </dl>
</section>
