<?php
/** @var array $data @var array $options */
$density = block_option($options, 'density', 'normal');
$first   = block_option($options, 'first', 'closed');
?>
<section class="block block--accordion">
  <?php if (!empty($data['heading'])): ?><h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3><?php endif; ?>
  <div class="accordion accordion--<?= e($density) ?>">
    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
      <?php // <details> needs no JavaScript and is keyboard accessible by default. ?>
      <details class="accordion__item"<?= ($first === 'open' && $i === 0) ? ' open' : '' ?>>
        <?php // The summary carries the open and close behaviour, so the
              // editable region is a span inside it: making the summary itself
              // editable means every click to place the cursor also toggles it. ?>
        <summary class="accordion__head"><span<?= editable("items.$i.question") ?>><?= e($item['question']) ?></span></summary>
        <div class="accordion__body prose"<?= editable("items.$i.answer", true) ?>><?= paragraphs($item['answer'] ?? '') ?></div>
      </details>
    <?php endforeach; ?>
  </div>
</section>
