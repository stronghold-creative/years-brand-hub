<?php
/**
 * A table of any width.
 *
 * The grid is stored as text — rows on lines, cells separated by a tab or a pipe
 * — which is what makes pasting out of a spreadsheet work: a copied range arrives
 * tab-separated, so it lands correctly with nothing to do. A repeater would have
 * meant retyping every cell and scrolling a card per row.
 *
 * The number of columns comes from the content rather than from a setting. A row
 * with fewer cells than the widest is padded, so a ragged paste still renders a
 * rectangle instead of a broken table.
 *
 * @var array $data @var array $options
 */
$header  = block_option($options, 'header', 'yes') === 'yes';
$density = block_option($options, 'density', 'normal');
$lines   = block_option($options, 'lines', 'rows');
$strike  = block_option($options, 'strike', 'plain');
$first   = block_option($options, 'first', 'normal');

$rows = table_rows((string) ($data['grid'] ?? ''));
if (!$rows) {
    return;
}

$width = max(array_map('count', $rows));
$head  = $header ? array_shift($rows) : null;
?>
<section class="block block--table">
  <?php if (!empty($data['heading'])): ?>
    <h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3>
  <?php endif; ?>

  <div class="table__scroll">
    <table class="table table--<?= e($density) ?> table--lines-<?= e($lines) ?> table--strike-<?= e($strike) ?> table--first-<?= e($first) ?>">
      <?php if ($head !== null): ?>
        <thead>
          <tr>
            <?php for ($c = 0; $c < $width; $c++): ?>
              <th scope="col"><?= rich_inline(e((string) ($head[$c] ?? ''))) ?></th>
            <?php endfor; ?>
          </tr>
        </thead>
      <?php endif; ?>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <?php for ($c = 0; $c < $width; $c++): ?>
              <?php // A row shorter than the widest is padded, so the table stays
                    // rectangular rather than collapsing at the right. ?>
              <td><?= rich_inline(e((string) ($row[$c] ?? ''))) ?></td>
            <?php endfor; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (!empty($data['caption'])): ?>
    <p class="block__note"<?= editable('caption') ?>><?= e($data['caption']) ?></p>
  <?php endif; ?>
</section>
