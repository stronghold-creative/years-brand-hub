<?php
/** @var array $data @var array $options */
$asset = ((int) ($data['asset'] ?? 0)) > 0 ? asset_find((int) $data['asset']) : null;
// Only a real video goes in a video element. Anything else is ignored rather
// than producing a player pointed at a file that cannot play.
if ($asset && !asset_is_inline_video($asset)) {
    $asset = null;
}
$url   = trim((string) ($data['url'] ?? ''));
$embed = null;

// Only YouTube and Vimeo are embedded, and only their own player URLs are
// built here, so an arbitrary address can never end up in an iframe.
if (preg_match('#youtube\.com/watch\?v=([A-Za-z0-9_-]{6,20})#', $url, $m)
 || preg_match('#youtu\.be/([A-Za-z0-9_-]{6,20})#', $url, $m)) {
    $embed = 'https://www.youtube-nocookie.com/embed/' . $m[1];
} elseif (preg_match('#vimeo\.com/(?:video/)?(\d{6,12})#', $url, $m)) {
    $embed = 'https://player.vimeo.com/video/' . $m[1];
}
?>
<section class="block block--video">
  <?php if (!empty($data['heading'])): ?><h3><?= e($data['heading']) ?></h3><?php endif; ?>

  <?php if ($embed): ?>
    <div class="video">
      <iframe src="<?= e($embed) ?>" title="<?= e($data['heading'] ?? 'Video') ?>"
              loading="lazy" allowfullscreen
              allow="accelerometer; clipboard-write; encrypted-media; picture-in-picture"
              referrerpolicy="strict-origin-when-cross-origin"></iframe>
    </div>
  <?php elseif ($asset): ?>
    <div class="video">
      <video controls preload="metadata" src="<?= e(asset_image_url($asset)) ?>"></video>
    </div>
  <?php elseif ($url !== ''): ?>
    <p class="block__empty">That address is not a YouTube or Vimeo video.</p>
  <?php endif; ?>

  <?php if (!empty($data['caption'])): ?><p class="block__note"><?= e($data['caption']) ?></p><?php endif; ?>
</section>
