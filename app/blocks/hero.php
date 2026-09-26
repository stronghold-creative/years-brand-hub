<?php
/**
 * A full-bleed opener.
 *
 * Each line of the headline is sized so it fills the width, which is what
 * makes lines of different lengths read as one block of type. The size is
 * worked out here from the font's own character widths rather than measured
 * in the browser, so there is no reflow when the page loads.
 *
 * @var array $data @var array $options
 */
$height  = block_option($options, 'height', 'full');
$align   = block_option($options, 'align', 'centre');
$case    = block_option($options, 'case', 'upper');
$reveal  = block_option($options, 'reveal', 'rise');
$overlay = block_option($options, 'overlay', 'none');

$asset = ((int) ($data['asset'] ?? 0)) > 0 ? asset_find((int) $data['asset']) : null;

$lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['lines'] ?? '')))));
if ($case === 'upper') {
    $lines = array_map(fn($line) => str_upper($line), $lines);
}

// The type sits inside the padding, so the measure is the container minus it.
$style = [];
if (!empty($data['background'])) { $style[] = '--hero-bg: ' . $data['background']; }
if (!empty($data['ink']))        { $style[] = '--hero-ink: ' . $data['ink']; }
?>
<section class="hero hero--<?= e($height) ?> hero--<?= e($align) ?> hero--reveal-<?= e($reveal) ?>"
         <?= $style ? 'style="' . e(implode('; ', $style)) . '"' : '' ?>>

  <?php if ($asset && asset_is_inline_image($asset)): ?>
    <?php $set = asset_srcset($asset); ?>
    <img class="hero__image" src="<?= e(asset_image_url($asset, 1600)) ?>"
         <?php if ($set): ?>srcset="<?= e($set) ?>" sizes="100vw"<?php endif; ?>
         alt="" loading="eager" decoding="async">
    <?php if ($overlay !== 'none'): ?>
      <span class="hero__veil hero__veil--<?= e($overlay) ?>" aria-hidden="true"></span>
    <?php endif; ?>
  <?php endif; ?>

  <div class="hero__inner">
    <?php if (!empty($data['label'])): ?>
      <p class="hero__label"<?= editable('label') ?>><?= e($data['label']) ?></p>
    <?php endif; ?>
    <?php
      // The headline itself is deliberately not editable in place. Each line is
      // sized from its own measured width, worked out here before the page is
      // sent, so typing into it would leave every line at the size of the words
      // that used to be there. It is edited in the inspector, where a save
      // re-measures it.
    ?>

    <?php
      // When the hero opens a section it is the only heading above the
      // chapters, so it carries the h1. A hero used partway down a page is a
      // heading within it, and the section head holds the h1 instead. Without
      // this a section that opened with a hero had no h1 at all.
      $tag = !empty($isOpener) ? 'h1' : 'h2';
    ?>
    <?php if ($lines): ?>
      <<?= $tag ?> class="hero__headline">
        <?php foreach ($lines as $i => $line): ?>
          <?php
            // Width of this line at font-size 1em, so the size that fills the
            // measure is simply the measure divided by it.
            $width = hero_line_width($line);
          ?>
          <span class="hero__line" style="--fit: <?= e(number_format($width, 3, '.', '')) ?>"
                data-line="<?= (int) $i ?>"><span><?= e($line) ?></span></span>
        <?php endforeach; ?>
      </<?= $tag ?>>
    <?php endif; ?>

    <?php if (!empty($data['standfirst'])): ?>
      <div class="hero__standfirst"<?= editable('standfirst', true) ?>><?= paragraphs($data['standfirst']) ?></div>
    <?php endif; ?>
  </div>
</section>
