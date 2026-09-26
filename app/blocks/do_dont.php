<?php
/** @var array $data @var array $options */
$layout = block_option($options, 'layout', 'rows');
?>
<section class="block block--rules">
  <?php if (!empty($data['heading'])): ?>
    <h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3>
  <?php endif; ?>
  <ul class="rules rules--<?= e($layout) ?>">
    <?php foreach (($data['pairs'] ?? []) as $i => $pair): ?>
      <li class="rule rule--<?= e($pair['stance']) ?>">
        <span class="rule__stance"><?= $pair['stance'] === 'do' ? 'Do' : 'Do not' ?></span>
        <div>
          <p class="rule__text"<?= editable("pairs.$i.rule") ?>><?= e($pair['rule']) ?></p>
          <?php if (!empty($pair['why'])): ?><p class="rule__why"<?= editable("pairs.$i.why") ?>><?= e($pair['why']) ?></p><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
