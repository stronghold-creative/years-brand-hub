<?php
// Everything the live preview needs to rebuild the custom properties without a
// round trip: which property each field writes, its unit, and which ones set a
// data attribute on the body instead.
$wiring = [];
foreach (style_flat() as $key => $field) {
    $wiring[$key] = [
        'css'  => $field['css'] ?? null,
        'unit' => $field['type'] === 'number' ? ($field['unit'] ?? '') : '',
        'body' => $field['body'] ?? null,
    ];
}
?>
<div class="m3-page m3-page--styles" id="styles" data-wiring='<?= e(json_encode($wiring, JSON_UNESCAPED_SLASHES)) ?>'>
  <div class="m3-page__bar">
    <div class="m3-page__titles">
      <h1>Appearance</h1>
      <p class="m3-page__meta">Site-wide values. Any block can override these individually.</p>
    </div>
    <div class="m3-page__action">
      <button class="m3-button m3-button--filled m3-state" type="submit" form="style-form">Save</button>
    </div>
  </div>

  <?php if ($notice): ?><p class="m3-banner"><?= e($notice) ?></p><?php endif; ?>

  <div class="m3-styles">
  <form method="post" id="style-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

    <?php foreach (style_fields() as $group => $fields): ?>
      <section class="m3-card">
        <h2 class="m3-card__title"><?= e($group) ?></h2>
        <div class="m3-stylegrid">
          <?php foreach ($fields as $key => $field): ?>
            <?php $value = style_value($key); ?>

            <?php if ($field['type'] === 'number'): ?>
              <div class="numfield" data-key="<?= e($key) ?>" data-default="<?= e((string) $field['default']) ?>">
                <label class="numfield__label" for="s-<?= e($key) ?>">
                  <?= e($field['label']) ?>
                  <?php // Only offered when the value is not already the default;
                        // shown or hidden again by admin.js as it is changed. ?>
                  <button class="numfield__reset" type="button" data-reset="<?= e($key) ?>"
                          title="Put this back to <?= e((string) $field['default']) ?>"
                          aria-label="Reset <?= e($field['label']) ?>"<?= $value === (string) $field['default'] ? ' hidden' : '' ?>>Reset</button>
                </label>
                <div class="numfield__controls">
                  <input class="numfield__input" type="number" inputmode="decimal"
                         name="<?= e($key) ?>" id="s-<?= e($key) ?>"
                         value="<?= e($value) ?>"
                         min="<?= e((string) $field['min']) ?>" max="<?= e((string) $field['max']) ?>"
                         step="<?= e((string) $field['step']) ?>">
                  <?php if (!empty($field['unit'])): ?>
                    <span class="numfield__unit"><?= e($field['unit']) ?></span>
                  <?php endif; ?>
                  <input class="numfield__slider" type="range" aria-label="<?= e($field['label']) ?> slider"
                         value="<?= e($value) ?>"
                         min="<?= e((string) $field['min']) ?>" max="<?= e((string) $field['max']) ?>"
                         step="<?= e((string) $field['step']) ?>">
                </div>
                <?php if (!empty($field['presets'])): ?>
                  <div class="numfield__presets">
                    <?php foreach ($field['presets'] as $label => $preset): ?>
                      <button class="m3-chip m3-chip--filter m3-state" type="button"
                              data-preset="<?= e((string) $preset) ?>"><?= e((string) $label) ?></button>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($field['help'])): ?>
                  <span class="numfield__help"><?= e($field['help']) ?></span>
                <?php endif; ?>
              </div>

            <?php elseif ($field['type'] === 'colour'): ?>
              <div class="numfield colourfield" data-key="<?= e($key) ?>" data-default="<?= e((string) $field['default']) ?>">
                <label class="numfield__label" for="s-<?= e($key) ?>">
                  <?= e($field['label']) ?>
                  <button class="numfield__reset" type="button" data-reset="<?= e($key) ?>"
                          title="Put this back to <?= e((string) $field['default']) ?>"
                          aria-label="Reset <?= e($field['label']) ?>"<?= strtoupper($value) === strtoupper((string) $field['default']) ? ' hidden' : '' ?>>Reset</button>
                </label>
                <div class="numfield__controls">
                  <input class="colourfield__swatch" type="color" value="<?= e($value) ?>"
                         aria-label="<?= e($field['label']) ?> picker">
                  <input class="numfield__input colourfield__hex" type="text"
                         name="<?= e($key) ?>" id="s-<?= e($key) ?>" value="<?= e($value) ?>"
                         spellcheck="false" maxlength="7">
                </div>
              </div>

            <?php else: ?>
              <div class="m3-field m3-field--select">
                <select class="m3-field__select" name="<?= e($key) ?>" id="s-<?= e($key) ?>">
                  <?php foreach ($field['options'] as $optionValue => $label): ?>
                    <option value="<?= e($optionValue) ?>"<?= $value === $optionValue ? ' selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <label class="m3-field__label" for="s-<?= e($key) ?>"><?= e($field['label']) ?></label>
              </div>
            <?php endif; ?>

          <?php endforeach; ?>
        </div>

        <?php if ($group === 'Colour'): ?>
          <?php
            // The pairs a reader actually has to read, measured with the same
            // functions the contrast block uses, so the admin and the
            // guidelines agree about what passes. Recomputed live in
            // admin.js as the colours change.
            $pairs = [
              ['Text on the page',    'ink',    'page'],
              ['Text on a panel',     'ink',    'raised'],
              ['Accent on the page',  'accent', 'page'],
              ['White on the accent', '#FFFFFF', 'accent'],
            ];
          ?>
          <div class="m3-contrast" id="contrast-check">
            <h3 class="m3-contrast__title">Contrast</h3>
            <ul class="m3-contrast__list">
              <?php foreach ($pairs as [$label, $fg, $bg]): ?>
                <?php
                  $fgValue = str_starts_with($fg, '#') ? $fg : style_value($fg);
                  $bgValue = str_starts_with($bg, '#') ? $bg : style_value($bg);
                  $ratio = contrast_ratio($fgValue, $bgValue);
                  $verdict = contrast_verdict($ratio);
                ?>
                <li class="m3-contrast__row<?= $verdict['pass'] ? '' : ' is-failing' ?>"
                    data-fg="<?= e($fg) ?>" data-bg="<?= e($bg) ?>">
                  <span class="m3-contrast__what"><?= e($label) ?></span>
                  <span class="m3-contrast__ratio"><?= e(number_format($ratio, 2)) ?>:1</span>
                  <span class="m3-contrast__verdict"><?= e($verdict['level']) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
            <p class="m3-contrast__note">Measured the same way the contrast block measures. Body text wants 4.5:1.</p>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

    <div class="m3-styles__actions">
      <button class="m3-button m3-button--filled m3-state" type="submit">Save appearance</button>
      <button class="m3-button m3-button--text m3-button--danger m3-state" type="submit"
              name="reset_all" value="1"
              data-confirm-click="Put every appearance setting back to its default? Anything you have changed here will be lost.">
        Reset everything
      </button>
    </div>
  </form>

  <?php // The real renderers and the real stylesheet, as the editor's canvas is,
        // so what is previewed cannot drift from what publishes. ?>
  <aside class="m3-styles__preview">
    <div class="m3-styles__previewhead">
      <span>Preview</span>
      <span class="m3-styles__previewnote" id="preview-state">Live</span>
    </div>
    <iframe id="style-preview" src="/admin/styles/preview" title="A sample of the site with these settings"></iframe>
  </aside>
  </div>
</div>
