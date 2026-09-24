<?php
/** @var array $data @var array $options */
$density = block_option($options, 'density', 'normal');
$strike  = block_option($options, 'strike', 'struck');
?>
<section class="block block--comparison">
  <?php if (!empty($data['heading'])): ?>
    <h3><?= e($data['heading']) ?></h3>
  <?php endif; ?>
  <table class="comparison comparison--<?= e($density) ?> comparison--<?= e($strike) ?>">
    <thead>
      <tr>
        <th scope="col"><?= e($data['left'] ?? '') ?></th>
        <th scope="col"><?= e($data['right'] ?? '') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($data['rows'] ?? []) as $row): ?>
        <tr>
          <td class="comparison__yes"><?= e($row['left'] ?? '') ?></td>
          <td class="comparison__no"><?= e($row['right'] ?? '') ?></td>
        </tr>
        <?php if (!empty($row['note'])): ?>
          <tr class="comparison__noterow"><td colspan="2"><?= e($row['note']) ?></td></tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
