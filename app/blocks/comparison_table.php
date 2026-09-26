<?php
/** @var array $data @var array $options */
$density = block_option($options, 'density', 'normal');
$strike  = block_option($options, 'strike', 'struck');
?>
<section class="block block--comparison">
  <?php if (!empty($data['heading'])): ?>
    <h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3>
  <?php endif; ?>
  <table class="comparison comparison--<?= e($density) ?> comparison--<?= e($strike) ?>">
    <thead>
      <tr>
        <th scope="col"<?= editable('left') ?>><?= e($data['left'] ?? '') ?></th>
        <th scope="col"<?= editable('right') ?>><?= e($data['right'] ?? '') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($data['rows'] ?? []) as $i => $row): ?>
        <tr>
          <td class="comparison__yes"<?= editable("rows.$i.left") ?>><?= e($row['left'] ?? '') ?></td>
          <td class="comparison__no"<?= editable("rows.$i.right") ?>><?= e($row['right'] ?? '') ?></td>
        </tr>
        <?php if (!empty($row['note'])): ?>
          <tr class="comparison__noterow"><td colspan="2"<?= editable("rows.$i.note") ?>><?= e($row['note']) ?></td></tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
