<?php
/** @var array $data @var array $options */
$style = block_option($options, 'style', 'box');
?>
<section class="block block--checklist">
  <?php if (!empty($data['heading'])): ?><h3><?= e($data['heading']) ?></h3><?php endif; ?>
  <ol class="checks checks--<?= e($style) ?>">
    <?php foreach (($data['items'] ?? []) as $item): ?>
      <li class="checks__item">
        <span class="checks__mark" aria-hidden="true"></span>
        <span class="checks__body">
          <span class="checks__text"><?= e($item['item']) ?></span>
          <?php if (!empty($item['note'])): ?><span class="checks__note"><?= e($item['note']) ?></span><?php endif; ?>
        </span>
      </li>
    <?php endforeach; ?>
  </ol>
</section>
