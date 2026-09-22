<main class="wrap">
  <div class="wrap__head">
    <h1>Pages</h1>
    <form method="post" action="/admin/pages/new" class="newpage">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="text" name="title" placeholder="Page title" required>
      <input type="text" name="section" placeholder="Section" value="General">
      <button type="submit">Add page</button>
    </form>
  </div>

  <?php if (!$pages): ?>
    <p class="empty">No pages yet. Add your first one above.</p>
  <?php else: ?>
    <table class="pages">
      <thead><tr><th>Title</th><th>Section</th><th>Status</th><th>Updated</th></tr></thead>
      <tbody>
      <?php foreach ($pages as $page): ?>
        <tr>
          <td><a href="/admin/pages/<?= (int) $page['id'] ?>"><?= e($page['title']) ?></a></td>
          <td><?= e($page['section']) ?></td>
          <td><span class="pill pill--<?= e($page['status']) ?>"><?= $page['status'] === 'published' ? 'Published' : 'Draft' ?></span></td>
          <td><?= e(date('j M, H:i', strtotime($page['updated_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>
