<?php
/**
 * A whole section on one page. Each page in it is an anchor, so the rail
 * scrolls rather than navigating: guidelines get read in sequence far more
 * often than they get looked up one at a time.
 */
$currentSlug = slugify($section);
?>
<div class="nav-scrim" id="nav-scrim" hidden></div>
<div class="reader">
  <nav class="reader__nav" id="reader-nav" aria-label="Guidelines">
    <?php foreach ($nav as $name => $items): ?>
      <?php $isCurrent = $name === $section; ?>
      <h2>
        <?= icon(section_icon($name), 'icon icon--small') ?>
        <?php if ($isCurrent): ?>
          <span><?= e($name) ?></span>
        <?php else: ?>
          <a href="/s/<?= e(slugify($name)) ?>"><?= e($name) ?></a>
        <?php endif; ?>
      </h2>
      <?php if ($isCurrent): ?>
        <?php foreach ($items as $item): ?>
          <a class="reader__link" href="#<?= e($item['slug']) ?>" data-anchor="<?= e($item['slug']) ?>">
            <?= e($item['title']) ?>
            <?php if ($item['status'] !== 'published'): ?><span class="reader__draftdot" title="Draft"></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <main class="reader__body">
    <header class="reader__sectionhead">
      <h1><?= e($section) ?></h1>
      <p class="reader__count"><?= count($pages) ?> <?= count($pages) === 1 ? 'page' : 'pages' ?></p>
    </header>

    <?php foreach ($pages as $entry): ?>
      <article class="chapter" id="<?= e($entry['slug']) ?>">
        <?php if ($entry['status'] !== 'published'): ?>
          <p class="draft-flag">Draft. Only editors can see this.</p>
        <?php endif; ?>
        <h2 class="chapter__title"><?= e($entry['title']) ?></h2>
        <?php if (!empty($entry['summary'])): ?>
          <p class="chapter__summary"><?= e($entry['summary']) ?></p>
        <?php endif; ?>
        <?php render_blocks(json_decode($entry['blocks'], true) ?: []); ?>
        <p class="chapter__updated">Last updated <?= e(date('j F Y', strtotime($entry['updated_at']))) ?></p>
      </article>
    <?php endforeach; ?>
  </main>
</div>
