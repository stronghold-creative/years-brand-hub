<?php
/** @var array $data @var array $options */
$style = block_option($options, 'style', 'number');
?>
<section class="block block--steps">
  <?php if (!empty($data['heading'])): ?><h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3><?php endif; ?>
  <ol class="steps steps--<?= e($style) ?>">
    <?php foreach (($data['steps'] ?? []) as $i => $step): ?>
      <li class="steps__item">
        <span class="steps__number" aria-hidden="true"><?= $i + 1 ?></span>
        <div class="steps__body">
          <h4 class="steps__title"<?= editable("steps.$i.title") ?>><?= e($step['title']) ?></h4>
          <?php if (!empty($step['body'])): ?><p class="steps__text"<?= editable("steps.$i.body") ?>><?= e($step['body']) ?></p><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
</section>
