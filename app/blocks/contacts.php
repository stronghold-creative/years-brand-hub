<?php
/** @var array $data @var array $options */
$layout = block_option($options, 'layout', 'cards');
?>
<section class="block block--contacts">
  <?php if (!empty($data['heading'])): ?><h3><?= e($data['heading']) ?></h3><?php endif; ?>
  <ul class="contacts contacts--<?= e($layout) ?>">
    <?php foreach (($data['people'] ?? []) as $person): ?>
      <li class="contacts__item">
        <h4><?= e($person['name']) ?></h4>
        <?php if (!empty($person['role'])): ?><p class="contacts__role"><?= e($person['role']) ?></p><?php endif; ?>
        <?php if (!empty($person['email'])): ?>
          <?php $email = filter_var($person['email'], FILTER_VALIDATE_EMAIL); ?>
          <?php if ($email): ?>
            <a class="contacts__link" href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
          <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($person['slack'])): ?>
          <p class="contacts__slack"><?= e($person['slack']) ?></p>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
