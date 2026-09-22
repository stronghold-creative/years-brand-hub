<?php
$bySection = [];
foreach ($pages as $page) {
    $bySection[$page['section']][] = $page;
}
$drafts = count(array_filter($pages, fn($p) => $p['status'] !== 'published'));
$stale  = count(array_filter($pages, fn($p) => strtotime($p['updated_at']) < strtotime('-6 months')));
?>
<main class="wrap wrap--narrow">
  <header class="pagehead">
    <h1>Pages</h1>
    <p class="pagehead__meta">
      <?= count($pages) ?> <?= count($pages) === 1 ? 'page' : 'pages' ?><?php
        if ($drafts) { echo ', ' . $drafts . ' in draft'; }
        if ($stale)  { echo ', ' . $stale . ' not updated in six months'; }
      ?>
    </p>
  </header>

  <?php if (!$pages): ?>
    <p class="empty">Nothing here yet. Add your first page below.</p>
  <?php endif; ?>

  <?php foreach ($bySection as $section => $rows): ?>
    <section class="sectionblock">
      <form method="post" action="/admin/sections/rename" class="sectionblock__head">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="from" value="<?= e($section) ?>">
        <input class="sectionblock__name" type="text" name="to" value="<?= e($section) ?>"
               aria-label="Section name, edit to rename">
        <button class="linkbutton" type="submit">Save name</button>
        <span class="sectionblock__count"><?= count($rows) ?></span>
      </form>

      <ul class="pagelist">
        <?php foreach ($rows as $i => $page): ?>
          <li class="pagerow">
            <a class="pagerow__title" href="/admin/pages/<?= (int) $page['id'] ?>"><?= e($page['title']) ?></a>

            <span class="pagerow__state">
              <?php if ($page['status'] !== 'published'): ?>
                <span class="state state--draft">Draft</span>
              <?php endif; ?>
              <span class="pagerow__date">Edited <?= e(date('j M', strtotime($page['updated_at']))) ?></span>
            </span>

            <span class="pagerow__actions">
              <a class="quiet" href="/admin/pages/<?= (int) $page['id'] ?>/versions">History</a>
              <a class="quiet" href="/g/<?= e($page['slug']) ?>" target="_blank" rel="noopener">View</a>
              <span class="movers">
                <?php foreach (['up' => '&uarr;', 'down' => '&darr;'] as $dir => $glyph): ?>
                  <?php $off = ($dir === 'up' && $i === 0) || ($dir === 'down' && $i === count($rows) - 1); ?>
                  <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/move/<?= $dir ?>">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <button class="mover" type="submit"<?= $off ? ' disabled' : '' ?>
                            aria-label="Move <?= $dir ?>"><?= $glyph ?></button>
                  </form>
                <?php endforeach; ?>
              </span>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>

  <form method="post" action="/admin/pages/new" class="newpage">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="text" name="title" placeholder="New page title" required>
    <input type="text" name="section" list="sections" placeholder="Section" value="<?= e(array_key_first($bySection) ?? 'Foundations') ?>">
    <datalist id="sections">
      <?php foreach (array_keys($bySection) as $name): ?>
        <option value="<?= e($name) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <button type="submit">Add page</button>
  </form>
</main>
