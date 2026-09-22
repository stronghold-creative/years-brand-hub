<?php /** @var array $data */ ?>
<section class="block block--rules">
  <?php if (!empty($data['heading'])): ?>
    <h2><?= e($data['heading']) ?></h2>
  <?php endif; ?>
  <ul class="rules">
    <?php foreach (($data['pairs'] ?? []) as $pair): ?>
      <li class="rule rule--<?= e($pair['stance']) ?>">
        <span class="rule__stance"><?= $pair['stance'] === 'do' ? 'Do' : 'Do not' ?></span>
        <div>
          <p class="rule__text"><?= e($pair['rule']) ?></p>
          <?php if (!empty($pair['why'])): ?><p class="rule__why"><?= e($pair['why']) ?></p><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
