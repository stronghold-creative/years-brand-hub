<?php
/**
 * Restoring from a backup: the review screen, and what happened afterwards.
 *
 * One template for three states — a problem with the file, a plan to confirm, and
 * a result — because they are three moments of one thing and splitting them
 * across views would mean three places to keep the wording honest.
 *
 * Expects one of: $error, or $plan + $payload + $json, or $done.
 */
$plan = $plan ?? null;
$done = $done ?? null;
$error = $error ?? '';
?>
<div class="m3-page">
  <div class="m3-page__bar">
    <div class="m3-page__titles">
      <a class="m3-back" href="/admin/settings"><?= ui_icon('back', 'icon') ?> Back to settings</a>
      <h1>Restore from a backup</h1>
      <?php if ($plan): ?>
        <p class="m3-page__meta">Nothing has changed yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($error !== ''): ?>
    <section class="m3-card">
      <h2 class="m3-card__title">That file cannot be used</h2>
      <p class="imp__note imp__note--refused"><?= e($error) ?></p>
      <p><a class="m3-button m3-button--text m3-state" href="/admin/settings">Back to settings</a></p>
    </section>

  <?php elseif ($done): ?>
    <section class="m3-card">
      <h2 class="m3-card__title">Restored</h2>
      <ul class="restore__list">
        <li><strong><?= (int) $done['updated'] ?></strong> <?= $done['updated'] === 1 ? 'page' : 'pages' ?> put back</li>
        <li><strong><?= (int) $done['created'] ?></strong> <?= $done['created'] === 1 ? 'page' : 'pages' ?> recreated</li>
        <?php if ($done['removed']): ?>
          <li><strong><?= (int) $done['removed'] ?></strong> <?= $done['removed'] === 1 ? 'page' : 'pages' ?> removed</li>
        <?php endif; ?>
        <li><strong><?= (int) $done['settings'] ?></strong> settings written</li>
      </ul>
      <p class="m3-field__support">
        Every page that was replaced has its previous state in its own History, so
        this can be undone one page at a time if the wrong backup went in.
      </p>
      <p>
        <a class="m3-button m3-button--filled m3-state" href="/admin">Back to pages</a>
      </p>
    </section>

  <?php elseif ($plan): ?>
    <section class="m3-card">
      <h2 class="m3-card__title">What this would do</h2>
      <p class="m3-body-medium">
        From a backup taken
        <?php if ($plan['exported_at'] !== ''): ?>
          <strong><?= e(date('j F Y, H:i', strtotime($plan['exported_at']))) ?></strong>.
        <?php else: ?>
          at an unrecorded time.
        <?php endif; ?>
      </p>

      <ul class="restore__list">
        <li><strong><?= count($plan['update']) ?></strong> existing
            <?= count($plan['update']) === 1 ? 'page' : 'pages' ?> would be put back to how they were</li>
        <li><strong><?= count($plan['create']) ?></strong>
            <?= count($plan['create']) === 1 ? 'page' : 'pages' ?> in the backup no longer exist here and would be recreated</li>
        <li><strong><?= count($plan['settings']) ?></strong> settings would change</li>
      </ul>

      <?php $edited = array_filter($plan['update'], fn($p) => !empty($p['edited_since'])); ?>
      <?php if ($edited): ?>
        <p class="imp__note">
          <strong><?= count($edited) ?></strong>
          <?= count($edited) === 1 ? 'page has' : 'pages have' ?>
          been edited since this backup was taken, so restoring replaces that work:
          <?= e(str_trim_to(implode(', ', array_map(fn($p) => $p['title'], $edited)), 300)) ?>.
          Each one keeps its current state in History, so it can be put back.
        </p>
      <?php endif; ?>

      <?php if ($plan['extra']): ?>
        <h3 class="restore__head">On the site, but not in this backup</h3>
        <p class="m3-field__support">
          Almost certainly written after the backup was taken. They are left alone
          unless you say otherwise.
        </p>
        <ul class="restore__list">
          <?php foreach ($plan['extra'] as $page): ?>
            <li><?= e($page['title']) ?> <code><?= e($page['slug']) ?></code></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($plan['assets_missing_row'] || $plan['assets_missing_file']): ?>
        <h3 class="restore__head">Assets</h3>
        <p class="m3-field__support">
          A backup carries the register of uploaded files, not the files. Nothing
          here is written — it is what the backup can tell you that nothing else
          can.
        </p>
        <ul class="restore__list">
          <?php foreach ($plan['assets_missing_row'] as $asset): ?>
            <li><?= e($asset['label']) ?> &mdash; in the backup, not in the asset library now</li>
          <?php endforeach; ?>
          <?php foreach ($plan['assets_missing_file'] as $asset): ?>
            <li><?= e($asset['label']) ?> &mdash; listed, but its file is not on the server</li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($plan['users']): ?>
        <p class="m3-field__support">
          The backup lists <?= (int) $plan['users'] ?>
          <?= $plan['users'] === 1 ? 'person' : 'people' ?>. People are never
          restored: the backup holds no passwords, so restoring them would make
          accounts nobody could sign in to and could remove the only admin.
        </p>
      <?php endif; ?>

      <form method="post" action="/admin/restore/apply">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="payload" value="<?= e($json) ?>">

        <?php if ($plan['extra']): ?>
          <label class="m3-switch">
            <input class="m3-switch__input" type="checkbox" name="remove_extra" value="1" role="switch">
            <span class="m3-switch__track"><span class="m3-switch__handle"></span></span>
            <span class="m3-switch__text">
              <strong>Also delete the <?= count($plan['extra']) ?>
                <?= count($plan['extra']) === 1 ? 'page' : 'pages' ?> not in this backup</strong>
              <span>This takes their version history with them and cannot be undone.
                    Leave it off unless you are certain.</span>
            </span>
          </label>
        <?php endif; ?>

        <div class="imp__actions">
          <button class="m3-button m3-button--filled m3-state" type="submit"
                  data-confirm="Restore from this backup? Every page it replaces keeps its current state in History.">
            Restore
          </button>
          <a class="m3-button m3-button--text m3-state" href="/admin/settings">Cancel</a>
        </div>
      </form>
    </section>

  <?php else: ?>
    <section class="m3-card">
      <h2 class="m3-card__title">Choose a backup</h2>
      <p class="m3-body-medium">
        The JSON file downloaded from Settings. Nothing is changed until you have
        seen what it would do.
      </p>
      <form method="post" action="/admin/restore" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input class="m3-file" type="file" name="backup" accept="application/json,.json" required>
        <div class="imp__actions">
          <button class="m3-button m3-button--filled m3-state" type="submit">See what it would do</button>
        </div>
      </form>
    </section>
  <?php endif; ?>
</div>
