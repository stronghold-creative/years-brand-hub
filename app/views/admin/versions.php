<div class="m3-page">
  <div class="m3-page__bar">
    <div class="m3-page__titles">
      <a class="m3-back" href="/admin/pages/<?= (int) $page['id'] ?>"><?= ui_icon('back', 'icon') ?> Back to the editor</a>
      <h1>History</h1>
      <p class="m3-page__meta"><?= e($page['title']) ?>, last <?= count($versions) ?> saves</p>
    </div>
  </div>

  <?php if (count($versions) < 2): ?>
    <p class="m3-empty">Only one version so far. Every save adds one.</p>
  <?php endif; ?>

  <table class="m3-table">
    <thead><tr><th>Saved</th><th>Title at the time</th><th>By</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($versions as $i => $version): ?>
      <tr>
        <td><?= e(date('j M Y, H:i', strtotime($version['created_at']))) ?></td>
        <td><?= e($version['title']) ?></td>
        <td><?= e($version['author'] ?? 'Unknown') ?></td>
        <td>
          <?php if ($i === 0): ?>
            <span class="m3-chip m3-chip--assist">Current</span>
          <?php else: ?>
            <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/restore/<?= (int) $version['id'] ?>">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <button class="m3-button m3-button--text m3-state" type="submit">Restore this</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="m3-empty">Restoring does not lose anything. The current version is saved to history first.</p>
</div>
