<?php
/** @var array $data @var array $options */
$asset = ((int) ($data['asset'] ?? 0)) > 0 ? asset_find((int) $data['asset']) : null;
$ratio = (int) ($data['ratio'] ?? 50);
$tone  = block_option($options, 'tone', 'accent');
$back  = block_option($options, 'background', 'panel');

/**
 * The clear space is a proportion OF THE MARK, not of the container.
 *
 * It used to be written as `padding: 50%` on the frame, which is 50% of the
 * frame's own width — so the diagram claimed a clear space that had nothing to
 * do with the size of the mark inside it, and changing the setting moved the
 * frame rather than the relationship. That is what made it wrong: the whole
 * point of the diagram is the ratio between the two.
 *
 * Sizing the mark instead gets it exactly right. If the clear space each side is
 * r times the mark's width, the frame is (1 + 2r) mark-widths across, so the
 * mark is 1/(1 + 2r) of the frame: two thirds at a quarter, a half at a half, a
 * third at a full mark. Worth doing on paper rather than by eye — the first
 * version of this comment said two thirds for a half and a check caught it.
 */
$r = max(0, $ratio) / 100;
$markWidth = round(100 / (1 + 2 * $r), 4);
?>
<section class="block block--clearspace">
  <?php if (!empty($data['heading'])): ?><h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3><?php endif; ?>
  <?php if ($asset): ?>
    <figure class="clearspace clearspace--<?= e($tone) ?> clearspace--bg-<?= e($back) ?>"
            style="--mark-width: <?= $markWidth ?>%">
      <div class="clearspace__frame">
        <div class="clearspace__mark">
          <img src="<?= e(asset_image_url($asset, 800)) ?>" alt="<?= e($data['heading'] ?? 'The mark') ?>" loading="lazy">
        </div>
        <?php // The measurement drawn on the diagram rather than only written
              // underneath it, which is how every brand guideline shows this. ?>
        <span class="clearspace__gap clearspace__gap--x" aria-hidden="true"><?= e(trim((string) ($data['unit'] ?? '')) ?: 'x') ?></span>
        <span class="clearspace__gap clearspace__gap--y" aria-hidden="true"><?= e(trim((string) ($data['unit'] ?? '')) ?: 'x') ?></span>
      </div>
      <?php if (!empty($data['measure'])): ?>
        <figcaption class="clearspace__measure"<?= editable('measure') ?>><?= e($data['measure']) ?></figcaption>
      <?php endif; ?>
    </figure>
  <?php endif; ?>
  <?php if (!empty($data['note'])): ?><p class="block__note"<?= editable('note') ?>><?= e($data['note']) ?></p><?php endif; ?>
</section>
