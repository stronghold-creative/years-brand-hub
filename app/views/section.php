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
    <?php // The head of the rail, and the only place the mark appears on a wide
          // screen. The bar above is not drawn at all here; see layout.php. ?>
    <div class="reader__head">
      <?= site_mark('mark') ?>
      <?php // The bar is not drawn on a wide screen, so the rail carries the
            // search trigger too. A real link, enhanced by search.js. ?>
      <a class="search-open search-open--rail" href="/" data-search>
        <?= ui_icon('search', 'icon icon--small') ?>
        <span>Search</span>
        <kbd>/</kbd>
      </a>
    </div>

    <?php foreach ($nav as $name => $items): ?>
      <?php $isCurrent = $name === $section; ?>
      <?php // Always a link, so the current section is styled rather than
            // structured differently and cannot drift from the others. ?>
      <h2 class="reader__section<?= $isCurrent ? ' is-current' : '' ?>">
        <?= icon(section_icon($name), 'icon icon--small') ?>
        <a href="/s/<?= e(slugify($name)) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>><?= e($name) ?></a>
      </h2>
      <?php if ($isCurrent): ?>
        <?php foreach ($items as $item): ?>
          <a class="reader__link" href="#<?= e($item['slug']) ?>" data-anchor="<?= e($item['slug']) ?>">
            <?= e($item['title']) ?>
            <?php // An empty span with a title attribute is not reliably read out,
                  // so the word itself is there for anyone not seeing the dot. ?>
            <?php if ($item['status'] !== 'published'): ?><span class="reader__draftdot" aria-hidden="true"></span><span class="visually-hidden">Draft</span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endforeach; ?>

    <?php // At the foot of the rail rather than in a bar, because the bar is
          // gone on a wide screen and these are the least of what is here. ?>
    <nav class="reader__tools" aria-label="Account">
      <?php if (can_edit()): ?>
        <a href="/admin">Edit</a>
        <a href="/admin/sign-out">Sign out</a>
      <?php else: ?>
        <a href="/admin/sign-in">Sign in</a>
      <?php endif; ?>
    </nav>
  </nav>

  <main class="reader__body" id="content">
    <?php if (!empty($hero)): ?>
      <?php // Inside the column, not across the page. A hero that spans the
            // viewport is a landing-page device: it can own the first screen
            // because nothing competes with it. Here the rail has to stay
            // reachable, so the opener is bounded by the column it opens. Its
            // type is fitted with container units, so it sizes itself to
            // whatever width it is given. ?>
      <div class="opener opener--column"><?php render_bare_block($hero); ?></div>
    <?php else: ?>
      <header class="reader__sectionhead">
        <h1><?= e($section) ?></h1>
        <p class="reader__count"><?= count($pages) ?> <?= count($pages) === 1 ? 'page' : 'pages' ?></p>
      </header>
    <?php endif; ?>

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

    <?php // A section is deliberately one long scroll, so the end of it is a long
          // way from the rail. On a narrow screen the rail is a panel behind a
          // button, which makes this the only way back without a gesture. ?>
    <p class="backtotop"><a href="#top">Back to the top of <?= e($section) ?></a></p>
  </main>
</div>
