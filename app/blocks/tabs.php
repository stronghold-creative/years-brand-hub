<?php
/** @var array $data @var array $options */
$style  = block_option($options, 'style', 'underline');
$panels = $data['panels'] ?? [];
// Unique per render, so two tab blocks on one page cannot collide.
$id = 'tabs-' . substr(md5(json_encode($panels) . microtime()), 0, 8);
?>
<section class="block block--tabs">
  <?php if (!empty($data['heading'])): ?><h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3><?php endif; ?>
  <?php if ($panels): ?>
    <div class="tabs tabs--<?= e($style) ?>" data-tabs>
      <div class="tabs__list" role="tablist">
        <?php foreach ($panels as $i => $panel): ?>
          <button class="tabs__tab" type="button" role="tab"
                  id="<?= e($id) ?>-t<?= $i ?>"
                  aria-controls="<?= e($id) ?>-p<?= $i ?>"
                  aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                  <?php // The label sits in a span: the button itself switches
                        // tabs, so making it editable means every click to
                        // place the cursor also changes the panel. ?>
                  tabindex="<?= $i === 0 ? '0' : '-1' ?>"><span<?= editable("panels.$i.label") ?>><?= e($panel['label']) ?></span></button>
        <?php endforeach; ?>
      </div>
      <?php foreach ($panels as $i => $panel): ?>
        <div class="tabs__panel" role="tabpanel"
             id="<?= e($id) ?>-p<?= $i ?>"
             aria-labelledby="<?= e($id) ?>-t<?= $i ?>"
             <?= $i === 0 ? '' : 'hidden' ?>>
          <?php $asset = ((int) ($panel['asset'] ?? 0)) > 0 ? asset_find((int) $panel['asset']) : null; ?>
          <?php if ($asset): ?>
            <img class="tabs__image" src="<?= e(asset_image_url($asset, 1600)) ?>" alt="<?= e($panel['label']) ?>" loading="lazy">
          <?php endif; ?>
          <?php if (!empty($panel['body'])): ?>
            <div class="prose"<?= editable("panels.$i.body", true) ?>><?= paragraphs($panel['body']) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
