<?php
/** @var array $data @var array $options */
$layout = block_option($options, 'layout', 'cards');
?>
<section class="block block--contacts">
  <?php if (!empty($data['heading'])): ?><h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3><?php endif; ?>
  <ul class="contacts contacts--<?= e($layout) ?>">
    <?php foreach (($data['people'] ?? []) as $i => $person): ?>
      <li class="contacts__item">
        <h4<?= editable("people.$i.name") ?>><?= e($person['name']) ?></h4>
        <?php if (!empty($person['role'])): ?><p class="contacts__role"<?= editable("people.$i.role") ?>><?= e($person['role']) ?></p><?php endif; ?>
        <?php if (!empty($person['email'])): ?>
          <?php $email = filter_var($person['email'], FILTER_VALIDATE_EMAIL); ?>
          <?php if ($email): ?>
            <a class="contacts__link" href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
          <?php endif; ?>
        <?php endif; ?>
        <?php // The email is not editable here: it is validated before it is
              // rendered, and a half-typed address would stop being a link
              // mid-edit. It is edited in the inspector. ?>
        <?php if (!empty($person['slack'])): ?>
          <p class="contacts__slack"<?= editable("people.$i.slack") ?>><?= e($person['slack']) ?></p>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
