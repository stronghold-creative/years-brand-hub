<?php
/**
 * What the paste turned out to be, and what accepting it would do.
 *
 * Nothing has been written at this point. The fields below carry the proposal
 * back to the apply route, and they are editable, because the most common thing
 * to want here is most of what Dev Mode produced rather than all of it.
 *
 * The markup appears only inside a textarea, escaped like any other value. This
 * screen never renders it.
 *
 * Expects: $component, $variants, $found, $spec.
 */
$hasSomething = $found['markup'] !== '' || $found['css'] !== '' || !empty($found['figma']['url']);
$back = '/admin/components/' . (int) $component['id'];
?>
<div class="m3-page">
  <div class="m3-page__bar">
    <div class="m3-page__titles">
      <a class="m3-back" href="<?= e($back) ?>"><?= ui_icon('back', 'icon') ?> Back to <?= e($component['name']) ?></a>
      <h1>Import</h1>
      <p class="m3-page__meta">Nothing has been saved yet.</p>
    </div>
  </div>

  <section class="m3-card">
    <h2 class="m3-card__title"><?= e($found['label']) ?></h2>
    <?php if ($found['note'] !== ''): ?>
      <p class="imp__note<?= $found['refused'] ? ' imp__note--refused' : '' ?>"><?= e($found['note']) ?></p>
    <?php endif; ?>

    <?php if ($found['refused'] || !$hasSomething): ?>
      <p><a class="m3-button m3-button--text m3-state" href="<?= e($back) ?>">Back to the component</a></p>

    <?php else: ?>
      <form method="post" action="<?= e($back) ?>/import/apply" data-guard>
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="figma_url" value="<?= e((string) ($found['figma']['url'] ?? '')) ?>">

        <?php if (!empty($found['figma']['url'])): ?>
          <p class="m3-field__support">
            Link: <code><?= e($found['figma']['url']) ?></code>
            <?php if (!empty($found['figma']['file_key'])): ?>
              <br>File <code><?= e($found['figma']['file_key']) ?></code><?php
                if (!empty($found['figma']['node_id'])): ?>, node <code><?= e($found['figma']['node_id']) ?></code><?php endif; ?>
            <?php endif; ?>
          </p>
        <?php endif; ?>

        <?php if ($found['markup'] !== ''): ?>
          <div class="m3-field">
            <textarea class="m3-field__input m3-field__textarea cmp__code" name="markup" rows="10"
                      id="impmarkup" placeholder=" "
                      spellcheck="false" autocapitalize="off"><?= e($found['markup']) ?></textarea>
            <label class="m3-field__label" for="impmarkup">Markup</label>
          </div>

          <div class="m3-editor__metarow">
            <div class="m3-field">
              <select class="m3-field__input" name="variant" id="impvariant">
                <option value="0">A new variant</option>
                <?php foreach ($variants as $variant): ?>
                  <option value="<?= (int) $variant['id'] ?>">Replace &ldquo;<?= e($variant['label']) ?>&rdquo;</option>
                <?php endforeach; ?>
              </select>
              <label class="m3-field__label" for="impvariant">Where it goes</label>
            </div>
            <div class="m3-field">
              <input class="m3-field__input" type="text" name="label" id="implabel" placeholder=" "
                     maxlength="190" value="Imported">
              <label class="m3-field__label" for="implabel">New variant's name</label>
            </div>
          </div>
          <p class="m3-field__support">Replacing a variant overwrites its markup and
             keeps its CSS, its note and its place in the order.</p>
        <?php endif; ?>

        <?php if ($found['css'] !== ''): ?>
          <div class="m3-field">
            <textarea class="m3-field__input m3-field__textarea cmp__code" name="css" rows="12"
                      id="impcss" placeholder=" "
                      spellcheck="false" autocapitalize="off"><?= e($found['css']) ?></textarea>
            <label class="m3-field__label" for="impcss">CSS</label>
          </div>
          <p class="m3-field__support">Added to the end of the component's base CSS,
             not in place of it. Clear the base CSS first if you want a fresh start.</p>
        <?php endif; ?>

        <div class="imp__actions">
          <button class="m3-button m3-button--filled m3-state" type="submit">Accept</button>
          <a class="m3-button m3-button--text m3-state" href="<?= e($back) ?>">Discard</a>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <?php if (!$found['refused'] && $found['css'] !== ''): ?>
    <section class="m3-card">
      <h2 class="m3-card__title">What this CSS would bring in</h2>
      <p class="m3-field__support">Read from the pasted CSS, before it is accepted.
         Dev Mode writes hardcoded values where a token exists, and this is where
         they show up.</p>
      <?php partial('component-spec', ['spec' => $spec]); ?>
    </section>
  <?php endif; ?>
</div>
