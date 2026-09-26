<?php
/** @var array $data @var array $options */
$density = block_option($options, 'density', 'normal');
$first   = block_option($options, 'first', 'closed');
?>
<section class="block block--accordion">
  <?php if (!empty($data['heading'])): ?><h3><?= e($data['heading']) ?></h3><?php endif; ?>
  <div class="accordion accordion--<?= e($density) ?>">
    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
      <?php // <details> needs no JavaScript and is keyboard accessible by default. ?>
      <details class="accordion__item"<?= ($first === 'open' && $i === 0) ? ' open' : '' ?>>
        <summary class="accordion__head"><?= e($item['question']) ?></summary>
        <div class="accordion__body prose"><?= paragraphs($item['answer'] ?? '') ?></div>
      </details>
    <?php endforeach; ?>
  </div>
</section>
