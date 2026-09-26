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

<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">

<?php if (fonts_are_local()): ?>
  <link rel="preload" href="<?= e(asset_url('/assets/fonts/plus-jakarta-sans-400.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<?php else: ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<?php endif; ?>
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
<body<?= style_body_attributes() ?>>
<?php // Every section page lists each section, then every chapter in the current
      // one, before the content. Without this a keyboard reader tabs through the
      // whole rail on every page before reaching a word of it. ?>
<a class="skip" href="#content">Skip to the content</a>
<span id="top"></span>
<header class="masthead" id="masthead">
  <?php $logo = site_logo(); ?>
  <button class="masthead__menu" type="button" id="nav-toggle"
          aria-expanded="false" aria-controls="reader-nav" hidden>
    <span class="masthead__bars" aria-hidden="true"></span>
    <span class="visually-hidden">Menu</span>
  </button>
  <a class="masthead__mark" href="/">
    <?php if ($logo): ?>
      <img src="<?= e(asset_image_url($logo)) ?>" alt="Years" class="masthead__logo">
    <?php else: ?>
      <span class="masthead__wordmark">Years</span>
    <?php endif; ?>
  </a>

  <?php if (components_enabled()): ?>
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
</body>
</html>
