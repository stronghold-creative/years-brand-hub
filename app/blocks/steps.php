<?php
/** @var array $data @var array $options */
$style = block_option($options, 'style', 'number');

/**
 * Numbered or not, and the list element follows.
 *
 * An ordered list whose markers are bullets tells a screen reader the order
 * matters when it does not, so the tag changes with the marker rather than the
 * numbers simply being hidden by CSS.
 */
$numbered = $style === 'number' || $style === 'line';
$tag = $numbered ? 'ol' : 'ul';
?>
<section class="block block--steps">
  <?php if (!empty($data['heading'])): ?><h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3><?php endif; ?>
  <<?= $tag ?> class="steps steps--<?= e($style) ?>">
    <?php foreach (($data['steps'] ?? []) as $i => $step): ?>
      <li class="steps__item">
        <?php if ($numbered): ?>
          <span class="steps__number" aria-hidden="true"><?= $i + 1 ?></span>
        <?php elseif ($style === 'bullet'): ?>
          <span class="steps__number steps__number--dot" aria-hidden="true"></span>
        <?php endif; ?>
        <div class="steps__body">
          <h4 class="steps__title"<?= editable("steps.$i.title") ?>><?= e($step['title']) ?></h4>
          <?php if (!empty($step['body'])): ?><p class="steps__text"<?= editable("steps.$i.body") ?>><?= e($step['body']) ?></p><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </<?= $tag ?>>
</section>
