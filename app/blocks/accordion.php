<?php
/** @var array $data @var array $options */
?>
<section class="block block--accordion">
  <?php if (!empty($data['heading'])): ?><h3><?= e($data['heading']) ?></h3><?php endif; ?>
  <div class="accordion">
    <?php foreach (($data['items'] ?? []) as $item): ?>
      <?php // <details> needs no JavaScript and is keyboard accessible by default. ?>
      <details class="accordion__item">
        <summary class="accordion__head"><?= e($item['question']) ?></summary>
        <div class="accordion__body prose"><?= paragraphs($item['answer'] ?? '') ?></div>
      </details>
    <?php endforeach; ?>
  </div>
</section>
