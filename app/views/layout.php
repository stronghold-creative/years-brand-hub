<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
$siteName   = (string) setting('site_name', 'Years brand hub');
$descriptor = trim((string) ($description ?? setting('footer_note', 'Brand guidelines for Years.')));
$private    = site_is_private($config ?? []);
$canonical  = rtrim((string) (($config['site']['base_url'] ?? '')), '/')
            . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
?>
<title><?= e($title ?? $siteName) ?></title>
<meta name="description" content="<?= e($descriptor) ?>">
<?php if ($private): ?>
  <meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<?php if ($canonical !== ''): ?>
  <link rel="canonical" href="<?= e($canonical) ?>">
<?php endif; ?>

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($title ?? $siteName) ?>">
<meta property="og:description" content="<?= e($descriptor) ?>">
<?php if ($canonical !== ''): ?><meta property="og:url" content="<?= e($canonical) ?>"><?php endif; ?>
<?php $shareImage = site_logo(); ?>
<?php if ($shareImage): ?>
  <meta property="og:image" content="<?= e(rtrim((string) ($config['site']['base_url'] ?? ''), '/') . asset_image_url($shareImage)) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">

<?php // Through asset_url() like every other asset, so replacing the mark is not
      // invisible to anyone who has already loaded the old one. ?>
<link rel="icon" href="<?= e(asset_url('/assets/favicon.svg')) ?>" type="image/svg+xml">

<?= font_head() ?>
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/site.css')) ?>">
<style><?= style_css() ?></style>
<?php
// Marks the document as able to reveal the hero, before anything paints, so the
// stylesheet can hold the headline in its starting state. Doing this from
// site.js at the end of the body is too late: the words paint in full first and
// are then snapped away to be animated back in. If scripting is off the class
// never arrives and the headline is simply there.
?>
<script>document.documentElement.classList.add('js-hero');</script>
</head>
<?php
/**
 * Two kinds of chrome, decided by whether the page has a rail.
 *
 *   index    the grid, components, 404. The bar is the only navigation there,
 *            so it carries everything and earns its place.
 *   reading  a section. The rail is the navigation, and it holds the mark and
 *            the tools at its head, so the bar has nothing left to do and is
 *            not drawn at all. On a narrow screen the rail is a drawer, which
 *            cannot hold a mark that has to stay visible, so a slim bar comes
 *            back with the mark, the menu and where you are.
 */
$chrome  = ($chrome ?? 'index') === 'reading' ? 'reading' : 'index';
$reading = $chrome === 'reading';
?>
<body class="chrome-<?= e($chrome) ?>"<?= style_body_attributes() ?>>
<?php // Every section page lists each section, then every chapter in the current
      // one, before the content. Without this a keyboard reader tabs through the
      // whole rail on every page before reaching a word of it. ?>
<a class="skip" href="#content">Skip to the content</a>
<span id="top"></span>
<header class="masthead<?= $reading ? ' masthead--slim' : '' ?>" id="masthead">
  <button class="masthead__menu" type="button" id="nav-toggle"
          aria-expanded="false" aria-controls="reader-nav" hidden>
    <span class="masthead__bars" aria-hidden="true"></span>
    <span class="visually-hidden">Menu</span>
  </button>
  <?= site_mark('mark') ?>

  <?php if ($reading): ?>
    <?php // Where you are, so the drawer does not have to be opened to find out. ?>
    <p class="masthead__where"><?= e($section ?? '') ?></p>
  <?php elseif (components_enabled()): ?>
    <nav class="areas" aria-label="Areas">
      <?php foreach (site_areas() as $area): ?>
        <a class="area<?= ($currentArea ?? 'brand') === $area['key'] ? ' is-on' : '' ?>"
           href="<?= e($area['href']) ?>"><?= e($area['label']) ?></a>
      <?php endforeach; ?>
    </nav>
  <?php else: ?>
    <p class="masthead__title">Brand guidelines</p>
  <?php endif; ?>

  <nav class="masthead__tools">
    <?php // A real link, so it still goes somewhere useful without JavaScript.
          // search.js turns it into the dialog. ?>
    <a class="search-open" href="/" data-search>
      <?= ui_icon('search', 'icon icon--small') ?>
      <span>Search</span>
      <kbd>/</kbd>
    </a>
    <?php if (can_edit()): ?>
      <a href="/admin">Edit</a>
      <a href="/admin/sign-out">Sign out</a>
    <?php else: ?>
      <a href="/admin/sign-in">Sign in</a>
    <?php endif; ?>
  </nav>
</header>
<?= $content ?>
<footer class="foot">
  <p><?= e((string) setting('footer_note', 'Questions about anything here, or something missing, speak to the brand team.')) ?></p>
</footer>
<?php
// Clicking a category in the rail loads a whole document, where clicking a
// chapter only scrolls, and the pause between the two is what made moving
// between categories feel awkward. Rather than cover the gap with an animation,
// the next section is fetched while the pointer is still on the link, so by the
// time it is clicked there is usually nothing to wait for.
//
// "moderate" means on hover, not on sight, so idle reading does not pull down
// every section. Browsers without speculation rules ignore this entirely and
// navigate exactly as before.
?>
<script type="speculationrules">
{
  "prefetch": [{
    "source": "document",
    "where": { "href_matches": "/s/*" },
    "eagerness": "moderate"
  }]
}
</script>
<script src="<?= e(asset_url('/assets/js/vendor/motion.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/motion-ui.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/site.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/search.js')) ?>"></script>
</body>
</html>
