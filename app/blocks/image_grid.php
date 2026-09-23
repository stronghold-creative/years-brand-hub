<?php
/** @var array $data */
$items = [];
foreach (($data['items'] ?? []) as $item) {
    $asset = ($item['asset'] ?? '') !== '' ? asset_find((int) $item['asset']) : null;
    if ($asset && asset_is_inline_image($asset)) {
        $items[] = ['asset' => $asset, 'meta' => $item];
    }
}
$gap = block_option($options, 'gap', 'normal');
$fit = block_option($options, 'fit', 'natural');
if ($items):
?>
<section class="block block--imagegrid">
  <?php if (!empty($data['heading'])): ?>
    <h2><?= e($data['heading']) ?></h2>
  <?php endif; ?>
  <div class="imagegrid imagegrid--gap-<?= e($gap) ?> imagegrid--fit-<?= e($fit) ?>" style="--cols: <?= (int) ($data['columns'] ?? 2) ?>">
    <?php foreach ($items as $entry): ?>
      <?php $stance = $entry['meta']['stance'] ?? 'none'; ?>
      <figure class="figure figure--grid<?= $stance !== 'none' ? ' figure--' . e($stance) : '' ?>">
        <img src="<?= e(asset_image_url($entry['asset'])) ?>"
             alt="<?= e($entry['meta']['alt'] ?? '') ?>" loading="lazy" decoding="async">
        <?php if ($stance !== 'none' || !empty($entry['meta']['caption'])): ?>
          <figcaption>
            <?php if ($stance !== 'none'): ?>
              <span class="figure__stance"><?= $stance === 'do' ? 'Do' : 'Do not' ?></span>
            <?php endif; ?>
            <?= e($entry['meta']['caption'] ?? '') ?>
          </figcaption>
        <?php endif; ?>
      </figure>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
