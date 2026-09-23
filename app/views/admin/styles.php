<div class="m3-page">
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

  <form method="post" id="style-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

    <?php foreach (style_fields() as $group => $fields): ?>
      <section class="m3-card">
        <h2 class="m3-card__title"><?= e($group) ?></h2>
        <div class="m3-stylegrid">
          <?php foreach ($fields as $key => $field): ?>
            <?php $value = style_value($key); ?>

            <?php if ($field['type'] === 'number'): ?>
              <div class="numfield" data-key="<?= e($key) ?>">
                <label class="numfield__label" for="s-<?= e($key) ?>"><?= e($field['label']) ?></label>
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
              <div class="numfield colourfield">
                <label class="numfield__label" for="s-<?= e($key) ?>"><?= e($field['label']) ?></label>
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
      </section>
    <?php endforeach; ?>

    <button class="m3-button m3-button--filled m3-state" type="submit">Save appearance</button>
  </form>
</div>
