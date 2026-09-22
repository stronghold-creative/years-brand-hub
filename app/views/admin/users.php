<main class="wrap">
  <div class="wrap__head"><h1>People</h1></div>

  <?php if ($notice): ?><p class="notice"><?= e($notice) ?></p><?php endif; ?>

  <table class="pages">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $user): ?>
      <tr>
        <td><?= e($user['name']) ?><?= (int) $user['id'] === (int) current_user()['id'] ? ' <span class="hint">(you)</span>' : '' ?></td>
        <td><?= e($user['email']) ?></td>
        <td>
          <form method="post" action="/admin/users/<?= (int) $user['id'] ?>/update" class="inline">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <select name="role">
              <?php foreach (['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'] as $value => $label): ?>
                <option value="<?= $value ?>"<?= $user['role'] === $value ? ' selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
            <input type="password" name="password" placeholder="New password, optional" autocomplete="new-password">
            <button type="submit">Save</button>
          </form>
        </td>
        <td>
          <?php if ((int) $user['id'] !== (int) current_user()['id']): ?>
            <form method="post" action="/admin/users/<?= (int) $user['id'] ?>/delete"
                  onsubmit="return confirm('Remove <?= e($user['name']) ?>?')">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <button class="linkbutton danger" type="submit">Remove</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <section class="panel">
    <h2>Add someone</h2>
    <form method="post" action="/admin/users/create" class="grid4">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <label class="field">Name<input type="text" name="name" required></label>
      <label class="field">Email<input type="email" name="email" required></label>
      <label class="field">Password, 12 characters or more<input type="password" name="password" required minlength="12" autocomplete="new-password"></label>
      <label class="field">Role
        <select name="role">
          <option value="viewer">Viewer, can read only</option>
          <option value="editor">Editor, can write pages</option>
          <option value="admin">Admin, can do everything</option>
        </select>
      </label>
      <button type="submit">Add person</button>
    </form>
    <p class="hint">Roles: viewers read the site when it is private, editors write pages, admins also manage people and settings.</p>
  </section>
</main>
