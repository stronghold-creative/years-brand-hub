<?php
/** @var array $data */
// The original key is carried through, because items without a usable image
// are dropped here and an editable() path is a route into the stored data, not
// a position in this list.
$items = [];
foreach (($data['items'] ?? []) as $at => $item) {
    $asset = ((int) ($item['asset'] ?? 0)) > 0 ? asset_find((int) $item['asset']) : null;
    if ($asset && asset_is_inline_image($asset)) {
        $items[] = ['asset' => $asset, 'meta' => $item, 'at' => $at];
    }
}
$gap = block_option($options, 'gap', 'normal');
$fit = block_option($options, 'fit', 'natural');
if ($items):
?>
<section class="block block--imagegrid">
  <?php if (!empty($data['heading'])): ?>
    <h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3>
  <?php endif; ?>
  <div class="imagegrid imagegrid--gap-<?= e($gap) ?> imagegrid--fit-<?= e($fit) ?>" style="--cols: <?= (int) ($data['columns'] ?? 2) ?>">
    <?php foreach ($items as $entry): ?>
      <?php $stance = $entry['meta']['stance'] ?? 'none'; ?>
      <figure class="figure figure--grid<?= $stance !== 'none' ? ' figure--' . e($stance) : '' ?>">
        <?php $size = asset_dimensions($entry['asset']); $set = asset_srcset($entry['asset']); ?>
        <img src="<?= e(asset_image_url($entry['asset'], 400)) ?>"
             <?php if ($set): ?>srcset="<?= e($set) ?>" sizes="(max-width: 48rem) 50vw, 22rem"<?php endif; ?>
             alt="<?= e($entry['meta']['alt'] ?? '') ?>"
             <?php if ($size): ?>width="<?= $size['width'] ?>" height="<?= $size['height'] ?>"<?php endif; ?>
             loading="lazy" decoding="async">
        <?php if ($stance !== 'none' || !empty($entry['meta']['caption'])): ?>
          <figcaption>
            <?php if ($stance !== 'none'): ?>
              <span class="figure__stance"><?= $stance === 'do' ? 'Do' : 'Do not' ?></span>
            <?php endif; ?>
            <span<?= editable('items.' . $entry['at'] . '.caption') ?>><?= e($entry['meta']['caption'] ?? '') ?></span>
          </figcaption>
        <?php endif; ?>
      </figure>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
