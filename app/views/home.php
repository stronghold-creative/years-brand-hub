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

    <?php if (count($sections) > 1): ?>
      <div class="filters" role="group" aria-label="Filter by section">
        <button class="filter is-on" type="button" data-filter="all">Everything</button>
        <?php foreach (array_keys($sections) as $name): ?>
          <button class="filter" type="button" data-filter="<?= e(slugify($name)) ?>"><?= e($name) ?></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <ul class="cards" id="cards">
      <?php foreach ($all as $i => $page): ?>
        <?php
          $cover = !empty($page['cover_asset_id']) ? asset_find((int) $page['cover_asset_id']) : null;
          $hasCover = $cover && asset_is_inline_image($cover);
          $tint = $tints[$i % count($tints)];
        ?>
        <li class="cards__item" data-section="<?= e(slugify($page['section'])) ?>">
          <a class="card" href="/g/<?= e($page['slug']) ?>">
            <span class="card__label"><?= e($page['title']) ?></span>
            <span class="card__art card__art--<?= $hasCover ? 'photo' : e($tint) ?>">
              <?php if ($hasCover): ?>
                <img src="<?= e(asset_image_url($cover)) ?>" alt="" loading="lazy" decoding="async">
              <?php else: ?>
                <span class="card__glyph"><?= icon(section_icon($page['section']), 'icon') ?: icon('star', 'icon') ?></span>
              <?php endif; ?>
            </span>
            <?php if (!empty($page['summary'])): ?>
              <span class="card__summary"><?= e($page['summary']) ?></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</main>
