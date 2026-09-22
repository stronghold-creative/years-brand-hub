<?php /** @var array $data */ ?>
<section class="block block--comparison">
  <?php if (!empty($data['heading'])): ?>
    <h2><?= e($data['heading']) ?></h2>
  <?php endif; ?>
  <table class="comparison">
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
          <tr class="comparison__noterow">
            <td colspan="2"><?= e($row['note']) ?></td>
          </tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
