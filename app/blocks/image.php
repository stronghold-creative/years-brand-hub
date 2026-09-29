<?php
/** @var array $data */
// Sanitising stores an unset image as "0", so check the number, not the string.
$asset  = ((int) ($data['asset'] ?? 0)) > 0 ? asset_find((int) $data['asset']) : null;
$fit    = block_option($options, 'fit', 'natural');
// The site radius is a number, applied through the CSS variable.
$radius = block_option($options, 'radius', 'round');
if ($asset && asset_is_inline_image($asset)):
?>
<figure class="block block--image figure figure--<?= e($data['width'] ?? 'text') ?> figure--frame-<?= e($data['frame'] ?? 'none') ?> figure--fit-<?= e($fit) ?> figure--r-<?= e($radius) ?>">
  <?php $size = asset_dimensions($asset); $set = asset_srcset($asset); ?>
  <img src="<?= e(asset_image_url($asset, 800)) ?>"
       <?php // A full-width image is asked to fill the block, so telling the
             // browser 46rem would have it choose a file too small for it. ?>
       <?php if ($set): ?>srcset="<?= e($set) ?>"
       sizes="<?= ($data['width'] ?? 'text') === 'full' ? '100vw' : '(max-width: 56rem) 100vw, 46rem' ?>"<?php endif; ?>
       alt="<?= e($data['alt'] ?? '') ?>"
       <?php if ($size): ?>width="<?= $size['width'] ?>" height="<?= $size['height'] ?>"<?php endif; ?>
       loading="lazy" decoding="async">
  <?php if (!empty($data['caption'])): ?>
    <figcaption<?= editable('caption') ?>><?= e($data['caption']) ?></figcaption>
  <?php endif; ?>
</figure>
<?php endif; ?>
