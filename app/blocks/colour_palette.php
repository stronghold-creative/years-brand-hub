<?php
/** @var array $data @var array $options */
$layout = block_option($options, 'layout', 'list', 'swatch_layout');
$shape  = block_option($options, 'shape', 'square', 'swatch_shape');
$size   = block_option($options, 'size', 'medium');
$values = block_option($options, 'values', 'all');
?>
<section class="block block--palette">
  <?php if (!empty($data['heading'])): ?>
    <h2><?= e($data['heading']) ?></h2>
  <?php endif; ?>
  <ul class="swatches swatches--<?= e($layout) ?> swatches--<?= e($size) ?> swatches--shape-<?= e($shape) ?>">
    <?php foreach (($data['colours'] ?? []) as $colour): ?>
      <li class="swatch">
        <button class="swatch__chip" type="button"
                style="--chip: <?= e($colour['hex']) ?>"
                data-copy="<?= e($colour['hex']) ?>"
                aria-label="Copy <?= e($colour['hex']) ?>">
          <span class="swatch__copy">Copy</span>
        </button>
        <div class="swatch__meta">
          <h3><?= e($colour['name']) ?></h3>
          <?php if ($values !== 'none'): ?>
            <dl>
              <dt>Hex</dt><dd><?= e($colour['hex']) ?></dd>
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
