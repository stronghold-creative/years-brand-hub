<?php
$bySection = [];
foreach ($pages as $page) {
    $bySection[$page['section']][] = $page;
}
?>
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
  <?php endif; ?>

  <?php foreach ($bySection as $section => $rows): ?>
    <section class="sectionblock">
      <form method="post" action="/admin/sections/rename" class="sectionblock__head">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="from" value="<?= e($section) ?>">
        <input class="sectionblock__name" type="text" name="to" value="<?= e($section) ?>"
               aria-label="Section name, edit to rename">
        <button class="linkbutton" type="submit">Save name</button>
      </form>

      <table class="pages">
        <thead><tr><th>Title</th><th>Status</th><th>Updated</th><th>Order</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $i => $page): ?>
          <tr>
            <td><a href="/admin/pages/<?= (int) $page['id'] ?>"><?= e($page['title']) ?></a></td>
            <td><span class="pill pill--<?= e($page['status']) ?>"><?= $page['status'] === 'published' ? 'Published' : 'Draft' ?></span></td>
            <td><?= e(date('j M, H:i', strtotime($page['updated_at']))) ?></td>
            <td class="order">
              <?php foreach (['up' => 'Move up', 'down' => 'Move down'] as $dir => $label): ?>
                <?php $disabled = ($dir === 'up' && $i === 0) || ($dir === 'down' && $i === count($rows) - 1); ?>
                <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/move/<?= $dir ?>" class="inline">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <button class="linkbutton" type="submit"<?= $disabled ? ' disabled' : '' ?> aria-label="<?= $label ?>"><?= $dir === 'up' ? '&uarr;' : '&darr;' ?></button>
                </form>
              <?php endforeach; ?>
            </td>
            <td><a class="hint" href="/admin/pages/<?= (int) $page['id'] ?>/versions">History</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endforeach; ?>
</main>
