<?php
/**
 * One variant of one component, rendered on its own.
 *
 * This is the only document in the project that emits author-supplied HTML, CSS
 * and JavaScript unescaped. It is served with the headers in
 * `component_frame_headers()` and is only ever loaded into an iframe carrying
 * `sandbox="allow-scripts"` without `allow-same-origin`, which makes it an opaque
 * origin with no access to the parent, the session or storage. Read section 4 of
 * docs/COMPONENTS.md before changing anything here.
 *
 * It deliberately does not load site.css. A component should look like itself,
 * styled by its own CSS and the brand tokens, not like a block on a guidelines
 * page — otherwise the preview flatters it with styling the real component will
 * not have.
 *
 * Expects: $component, $variant, $scheme ('light' or 'dark').
 */
?>
<!doctype html>
<html lang="en-GB" data-scheme="<?= e($scheme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="<?= $scheme === 'dark' ? 'dark' : 'light' ?>">
<title><?= e($component['name']) ?> &middot; <?= e($variant['label']) ?></title>
<?= font_head() ?>
<?php // The brand's own colour tokens, the same ones the guidelines publish. ?>
<link rel="stylesheet" href="/tokens.css">
<style>
<?php
/**
 * The brand's own typefaces.
 *
 * This frame deliberately does not load site.css, which is where the public
 * site declares its faces — so a component asking for More Years got the
 * fallback, and type imported from Figma never matched the design however
 * carefully the CSS was pasted. It was the one thing that could not be fixed by
 * editing the component.
 *
 * Every face on the server is declared here, by the same function the type
 * specimen reads its dropdown from, so the frame and the guidelines can never
 * disagree about which typefaces exist.
 */
?>
<?= brand_font_faces() ?>

<?= style_css() ?>

/* A floor, not a house style. Enough that a component is not fighting the
   browser's defaults, and no more — anything else would be styling the preview
   rather than the component, and the component would look different in the
   theme where none of this exists. */
*, *::before, *::after { box-sizing: border-box; }
html { -webkit-text-size-adjust: 100%; }
body {
  margin: 0;
  padding: var(--frame-pad, 1.5rem);
  font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
  font-size: var(--s-text, 1rem);
  line-height: var(--s-line, 1.6);
  color: <?= $scheme === 'dark' ? '#F2F0F4' : 'var(--s-ink, #1F1F1F)' ?>;
  background: <?= $scheme === 'dark' ? '#17161A' : 'var(--s-page, #FFFFFF)' ?>;
}
img, svg, video { max-width: 100%; height: auto; }
</style>
<?php // The component's own CSS, base first so a variant can override it. ?>
<style><?= $component['base_css'] ?></style>
<style><?= $variant['variant_css'] ?></style>
</head>
<body>
<?= $variant['markup'] ?>

<script>
/**
 * Reports this frame's height to whatever is showing it, so the iframe can be
 * sized to its content rather than guessed at or given a scrollbar.
 *
 * It posts to '*' and the parent does NOT check the origin, which is the
 * opposite of the rule the page preview follows — and correct here. A frame
 * sandboxed without allow-same-origin has an opaque origin, so its messages
 * arrive with an origin of "null" and there is nothing to validate against.
 * The check that does the work is on the other side: the parent compares
 * event.source with the contentWindow of the iframe it created, which a
 * different frame cannot forge. Nothing sensitive is in the message, and
 * frame-ancestors 'self' means no other site can be showing this anyway.
 */
(function () {
  var last = 0;

  function report() {
    var height = Math.ceil(document.documentElement.getBoundingClientRect().height);
    if (height === last) return;
    last = height;
    parent.postMessage({ source: 'component-frame', height: height }, '*');
  }

  report();
  window.addEventListener('load', report);

  // Fonts and images change the height after first paint, and a component can
  // change its own size when it is interacted with.
  if (window.ResizeObserver) {
    new ResizeObserver(report).observe(document.documentElement);
  } else {
    setInterval(report, 500);
  }
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(report);
  }
})();
</script>
<?php if (trim((string) $component['base_js']) !== ''): ?>
<script><?= $component['base_js'] ?></script>
<?php endif; ?>
<?php // The measurements, on request. Last, so it measures the component after
      // its own script has had its say. ?>
<?php if (!empty($inspect)): ?>
  <script src="<?= e(asset_url('/assets/js/component-inspect.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
