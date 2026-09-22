<?php
/** @var array $data */
$asset = ($data['asset'] ?? '') !== '' ? asset_find((int) $data['asset']) : null;
if ($asset && asset_is_inline_image($asset)):
?>
<figure class="block block--image figure figure--<?= e($data['width'] ?? 'text') ?> figure--frame-<?= e($data['frame'] ?? 'none') ?>">
  <img src="<?= e(asset_image_url($asset)) ?>" alt="<?= e($data['alt'] ?? '') ?>" loading="lazy" decoding="async">
  <?php if (!empty($data['caption'])): ?>
    <figcaption><?= e($data['caption']) ?></figcaption>
  <?php endif; ?>
</figure>
<?php endif; ?>
