<?php
/**
 * One component: the real thing, its states, its code and the rules.
 *
 * Nothing on this page prints a variant's markup as markup. It appears twice —
 * rendered inside the sandboxed frame, and escaped inside a `<pre>` for copying
 * — and the second of those is the one that would be stored XSS if it were ever
 * written unescaped. `check-components.php` asserts it.
 *
 * Expects: $component, $variants, $open.
 */
$showing = $open;
$frameBase = '/components/' . rawurlencode($component['slug']) . '/frame/';
$figma = component_link($component['figma_url'] ?? null);

// The code panel shows the base CSS with the variant's own appended, because
// that is what someone copying it needs — the variant alone is missing the half
// that makes it look like anything.
$css = trim((string) $component['base_css']);
$variantCss = $showing ? trim((string) $showing['variant_css']) : '';
if ($variantCss !== '') {
    $css = trim($css . "\n\n" . $variantCss);
}
$docs = json_decode((string) $component['docs'], true) ?: [];
?>
<main class="reader__body component" id="content">

  <p class="component__crumb"><a href="/components">Components</a></p>

  <header class="component__head">
    <h1><?= e($component['name']) ?></h1>
    <?php if ($component['status'] !== 'ready'): ?>
      <p class="component__status component__status--<?= e($component['status']) ?>">
        <?php if ($component['status'] === 'deprecated'): ?>
          Deprecated. It is documented because it is still in the theme, not
          because it should be used for anything new.
        <?php else: ?>
          Draft. Not finished, and only visible to you because you can edit.
        <?php endif; ?>
      </p>
    <?php endif; ?>
    <?php if (!empty($component['summary'])): ?>
      <p class="component__summary"><?= e($component['summary']) ?></p>
    <?php endif; ?>
  </header>

  <?php if (!$showing): ?>
    <p class="component__empty">This component has no variants yet, so there is
       nothing to show.</p>

  <?php else: ?>

    <section class="component__preview">
      <div class="component__toolbar">
        <?php if (count($variants) > 1): ?>
          <span class="component__chips" role="group" aria-label="Variant">
            <?php foreach ($variants as $variant): ?>
              <?php $on = (int) $variant['id'] === (int) $showing['id']; ?>
              <?php // Real links, so the switcher works without JavaScript; the
                    // script then swaps the frame rather than reloading. ?>
              <a class="component__chip<?= $on ? ' is-on' : '' ?>"
                 href="/components/<?= e($component['slug']) ?>?v=<?= (int) $variant['id'] ?>"
                 data-frame-src="<?= e($frameBase . (int) $variant['id']) ?>"
                 data-variant-id="<?= (int) $variant['id'] ?>"
                 <?= $on ? 'aria-current="true"' : '' ?>><?= e($variant['label']) ?></a>
            <?php endforeach; ?>
          </span>
        <?php endif; ?>

        <span class="component__toggles">
          <?php foreach (['auto' => 'Fill', '375' => 'Phone', '768' => 'Tablet'] as $width => $label): ?>
            <button class="component__chip<?= $width === 'auto' ? ' is-on' : '' ?>"
                    type="button" data-frame-width="<?= $width ?>"><?= $label ?></button>
          <?php endforeach; ?>
          <button class="component__chip" type="button" data-frame-scheme="dark"
                  aria-pressed="false">Dark</button>
        </span>
      </div>

      <div class="component__stage" data-frame-stage>
        <iframe class="component__frame"
                id="cmp-frame"
                title="<?= e($component['name']) ?>, <?= e($showing['label']) ?>"
                src="<?= e($frameBase . (int) $showing['id']) ?>"
                sandbox="allow-scripts"
                referrerpolicy="no-referrer"></iframe>
      </div>

      <?php // A note belongs to its variant, so it is written out for all of them
            // and shown for the one on screen. Switching without a reload has to
            // change the note too, or it goes stale the moment a chip is clicked. ?>
      <?php foreach ($variants as $variant): ?>
        <?php if (empty($variant['notes'])) { continue; } ?>
        <p class="component__note" data-variant-note="<?= (int) $variant['id'] ?>"
           <?= (int) $variant['id'] === (int) $showing['id'] ? '' : 'hidden' ?>><?= e($variant['notes']) ?></p>
      <?php endforeach; ?>
    </section>

    <section class="component__code">
      <h2>The code</h2>

      <?php foreach ($variants as $variant): ?>
        <?php // One panel per variant, all but the shown one hidden, so switching
              // needs no request. The markup is escaped; it is never live here. ?>
        <figure class="code code--scroll component__panel" data-variant-code="<?= (int) $variant['id'] ?>"
                <?= (int) $variant['id'] === (int) $showing['id'] ? '' : 'hidden' ?>>
          <figcaption class="code__bar">
            <span class="code__lang">HTML &middot; <?= e($variant['label']) ?></span>
            <button class="code__copy" type="button" data-copy-code>Copy</button>
          </figcaption>
          <pre class="code__pre"><code><?= e(trim((string) $variant['markup'])) ?></code></pre>
        </figure>
      <?php endforeach; ?>

      <?php if ($css !== ''): ?>
        <figure class="code code--scroll component__panel">
          <figcaption class="code__bar">
            <span class="code__lang">CSS</span>
            <button class="code__copy" type="button" data-copy-code>Copy</button>
          </figcaption>
          <pre class="code__pre"><code><?= e($css) ?></code></pre>
        </figure>
      <?php endif; ?>

      <?php if (trim((string) $component['base_js']) !== ''): ?>
        <figure class="code code--scroll component__panel">
          <figcaption class="code__bar">
            <span class="code__lang">JavaScript</span>
            <button class="code__copy" type="button" data-copy-code>Copy</button>
          </figcaption>
          <pre class="code__pre"><code><?= e(trim((string) $component['base_js'])) ?></code></pre>
        </figure>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($docs): ?>
    <?php // The written guidance, through the same block engine the guidelines
          // use. The editor for it is phase 7; the renderer costs nothing now
          // and means phase 7 is only the editing side. ?>
    <section class="component__docs">
      <?php render_blocks($docs); ?>
    </section>
  <?php endif; ?>

  <?php if ($figma !== null || can_edit()): ?>
    <footer class="component__foot">
      <?php if ($figma !== null): ?>
        <a class="component__link" href="<?= e($figma) ?>" target="_blank" rel="noopener noreferrer">Open in Figma</a>
      <?php endif; ?>
      <?php if (can_edit()): ?>
        <a class="component__link" href="/admin/components/<?= (int) $component['id'] ?>">Edit this component</a>
      <?php endif; ?>
    </footer>
  <?php endif; ?>
</main>
