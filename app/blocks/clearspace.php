<?php
/**
 * Clear space around the mark.
 *
 * **The diagram is an image you uploaded, not one this draws.** A clearspace
 * diagram is a designed thing — corner marks, the measured letter called out,
 * guides where the construction actually is — and a generated one can only ever
 * be a rectangle with a gap round it. Trying to produce the first from the second
 * is a losing argument with a drawing someone has already made correctly.
 *
 * So: export it from Figma, upload it, and it appears exactly as designed. The
 * generated version stays as a fallback for a page that has no diagram yet, with
 * the geometry corrected — it is better than an empty block and worse than a real
 * one, and it says so.
 *
 * What this adds on top of the picture is the thing a static export cannot do:
 * the guides can be **toggled over it**, so someone can see the margin in place
 * and then see the artwork without it.
 *
 * @var array $data @var array $options
 */
$asset  = ((int) ($data['asset'] ?? 0)) > 0 ? asset_find((int) $data['asset']) : null;
$hasImage = $asset && asset_is_inline_image($asset);

$tone   = block_option($options, 'tone', 'accent');
$back   = block_option($options, 'background', 'panel');
$guides = block_option($options, 'guides', 'toggle');

// Where the guides are drawn, as a margin from each edge.
$inset = trim((string) ($data['inset'] ?? ''));
$inset = $inset === '' ? 8 : max(0, min(45, (int) $inset));

// The generated fallback: for a clear space of r times the mark on each side,
// the frame is (1 + 2r) marks across, so the mark is 1/(1 + 2r) of it.
$ratio = (int) ($data['ratio'] ?? 50);
$markWidth = round(100 / (1 + 2 * (max(0, $ratio) / 100)), 4);

$id = 'cs' . substr(md5((string) ($data['asset'] ?? '') . $ratio . $inset), 0, 8);
?>
<section class="block block--clearspace">
  <?php if (!empty($data['heading'])): ?><h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3><?php endif; ?>

  <?php if ($hasImage): ?>
    <figure class="clearspace clearspace--bg-<?= e($back) ?><?= $guides === 'on' ? ' is-guides' : '' ?>"
            style="--guide-inset: <?= $inset ?>%">
      <?php if ($guides === 'toggle'): ?>
        <?php // A real control, labelled, rather than something to discover. It
              // does nothing without JavaScript, so it is not drawn without it —
              // site.js reveals it. ?>
        <div class="clearspace__bar">
          <span class="clearspace__title"><?= e($data['heading'] ?? '') !== '' ? e($data['heading']) : 'Clear space' ?></span>
          <button class="clearspace__toggle" type="button" data-guides="<?= e($id) ?>"
                  aria-pressed="false" hidden>
            <span>Guides</span>
            <span class="clearspace__switch" aria-hidden="true"></span>
          </button>
        </div>
      <?php endif; ?>

      <div class="clearspace__art" id="<?= e($id) ?>">
        <?php $set = asset_srcset($asset); ?>
        <img src="<?= e(asset_image_url($asset, 1600)) ?>"
             <?php if ($set): ?>srcset="<?= e($set) ?>" sizes="100vw"<?php endif; ?>
             alt="<?= e($data['measure'] ?? 'Clear space around the mark') ?>" loading="lazy" decoding="async">
        <?php if ($guides !== 'off'): ?>
          <span class="clearspace__guides" aria-hidden="true"></span>
        <?php endif; ?>
      </div>

      <?php if (!empty($data['measure'])): ?>
        <figcaption class="clearspace__measure"<?= editable('measure') ?>><?= e($data['measure']) ?></figcaption>
      <?php endif; ?>
    </figure>

  <?php elseif (((int) ($data['asset'] ?? 0)) > 0 || $ratio): ?>
    <?php // No diagram uploaded, so a rough one is drawn from the mark. It is
          // honest about being rough rather than pretending to be the artwork. ?>
    <figure class="clearspace clearspace--drawn clearspace--<?= e($tone) ?> clearspace--bg-<?= e($back) ?>"
            style="--mark-width: <?= $markWidth ?>%">
      <div class="clearspace__frame">
        <div class="clearspace__mark">
          <?php if ($asset): ?>
            <img src="<?= e(asset_image_url($asset, 800)) ?>" alt="<?= e($data['heading'] ?? 'The mark') ?>" loading="lazy">
          <?php endif; ?>
        </div>
        <span class="clearspace__gap clearspace__gap--x" aria-hidden="true"><?= e(trim((string) ($data['unit'] ?? '')) ?: 'x') ?></span>
        <span class="clearspace__gap clearspace__gap--y" aria-hidden="true"><?= e(trim((string) ($data['unit'] ?? '')) ?: 'x') ?></span>
      </div>
      <?php if (!empty($data['measure'])): ?>
        <figcaption class="clearspace__measure"<?= editable('measure') ?>><?= e($data['measure']) ?></figcaption>
      <?php endif; ?>
    </figure>
  <?php endif; ?>

  <?php // A div, because paragraphs() emits its own <p> tags. ?>
  <?php if (!empty($data['note'])): ?><div class="block__note"<?= editable('note', true) ?>><?= paragraphs($data['note']) ?></div><?php endif; ?>
</section>
