<?php
/** @var array $data @var array $options */
$layout  = block_option($options, 'layout', 'list', 'swatch_layout');
$shape   = block_option($options, 'shape', 'square', 'swatch_shape');
$size    = block_option($options, 'size', 'medium');
$values  = block_option($options, 'values', 'all');
$columns = block_option($options, 'columns', 'auto');
$ratio   = block_option($options, 'ratio', 'square');
$labels  = block_option($options, 'labels', 'below');

// A fixed column count is a number; "auto" lets the grid fit the space. Only
// ever a number from the option list, so it cannot carry anything else into
// the style attribute.
$columnStyle = ctype_digit($columns) ? ' style="--cols: ' . (int) $columns . '"' : '';
?>
<section class="block block--palette">
  <?php if (!empty($data['heading'])): ?>
    <h3><?= e($data['heading']) ?></h3>
  <?php endif; ?>
  <ul class="swatches swatches--<?= e($layout) ?> swatches--<?= e($size) ?> swatches--shape-<?= e($shape) ?> swatches--ratio-<?= e($ratio) ?> swatches--labels-<?= e($labels) ?><?= $columns !== 'auto' ? ' swatches--fixed' : '' ?>"<?= $columnStyle ?>>
    <?php foreach (($data['colours'] ?? []) as $colour): ?>
      <?php
        // A colour with no hex has nothing to show and nothing to copy. Rows
        // like that exist, because the editor once saved a blank hex while
        // displaying a default, so this has to cope rather than emit an empty
        // custom property and a Copy button that copies nothing.
        $hex = sanitise_colour((string) ($colour['hex'] ?? ''));
      ?>
      <li class="swatch<?= $hex === '' ? ' swatch--unset' : '' ?>">
        <?php if ($hex !== ''): ?>
          <button class="swatch__chip" type="button"
                  style="--chip: <?= e($hex) ?>"
                  data-copy="<?= e($hex) ?>"
                  aria-label="Copy <?= e($hex) ?>">
            <span class="swatch__copy">Copy</span>
          </button>
        <?php else: ?>
          <span class="swatch__chip swatch__chip--unset" aria-hidden="true"></span>
        <?php endif; ?>
        <div class="swatch__meta">
          <h4><?= e($colour['name']) ?></h4>
          <?php if ($values !== 'none' && $hex !== ''): ?>
            <dl>
              <dt>Hex</dt><dd><?= e($hex) ?></dd>
              <?php if ($values === 'all'): ?>
                <?php if (!empty($colour['cmyk'])): ?><dt>CMYK</dt><dd><?= e($colour['cmyk']) ?></dd><?php endif; ?>
                <?php if (!empty($colour['pantone'])): ?><dt>Pantone</dt><dd><?= e($colour['pantone']) ?></dd><?php endif; ?>
              <?php endif; ?>
            </dl>
          <?php endif; ?>
          <?php if (!empty($colour['usage'])): ?>
            <p class="swatch__usage"><?= e($colour['usage']) ?></p>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
