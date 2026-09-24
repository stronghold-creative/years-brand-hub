<?php
/**
 * The index is a grid of cards, one per page. A card shows its cover image
 * where one is set, and otherwise a generated tile built from the section's
 * icon over a brand tint, so the grid never has holes in it.
 */
$tints = ['tint-purple', 'tint-pink', 'tint-gold', 'tint-green', 'tint-lilac'];
$all = [];
foreach ($sections as $section => $items) {
    foreach ($items as $item) {
        $item['section'] = $section;
        $all[] = $item;
    }
}
?>
<main class="index">
  <?php if (!$all): ?>
    <div class="index__empty">
      <h1>Nothing published yet.</h1>
      <p>Sign in and add your first page.</p>
    </div>
  <?php else: ?>

    <ul class="cards" id="cards">
      <?php foreach ($all as $i => $page): ?>
        <?php
          $cover = !empty($page['cover_asset_id']) ? asset_find((int) $page['cover_asset_id']) : null;
          $hasCover = $cover && asset_is_inline_image($cover);
          $tint = $tints[$i % count($tints)];
        ?>
        <li class="cards__item" data-section="<?= e(slugify($page['section'])) ?>">
          <a class="card" href="/s/<?= e(slugify($page['section'])) ?>#<?= e($page['slug']) ?>" title="<?= e($page['summary'] ?? '') ?>">
            <span class="card__label"><?= e($page['title']) ?></span>
            <span class="card__art card__art--<?= $hasCover ? 'photo' : e($tint) ?>">
              <?php if ($hasCover): ?>
                <?php $set = asset_srcset($cover); ?>
                <img src="<?= e(asset_image_url($cover, 400)) ?>"
                     <?php if ($set): ?>srcset="<?= e($set) ?>" sizes="(max-width: 42rem) 50vw, 18rem"<?php endif; ?>
                     alt="" loading="lazy" decoding="async">
              <?php else: ?>
                <span class="card__glyph"><?= icon(section_icon($page['section']), 'icon') ?: icon('star', 'icon') ?></span>
              <?php endif; ?>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</main>
