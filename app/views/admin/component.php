<?php
/**
 * One component: its base CSS and its variants on the left, the live sandboxed
 * preview on the right.
 *
 * Which variant is previewed is decided by `?v=`, server-side, so the screen
 * works with no JavaScript at all — the chips are real links. components.js then
 * swaps the frame without a reload, which matters because a reload would lose
 * whatever is half-typed in a textarea below.
 *
 * Nothing here prints a variant's markup as HTML. It goes into a textarea, where
 * it is escaped like any other value, and into the frame, which is the only place
 * it renders. Phase 3 adds the metadata and autosave; phase 5 the derived spec.
 */
$chosen = null;
$wanted = (int) ($_GET['v'] ?? 0);
foreach ($variants as $variant) {
    if ((int) $variant['id'] === $wanted) {
        $chosen = $variant;
        break;
    }
}
$showing = $chosen ?? $open;
$frameBase = '/components/' . rawurlencode($component['slug']) . '/frame/';
?>
<div class="m3-page">
  <div class="m3-page__bar">
    <div class="m3-page__titles">
      <p class="m3-page__crumb"><a href="/admin/components">Components</a></p>
      <h1><?= e($component['name']) ?></h1>
      <p class="m3-page__meta">
        <?= e($component['group_name']) ?> &middot;
        <?= e(component_status_label($component['status'])) ?> &middot;
        <?= count($variants) ?> <?= count($variants) === 1 ? 'variant' : 'variants' ?> &middot;
        <code>/components/<?= e($component['slug']) ?></code>
      </p>
    </div>
    <div class="m3-page__action">
      <a class="m3-button m3-button--text m3-state" href="/admin/components/<?= (int) $component['id'] ?>/versions">
        <?= ui_icon('history', 'icon') ?> History
      </a>
    </div>
  </div>

  <div class="cmp">

    <!-- ------------------------------------------------------------ the work -->
    <div class="cmp__work">

      <section class="m3-card">
        <h2 class="m3-card__title">Details</h2>
        <form method="post" action="/admin/components/<?= (int) $component['id'] ?>/meta" data-guard>
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

          <div class="m3-field">
            <input class="m3-field__input" type="text" name="name" id="name" placeholder=" "
                   maxlength="190" value="<?= e($component['name']) ?>">
            <label class="m3-field__label" for="name">Name</label>
          </div>

          <div class="m3-editor__metarow">
            <div class="m3-field">
              <input class="m3-field__input" type="text" name="group_name" id="group" list="componentgroups"
                     placeholder=" " maxlength="120" value="<?= e($component['group_name']) ?>">
              <label class="m3-field__label" for="group">Group</label>
              <datalist id="componentgroups">
                <?php foreach ($groups as $name): ?>
                  <option value="<?= e($name) ?>"></option>
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="m3-field">
              <select class="m3-field__input" name="status" id="status">
                <?php foreach (component_statuses() as $value => $label): ?>
                  <option value="<?= e($value) ?>"<?= $component['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <label class="m3-field__label" for="status">Status</label>
            </div>
          </div>
          <p class="m3-field__support">Only <strong>Ready</strong> is visible to
             anyone who cannot edit. Deprecated stays readable, and says so.</p>

          <div class="m3-field">
            <input class="m3-field__input" type="text" name="summary" id="summary" placeholder=" "
                   maxlength="400" value="<?= e($component['summary'] ?? '') ?>">
            <label class="m3-field__label" for="summary">Summary</label>
          </div>

          <div class="m3-field">
            <input class="m3-field__input" type="text" name="slug" id="slug" placeholder=" "
                   maxlength="190" value="<?= e($component['slug']) ?>">
            <label class="m3-field__label" for="slug">URL</label>
          </div>
          <p class="m3-field__support">Changing this breaks any link already shared.
             It does not follow a rename, for that reason.</p>

          <div class="m3-field">
            <input class="m3-field__input" type="url" name="figma_url" id="figma" placeholder=" "
                   maxlength="500" value="<?= e($component['figma_url'] ?? '') ?>">
            <label class="m3-field__label" for="figma">Figma link</label>
          </div>
          <p class="m3-field__support">
            Right-click the component in Figma and Copy link to selection. Nothing
            is fetched — the link is kept, and the file and node read out of it.
            <?php if (!empty($component['figma_file_key'])): ?>
              <br>Read: file <code><?= e($component['figma_file_key']) ?></code><?php
                if (!empty($component['figma_node_id'])): ?>, node
                <code><?= e($component['figma_node_id']) ?></code><?php
                else: ?>, no node — the link points at the whole file<?php endif; ?>.
            <?php elseif (!empty($component['figma_url'])): ?>
              <br>Kept as a link, but it is not a Figma URL this recognises.
            <?php endif; ?>
          </p>

          <details class="cmp__advanced">
            <summary>JavaScript</summary>
            <p class="m3-field__support">Only for a component that genuinely needs
               it — a disclosure, a carousel. It runs in the sandbox, which can
               reach nothing outside itself and cannot make network requests.</p>
            <div class="m3-field">
              <textarea class="m3-field__input m3-field__textarea cmp__code" name="base_js" rows="6"
                        id="basejs" placeholder=" "
                        spellcheck="false" autocapitalize="off"><?= e($component['base_js']) ?></textarea>
              <label class="m3-field__label" for="basejs">JS</label>
            </div>
          </details>

          <button class="m3-button m3-button--filled m3-state" type="submit">Save details</button>
        </form>
      </section>

      <section class="m3-card">
        <h2 class="m3-card__title">Variants</h2>
        <p class="m3-field__support">One per state worth documenting. The default is
           the one the component page opens on.</p>

        <?php if (!$variants): ?>
          <p class="m3-field__support">None yet. Add one and paste the markup into it.</p>
        <?php endif; ?>

        <?php foreach ($variants as $i => $variant): ?>
          <?php $isShowing = $showing && (int) $variant['id'] === (int) $showing['id']; ?>
          <details class="cmp__variant"<?= $isShowing ? ' open' : '' ?>>
            <summary class="cmp__variant-head">
              <span class="cmp__variant-label"><?= e($variant['label']) ?></span>
              <?php if ((int) $variant['is_default'] === 1): ?>
                <span class="m3-chip m3-chip--draft">Default</span>
              <?php endif; ?>
              <?php if (trim((string) $variant['markup']) === ''): ?>
                <span class="cmp__variant-empty">Empty</span>
              <?php endif; ?>
            </summary>

            <form class="cmp__form" method="post" data-guard
                  action="/admin/components/<?= (int) $component['id'] ?>/variants/<?= (int) $variant['id'] ?>/save">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

              <div class="m3-field">
                <input class="m3-field__input" type="text" name="label" placeholder=" " maxlength="190"
                       id="label-<?= (int) $variant['id'] ?>" value="<?= e($variant['label']) ?>">
                <label class="m3-field__label" for="label-<?= (int) $variant['id'] ?>">Label</label>
              </div>

              <div class="m3-field">
                <textarea class="m3-field__input m3-field__textarea cmp__code" name="markup" rows="8"
                          id="markup-<?= (int) $variant['id'] ?>" placeholder=" "
                          spellcheck="false" autocapitalize="off"><?= e($variant['markup']) ?></textarea>
                <label class="m3-field__label" for="markup-<?= (int) $variant['id'] ?>">Markup</label>
              </div>

              <div class="m3-field">
                <textarea class="m3-field__input m3-field__textarea cmp__code" name="variant_css" rows="5"
                          id="css-<?= (int) $variant['id'] ?>" placeholder=" "
                          spellcheck="false" autocapitalize="off"><?= e($variant['variant_css']) ?></textarea>
                <label class="m3-field__label" for="css-<?= (int) $variant['id'] ?>">CSS for this variant only</label>
              </div>

              <div class="m3-field">
                <input class="m3-field__input" type="text" name="notes" placeholder=" " maxlength="400"
                       id="notes-<?= (int) $variant['id'] ?>" value="<?= e($variant['notes'] ?? '') ?>">
                <label class="m3-field__label" for="notes-<?= (int) $variant['id'] ?>">Note</label>
              </div>

              <button class="m3-button m3-button--filled m3-state" type="submit">Save variant</button>
            </form>

            <div class="cmp__variant-actions">
              <?php if ((int) $variant['is_default'] !== 1): ?>
                <form method="post" action="/admin/components/<?= (int) $component['id'] ?>/variants/<?= (int) $variant['id'] ?>/default">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <button class="m3-button m3-button--text m3-state" type="submit">Make default</button>
                </form>
              <?php endif; ?>

              <?php foreach (['up' => '&uarr;', 'down' => '&darr;'] as $dir => $glyph): ?>
                <?php $off = ($dir === 'up' && $i === 0) || ($dir === 'down' && $i === count($variants) - 1); ?>
                <form method="post" action="/admin/components/<?= (int) $component['id'] ?>/variants/<?= (int) $variant['id'] ?>/move/<?= $dir ?>">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <button class="m3-icon-button m3-state" type="submit"<?= $off ? ' disabled' : '' ?>
                          aria-label="Move <?= e($variant['label']) ?> <?= $dir ?>"><?= $glyph ?></button>
                <?php // Keeping the form open would swallow the next one. ?>
                </form>
              <?php endforeach; ?>

              <form method="post" action="/admin/components/<?= (int) $component['id'] ?>/variants/<?= (int) $variant['id'] ?>/delete"
                    data-confirm="Delete the &quot;<?= e($variant['label']) ?>&quot; variant? This cannot be undone.">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <button class="m3-button m3-button--text m3-state cmp__danger" type="submit">Delete</button>
              </form>
            </div>
          </details>
        <?php endforeach; ?>

        <form class="cmp__add" method="post" action="/admin/components/<?= (int) $component['id'] ?>/variants/new">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <div class="m3-field m3-field--dense">
            <input class="m3-field__input" type="text" name="label" id="newvariant" placeholder=" " maxlength="190">
            <label class="m3-field__label" for="newvariant">New variant</label>
          </div>
          <button class="m3-button m3-button--text m3-state" type="submit"><?= ui_icon('add', 'icon') ?> Add</button>
        </form>
      </section>

      <section class="m3-card">
        <h2 class="m3-card__title">Base CSS</h2>
        <p class="m3-field__support">Shared by every variant. The brand tokens are
           already there, so <code>var(--s-radius)</code> and the palette's custom
           properties work without declaring them.</p>
        <form method="post" action="/admin/components/<?= (int) $component['id'] ?>/css" data-guard>
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <div class="m3-field">
            <textarea class="m3-field__input m3-field__textarea cmp__code" name="base_css" rows="14"
                      id="basecss" placeholder=" "
                      spellcheck="false" autocapitalize="off"><?= e($component['base_css']) ?></textarea>
            <label class="m3-field__label" for="basecss">CSS</label>
          </div>
          <button class="m3-button m3-button--filled m3-state" type="submit">Save CSS</button>
        </form>
      </section>
    </div>

    <!-- --------------------------------------------------------- the preview -->
    <div class="cmp__preview">
      <?php if (!$showing): ?>
        <div class="m3-empty-state">
          <h2>Nothing to preview yet</h2>
          <p>Add a variant, paste its markup, and it renders here.</p>
        </div>
      <?php else: ?>
        <div class="cmp__toolbar">
          <span class="cmp__chips" role="group" aria-label="Variant">
            <?php foreach ($variants as $variant): ?>
              <?php $on = (int) $variant['id'] === (int) $showing['id']; ?>
              <a class="m3-chip m3-chip--filter m3-state<?= $on ? ' is-on' : '' ?>"
                 href="/admin/components/<?= (int) $component['id'] ?>?v=<?= (int) $variant['id'] ?>"
                 data-frame-src="<?= e($frameBase . (int) $variant['id']) ?>"
                 <?= $on ? 'aria-current="true"' : '' ?>><?= e($variant['label']) ?></a>
            <?php endforeach; ?>
          </span>

          <span class="cmp__toggles">
            <?php foreach (['auto' => 'Fill', '375' => 'Phone', '768' => 'Tablet'] as $width => $label): ?>
              <button class="m3-chip m3-chip--filter m3-state<?= $width === 'auto' ? ' is-on' : '' ?>"
                      type="button" data-frame-width="<?= $width ?>"><?= $label ?></button>
            <?php endforeach; ?>
            <button class="m3-chip m3-chip--filter m3-state" type="button" data-frame-scheme="dark"
                    aria-pressed="false">Dark</button>
          </span>
        </div>

        <?php if (!empty($showing['notes'])): ?>
          <p class="cmp__note"><?= e($showing['notes']) ?></p>
        <?php endif; ?>

        <?php /*
          sandbox="allow-scripts" and deliberately NOT allow-same-origin. Both
          together are equivalent to no sandbox: a frame with same-origin access
          can reach its own element and remove the attribute. Without it the
          document is an opaque origin and cannot touch this page, the session or
          storage. See component_frame_headers() for the rest of the defence.
        */ ?>
        <div class="cmp__stage" data-frame-stage>
          <iframe class="cmp__frame"
                  id="cmp-frame"
                  title="<?= e($component['name']) ?>, <?= e($showing['label']) ?>"
                  src="<?= e($frameBase . (int) $showing['id']) ?>"
                  sandbox="allow-scripts"
                  referrerpolicy="no-referrer"
                  loading="lazy"></iframe>
        </div>

        <p class="cmp__frame-note">
          Rendered in a sandbox with no access to this page. It carries the brand
          tokens and nothing else, so what you see is the component's own CSS
          doing the work.
        </p>
      <?php endif; ?>

      <?php // The spec of the base CSS, with every row's note editable. It sits
            // under the preview so an off-token value is seen while it is being
            // written rather than after. ?>
      <?php partial('component-spec', [
        'spec'         => $spec,
        'specEditable' => '/admin/components/' . (int) $component['id'] . '/notes',
      ]); ?>
    </div>
  </div>
</div>
