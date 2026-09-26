<?php
/** @var array $data @var array $options */
$emphasis = block_option($options, 'emphasis', 'bar');
?>
<aside class="block callout callout--<?= e($data['tone'] ?? 'note') ?> callout--<?= e($emphasis) ?>">
  <?php // Wrapped, because the editable region has to be one element that owns
        // the paragraphs, not the aside, which also carries the tone styling. ?>
  <div class="callout__body"<?= editable('body', true) ?>><?= paragraphs($data['body'] ?? '') ?></div>
</aside>
