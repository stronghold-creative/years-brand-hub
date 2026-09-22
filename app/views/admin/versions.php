<main class="m3-page">
  <header class="m3-page__head">
    <h1>History</h1>
    <p class="m3-page__meta"><?= e($page['title']) ?> &middot;
      <a href="/admin/pages/<?= (int) $page['id'] ?>">Back to the editor</a></p>
  </header>

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
</main>
