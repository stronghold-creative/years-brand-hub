<?php
/** @var array $data @var array $options */
$size = block_option($options, 'size', 'normal');
?>
<figure class="block block--quote quote quote--<?= e($size) ?>">
  <blockquote class="quote__text"<?= editable('text', true) ?>><?= paragraphs($data['text'] ?? '') ?></blockquote>
  <?php if (!empty($data['source'])): ?>
    <figcaption class="quote__source"<?= editable('source') ?>><?= e($data['source']) ?></figcaption>
  <?php endif; ?>
</figure>
