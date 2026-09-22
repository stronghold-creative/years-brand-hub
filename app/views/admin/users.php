<main class="m3-page">
  <header class="m3-page__head"><h1>People</h1></header>

  <?php if ($notice): ?><p class="m3-banner"><?= e($notice) ?></p><?php endif; ?>

  <table class="m3-table">
    <thead><tr><th>Name</th><th>Email</th><th>Role and password</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $user): ?>
      <tr>
        <td><?= e($user['name']) ?><?= (int) $user['id'] === (int) current_user()['id'] ? ' <span class="m3-body-small">(you)</span>' : '' ?></td>
        <td><?= e($user['email']) ?></td>
        <td>
          <form method="post" action="/admin/users/<?= (int) $user['id'] ?>/update" class="m3-inline">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <select name="role" aria-label="Role">
              <?php foreach (['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'] as $value => $label): ?>
                <option value="<?= $value ?>"<?= $user['role'] === $value ? ' selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
            <input type="password" name="password" placeholder="New password, optional" autocomplete="new-password" aria-label="New password">
            <button class="m3-button m3-button--text m3-state" type="submit">Save</button>
          </form>
        </td>
        <td>
          <?php if ((int) $user['id'] !== (int) current_user()['id']): ?>
            <form method="post" action="/admin/users/<?= (int) $user['id'] ?>/delete"
                  onsubmit="return confirm('Remove <?= e($user['name']) ?>?')">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <button class="m3-button m3-button--text m3-button--danger m3-state" type="submit">Remove</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <section class="m3-card" style="margin-top:2rem">
    <h2 class="m3-card__title">Add someone</h2>
    <form method="post" action="/admin/users/create">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div class="m3-editor__metarow">
        <div class="m3-field">
          <input class="m3-field__input" type="text" name="name" id="u-name" placeholder=" " required>
          <label class="m3-field__label" for="u-name">Name</label>
        </div>
        <div class="m3-field m3-field--select">
          <select class="m3-field__select" name="role" id="u-role">
            <option value="viewer">Viewer, can read only</option>
            <option value="editor">Editor, can write pages</option>
            <option value="admin">Admin, can do everything</option>
          </select>
          <label class="m3-field__label" for="u-role">Role</label>
        </div>
      </div>
      <div class="m3-field">
        <input class="m3-field__input" type="email" name="email" id="u-email" placeholder=" " required>
        <label class="m3-field__label" for="u-email">Email</label>
      </div>
      <div class="m3-field">
        <input class="m3-field__input" type="password" name="password" id="u-pass" placeholder=" " required minlength="12" autocomplete="new-password">
        <label class="m3-field__label" for="u-pass">Password</label>
        <span class="m3-field__support">At least 12 characters.</span>
      </div>
      <button class="m3-button m3-button--filled m3-state" type="submit">Add person</button>
    </form>
  </section>
</main>
