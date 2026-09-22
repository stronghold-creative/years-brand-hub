<?php /** @var array $data */ ?>
<section class="block block--text">
  <?php if (!empty($data['heading'])): ?>
    <h2><?= e($data['heading']) ?></h2>
  <?php endif; ?>
  <div class="prose"><?= paragraphs($data['body'] ?? '') ?></div>
</section>
