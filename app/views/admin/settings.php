<main class="wrap">
  <div class="wrap__head"><h1>Settings</h1></div>

  <?php if ($notice): ?><p class="notice"><?= e($notice) ?></p><?php endif; ?>

  <?php if (!empty($pending)): ?>
    <section class="panel">
      <h2>Database updates waiting</h2>
      <p class="hint">These come with new features and need applying once: <?= e(implode(', ', $pending)) ?></p>
      <form method="post" action="/admin/migrate">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button type="submit">Apply updates</button>
      </form>
    </section>
  <?php endif; ?>

  <form method="post" class="panel">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

    <label class="field">Site name
      <input type="text" name="site_name" value="<?= e((string) setting('site_name', $config['site']['name'] ?? 'Years brand hub')) ?>">
    </label>

    <label class="field">Footer note
      <input type="text" name="footer_note" value="<?= e((string) setting('footer_note', 'Questions about anything here, or something missing, speak to the brand team.')) ?>">
    </label>

    <label class="check">
      <input type="checkbox" name="require_login_to_view" value="1"<?= site_is_private($config) ? ' checked' : '' ?>>
      <span>
        <strong>Require a sign-in to read the site</strong>
        <span class="hint">Off means anyone with the link can read the guidelines. On means only people with an account can. Editing always requires signing in either way.</span>
      </span>
    </label>

    <button type="submit">Save settings</button>
  </form>
</main>
