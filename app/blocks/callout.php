<?php /** @var array $data */ ?>
<aside class="block callout callout--<?= e($data['tone'] ?? 'note') ?>">
  <?= paragraphs($data['body'] ?? '') ?>
</aside>
