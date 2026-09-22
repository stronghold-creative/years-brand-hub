<main class="wrap">
  <div class="wrap__head">
    <h1>History: <?= e($page['title']) ?></h1>
    <a class="backlink" href="/admin/pages/<?= (int) $page['id'] ?>">Back to the editor</a>
  </div>

  <?php if (count($versions) < 2): ?>
    <p class="empty">Only one version so far. Every save adds one.</p>
  <?php endif; ?>

  <table class="pages">
    <thead><tr><th>Saved</th><th>Title at the time</th><th>By</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($versions as $i => $version): ?>
      <tr>
        <td><?= e(date('j M Y, H:i', strtotime($version['created_at']))) ?></td>
        <td><?= e($version['title']) ?></td>
        <td><?= e($version['author'] ?? 'Unknown') ?></td>
        <td>
          <?php if ($i === 0): ?>
            <span class="pill pill--published">Current</span>
          <?php else: ?>
            <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/restore/<?= (int) $version['id'] ?>">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <button class="linkbutton" type="submit">Restore this</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="hint">Restoring does not lose anything. The current version is saved to history first.</p>
</main>
