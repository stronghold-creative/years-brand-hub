<?php
/** @var array $data @var array $options */
$emphasis = block_option($options, 'emphasis', 'bar');
?>
<aside class="block callout callout--<?= e($data['tone'] ?? 'note') ?> callout--<?= e($emphasis) ?>">
  <?= paragraphs($data['body'] ?? '') ?>
</aside>
