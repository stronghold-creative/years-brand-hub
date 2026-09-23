<?php
/** @var array $data @var array $options */
// Body size is a number now, so "site default" means simply not overriding it.
$size    = block_option($options, 'size', 'inherit');
$align   = block_option($options, 'align', 'left');
$columns = block_option($options, 'columns', 'one');
?>
<section class="block block--text">
  <?php if (!empty($data['heading'])): ?>
    <h2><?= e($data['heading']) ?></h2>
  <?php endif; ?>
  <div class="prose prose--<?= e($size) ?> prose--<?= e($align) ?> prose--col-<?= e($columns) ?>">
    <?= paragraphs($data['body'] ?? '') ?>
  </div>
</section>
