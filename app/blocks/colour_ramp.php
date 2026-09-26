<?php
/**
 * Families of colour, each drawn as a ramp of its own steps.
 *
 * The scale is whatever the content gives it: eleven steps from 50 to 950, or
 * three, or one. Nothing here assumes a particular set, because a brand states
 * its tints rather than having them generated.
 *
 * @var array $data @var array $options
 */
$layout = block_option($options, 'layout', 'strip');
$height = block_option($options, 'height', 'medium');
$labels = block_option($options, 'labels', 'inside');
$values = block_option($options, 'values', 'hover');
$naming = block_option($options, 'name', 'above');

$families = array_values(array_filter(
    (array) ($data['families'] ?? []),
    // A family with no steps has nothing to draw, and a row of nothing is
    // worse than no row at all.
    fn($family) => !empty(array_filter((array) ($family['steps'] ?? []), fn($s) => sanitise_colour((string) ($s['hex'] ?? '')) !== ''))
));
?>
<section class="block block--ramp ramps--<?= e($layout) ?> ramps--h-<?= e($height) ?> ramps--name-<?= e($naming) ?>">
  <?php if (!empty($data['heading'])): ?>
    <h3><?= e($data['heading']) ?></h3>
  <?php endif; ?>

  <?php foreach ($families as $family): ?>
    <?php
      $steps = array_values(array_filter(
          (array) ($family['steps'] ?? []),
          fn($step) => sanitise_colour((string) ($step['hex'] ?? '')) !== ''
      ));
    ?>
    <div class="ramp">
      <div class="ramp__head">
        <h4 class="ramp__name"><?= e($family['name'] ?? '') ?></h4>
        <?php if (!empty($family['note'])): ?>
          <p class="ramp__note"><?= e($family['note']) ?></p>
        <?php endif; ?>
      </div>

      <ul class="ramp__steps ramp__steps--<?= e($labels) ?>">
        <?php foreach ($steps as $step): ?>
          <?php
            $hex = sanitise_colour((string) $step['hex']);
            // Which way the label has to read to stay legible on its own step.
            $onDark = contrast_ratio($hex, '#FFFFFF') >= contrast_ratio($hex, '#1F1F1F');
            $name = trim((string) ($step['step'] ?? ''));
          ?>
          <li class="ramp__step<?= $onDark ? ' is-dark' : '' ?>" style="--step: <?= e($hex) ?>">
            <button class="ramp__chip" type="button" data-copy="<?= e($hex) ?>"
                    aria-label="Copy <?= e($name !== '' ? $name . ', ' : '') ?><?= e($hex) ?>">
              <?php if ($labels === 'inside' && $name !== ''): ?>
                <span class="ramp__label"><?= e($name) ?></span>
              <?php endif; ?>
              <?php if ($values !== 'never'): ?>
                <span class="ramp__hex<?= $values === 'always' ? ' is-shown' : '' ?>"><?= e($hex) ?></span>
              <?php endif; ?>
            </button>
            <?php if ($labels === 'below' && $name !== ''): ?>
              <span class="ramp__under"><?= e($name) ?></span>
            <?php endif; ?>
            <?php if (!empty($step['use'])): ?>
              <span class="ramp__use"><?= e($step['use']) ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
</section>
