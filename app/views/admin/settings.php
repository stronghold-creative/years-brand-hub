<div class="m3-page">
  <div class="m3-page__bar">
    <div class="m3-page__titles"><h1>Settings</h1></div>
  </div>

  <?php if ($notice): ?><p class="m3-banner"><?= e($notice) ?></p><?php endif; ?>

  <?php if (!empty($pending)): ?>
    <section class="m3-card">
      <h2 class="m3-card__title">Database updates waiting</h2>
      <p class="m3-body-medium">These arrive with new features and need applying once: <?= e(implode(', ', $pending)) ?></p>
      <form method="post" action="/admin/migrate">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button class="m3-button m3-button--tonal m3-state" type="submit">Apply updates</button>
      </form>
    </section>
  <?php endif; ?>

  <form method="post" class="m3-card">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

    <div class="m3-field">
      <input class="m3-field__input" type="text" name="site_name" id="site_name" placeholder=" "
             value="<?= e((string) setting('site_name', $config['site']['name'] ?? 'Years brand hub')) ?>">
      <label class="m3-field__label" for="site_name">Site name</label>
    </div>

    <div class="m3-field">
      <input class="m3-field__input" type="text" name="footer_note" id="footer_note" placeholder=" "
             value="<?= e((string) setting('footer_note', 'Questions about anything here, or something missing, speak to the brand team.')) ?>">
      <label class="m3-field__label" for="footer_note">Footer note</label>
    </div>

    <div class="m3-field field--asset" id="logo-picker" data-cover="<?= (int) setting('site_logo', 0) ?>"></div>
    <input type="hidden" name="site_logo" id="site_logo" value="<?= (int) setting('site_logo', 0) ?>">

    <label class="m3-switch">
      <input class="m3-switch__input" type="checkbox" name="require_login_to_view" value="1"
             role="switch"<?= site_is_private($config) ? ' checked' : '' ?>>
      <span class="m3-switch__track"><span class="m3-switch__handle"></span></span>
      <span class="m3-switch__text">
        <strong>Require a sign-in to read the site</strong>
        <span>Off means anyone with the link can read the guidelines. On means only people with an account can. Editing always requires signing in either way.</span>
      </span>
    </label>

    <button class="m3-button m3-button--filled m3-state" type="submit">Save settings</button>
  </form>

  <form method="post" class="m3-card">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="site_name" value="<?= e((string) setting('site_name', 'Years brand hub')) ?>">
    <input type="hidden" name="footer_note" value="<?= e((string) setting('footer_note', '')) ?>">
    <input type="hidden" name="site_logo" value="<?= (int) setting('site_logo', 0) ?>">
    <?php if (site_is_private($config)): ?>
      <input type="hidden" name="require_login_to_view" value="1">
    <?php endif; ?>

    <h2 class="m3-card__title">Areas</h2>
    <p class="m3-body-medium">The toggle in the middle of the top bar. Brand is these guidelines.
       Components is for documenting web components for design and development.</p>

    <div class="m3-editor__metarow">
      <div class="m3-field">
        <input class="m3-field__input" type="text" name="area_one_label" id="area_one_label" placeholder=" "
               value="<?= e((string) setting('area_one_label', 'Brand')) ?>">
        <label class="m3-field__label" for="area_one_label">First area</label>
      </div>
      <div class="m3-field">
        <input class="m3-field__input" type="text" name="area_two_label" id="area_two_label" placeholder=" "
               value="<?= e((string) setting('area_two_label', 'Components')) ?>">
        <label class="m3-field__label" for="area_two_label">Second area</label>
      </div>
    </div>

    <label class="m3-switch">
      <input class="m3-switch__input" type="checkbox" name="components_enabled" value="1"
             role="switch"<?= components_enabled() ? ' checked' : '' ?>>
      <span class="m3-switch__track"><span class="m3-switch__handle"></span></span>
      <span class="m3-switch__text">
        <strong>Show the areas toggle</strong>
        <span>Leave this off until the components area has something in it. While it is off the top bar reads Brand guidelines.</span>
      </span>
    </label>

    <button class="m3-button m3-button--filled m3-state" type="submit">Save areas</button>
  </form>

  <section class="m3-card">
    <h2 class="m3-card__title">Backup</h2>
    <p class="m3-body-medium">Downloads every page, setting and asset record as one JSON file.
       Uploaded files themselves are not included; those live in storage on the server.</p>
    <a class="m3-button m3-button--tonal m3-state" href="/admin/export">Download a backup</a>
  </section>
</div>
