<?php
/** @var array $data @var array $options */
$wrap = block_option($options, 'wrap', 'scroll');
$lang = (string) ($data['language'] ?? 'plain');
?>
<section class="block block--code">
  <?php if (!empty($data['heading'])): ?><h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3><?php endif; ?>
  <figure class="code code--<?= e($wrap) ?>">
    <figcaption class="code__bar">
      <span class="code__lang"><?= e($lang === 'plain' ? 'Snippet' : strtoupper($lang)) ?></span>
      <button class="code__copy" type="button" data-copy-code>Copy</button>
    </figcaption>
    <pre class="code__pre"><code><?= e($data['code'] ?? '') ?></code></pre>
  </figure>
  <?php if (!empty($data['caption'])): ?><p class="block__note"><?= e($data['caption']) ?></p><?php endif; ?>
</section>
