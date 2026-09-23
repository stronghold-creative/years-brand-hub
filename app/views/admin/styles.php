<div class="m3-page">
  <div class="m3-page__bar">
    <div class="m3-page__titles">
      <h1>Appearance</h1>
      <p class="m3-page__meta">Site-wide defaults. Any block can override these individually.</p>
    </div>
  </div>

  <?php if ($notice): ?><p class="m3-banner"><?= e($notice) ?></p><?php endif; ?>

  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

    <?php foreach (style_fields() as $group => $fields): ?>
      <section class="m3-card">
        <h2 class="m3-card__title"><?= e($group) ?></h2>
        <div class="m3-stylegrid">
          <?php foreach ($fields as $key => $field): ?>
            <div class="m3-field m3-field--select">
              <select class="m3-field__select" name="<?= e($key) ?>" id="s-<?= e($key) ?>">
                <?php foreach ($field['options'] as $value => $label): ?>
                  <option value="<?= e($value) ?>"<?= style_value($key) === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <label class="m3-field__label" for="s-<?= e($key) ?>"><?= e($field['label']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <button class="m3-button m3-button--filled m3-state" type="submit">Save appearance</button>
  </form>
</div>
