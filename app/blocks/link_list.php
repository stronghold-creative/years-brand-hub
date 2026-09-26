<?php
/** @var array $data @var array $options */
$layout = block_option($options, 'layout', 'rows');
?>
<section class="block block--links">
  <?php if (!empty($data['heading'])): ?><h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3><?php endif; ?>
  <ul class="links links--<?= e($layout) ?>">
    <?php foreach (($data['links'] ?? []) as $link): ?>
      <?php
        // Only http links are rendered as links, so a stray value cannot
        // become a javascript: or data: URL.
        $url  = (string) ($link['url'] ?? '');
        $safe = preg_match('#^https?://#i', $url) === 1;
        $host = $safe ? (parse_url($url, PHP_URL_HOST) ?: '') : '';
      ?>
      <li class="links__item">
        <?php if ($safe): ?>
          <a class="links__link" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">
            <span class="links__label"><?= e($link['label']) ?></span>
            <span class="links__host"><?= e(preg_replace('/^www\./', '', $host)) ?></span>
          </a>
        <?php else: ?>
          <span class="links__label"><?= e($link['label']) ?></span>
        <?php endif; ?>
        <?php if (!empty($link['note'])): ?><span class="links__note"><?= e($link['note']) ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
