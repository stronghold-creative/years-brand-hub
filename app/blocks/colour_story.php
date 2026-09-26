<?php
/**
 * The brand's colours as one composition rather than a list.
 *
 * The primaries take the large panels and the secondaries tile beside them, so
 * the arrangement itself says which colours carry the brand. The proportions
 * are the content: reading it should tell you what leads before you read a
 * single word.
 *
 * @var array $data @var array $options
 */
$emphasis = block_option($options, 'emphasis', 'half');
$height   = block_option($options, 'height', 'medium');
$values   = block_option($options, 'values', 'all');
$gap      = block_option($options, 'gap', 'thin');

// The original key travels with each colour, because editable() paths are
// routes into the stored data and this list is filtered and split in two. A
// colour's position here says nothing about where it lives in $data.
$all = [];
foreach ((array) ($data['colours'] ?? []) as $at => $colour) {
    $hex = sanitise_colour((string) ($colour['hex'] ?? ''));
    if ($hex === '') {
        continue;   // Nothing to paint, so nothing to place.
    }
    $colour['hex'] = $hex;
    $colour['__at'] = $at;
    $all[] = $colour;
}

$primary   = array_values(array_filter($all, fn($c) => ($c['role'] ?? 'primary') === 'primary'));
$secondary = array_values(array_filter($all, fn($c) => ($c['role'] ?? 'primary') === 'secondary'));

/** One panel. The text colour is measured against the panel, never guessed. */
$panel = function (array $colour, string $size) use ($values): void {
    $hex = $colour['hex'];
    $at  = $colour['__at'];
    $onDark = contrast_ratio($hex, '#FFFFFF') >= contrast_ratio($hex, '#1F1F1F');
    ?>
    <div class="story__panel story__panel--<?= e($size) ?><?= $onDark ? ' is-dark' : '' ?>"
         style="--panel: <?= e($hex) ?>">
      <button class="story__copy" type="button" data-copy="<?= e($hex) ?>"
              aria-label="Copy <?= e($colour['name'] ?? '') ?> <?= e($hex) ?>">
        <span class="swatch__copy">Copy</span>
      </button>
      <div class="story__meta">
        <span class="story__name"<?= editable("colours.$at.name") ?>><?= e($colour['name'] ?? '') ?></span>
        <?php if ($values !== 'name'): ?>
          <span class="story__hex"><?= e($hex) ?></span>
          <?php if (!empty($colour['pantone'])): ?>
            <span class="story__pantone"<?= editable("colours.$at.pantone") ?>><?= e($colour['pantone']) ?></span>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($values === 'all' && !empty($colour['usage'])): ?>
          <span class="story__usage"<?= editable("colours.$at.usage") ?>><?= e($colour['usage']) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <?php
};
?>
<section class="block block--story">
  <?php if (!empty($data['heading'])): ?>
    <h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3>
  <?php endif; ?>
  <?php if (!empty($data['intro'])): ?>
    <div class="prose"<?= editable('intro', true) ?>><?= paragraphs($data['intro']) ?></div>
  <?php endif; ?>

  <?php if ($primary || $secondary): ?>
    <div class="story story--<?= e($emphasis) ?> story--h-<?= e($height) ?> story--gap-<?= e($gap) ?>">
      <?php if ($primary): ?>
        <div class="story__lead">
          <?php foreach ($primary as $colour) { $panel($colour, 'lead'); } ?>
        </div>
      <?php endif; ?>

      <?php if ($secondary): ?>
        <?php
          // The count decides the tiling. Letting the grid fit as many as the
          // space allowed put three across and left the fourth alone with a
          // hole beside it, which is the opposite of a composition. Two
          // columns suits a tall panel; a long list of secondaries gets three.
          $count = count($secondary);
          $cols = $count <= 2 ? 1 : ($count >= 7 ? 3 : 2);
          // An odd one out is stretched across the row rather than left short.
          $orphan = $cols > 1 && ($count % $cols) === 1;
        ?>
        <div class="story__rest<?= $orphan ? ' story__rest--stretch' : '' ?>" style="--cols: <?= (int) $cols ?>">
          <?php foreach ($secondary as $colour) { $panel($colour, 'rest'); } ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
