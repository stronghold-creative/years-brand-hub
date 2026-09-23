<?php
/** @var array $data */
$asset  = ($data['asset'] ?? '') !== '' ? asset_find((int) $data['asset']) : null;
$fit    = block_option($options, 'fit', 'natural');
$radius = block_option($options, 'radius', 'round', 'radius');
if ($asset && asset_is_inline_image($asset)):
?>
<figure class="block block--image figure figure--<?= e($data['width'] ?? 'text') ?> figure--frame-<?= e($data['frame'] ?? 'none') ?> figure--fit-<?= e($fit) ?> figure--r-<?= e($radius) ?>">
  <img src="<?= e(asset_image_url($asset)) ?>" alt="<?= e($data['alt'] ?? '') ?>" loading="lazy" decoding="async">
  <?php if (!empty($data['caption'])): ?>
    <figcaption><?= e($data['caption']) ?></figcaption>
  <?php endif; ?>
</figure>
<?php endif; ?>
