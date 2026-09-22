<?php
$bySection = [];
foreach ($pages as $page) {
    $bySection[$page['section']][] = $page;
}
$drafts = count(array_filter($pages, fn($p) => $p['status'] !== 'published'));
$stale  = count(array_filter($pages, fn($p) => strtotime($p['updated_at']) < strtotime('-6 months')));
?>
<main class="m3-page m3-page--narrow">
  <header class="m3-page__head">
    <h1>Pages</h1>
    <p class="m3-page__meta">
      <?= count($pages) ?> <?= count($pages) === 1 ? 'page' : 'pages' ?><?php
        if ($drafts) { echo ', ' . $drafts . ' in draft'; }
        if ($stale)  { echo ', ' . $stale . ' not updated in six months'; }
      ?>
    </p>
  </header>

  <?php if (!$pages): ?>
    <p class="m3-empty">Nothing here yet. Add your first page below.</p>
  <?php endif; ?>

  <?php foreach ($bySection as $section => $rows): ?>
    <section class="m3-section">
      <form method="post" action="/admin/sections/rename" class="m3-section__head">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="from" value="<?= e($section) ?>">
        <span class="m3-section__icon"><?= icon(section_icon($section), 'icon') ?></span>
        <input class="m3-section__name" type="text" name="to" value="<?= e($section) ?>"
               aria-label="Section name, edit to rename">
        <select class="m3-section__icons" name="icon" aria-label="Section icon">
          <?php foreach (icon_set() as $key => $meta): ?>
            <option value="<?= e($key) ?>"<?= section_icon($section) === $key ? ' selected' : '' ?>><?= e($meta['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="m3-button m3-button--text m3-state m3-section__save" type="submit">Save</button>
      </form>

      <hr class="m3-divider">
      <ul class="m3-list">
        <?php foreach ($rows as $i => $page): ?>
          <li class="m3-list-item m3-state">
            <a class="m3-list-item__headline" href="/admin/pages/<?= (int) $page['id'] ?>"><?= e($page['title']) ?></a>

            <span class="m3-list-item__support">
              <?php if ($page['status'] !== 'published'): ?>
                <span class="m3-chip m3-chip--draft">Draft</span>
              <?php endif; ?>
              Edited <?= e(date('j M', strtotime($page['updated_at']))) ?>
            </span>

            <span class="m3-list-item__actions">
              <a class="m3-button m3-button--text m3-state" href="/admin/pages/<?= (int) $page['id'] ?>/versions">History</a>
              <a class="m3-button m3-button--text m3-state" href="/g/<?= e($page['slug']) ?>" target="_blank" rel="noopener">View</a>
              <?php foreach (['up' => '&uarr;', 'down' => '&darr;'] as $dir => $glyph): ?>
                <?php $off = ($dir === 'up' && $i === 0) || ($dir === 'down' && $i === count($rows) - 1); ?>
                <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/move/<?= $dir ?>">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <button class="m3-icon-button m3-state" type="submit"<?= $off ? ' disabled' : '' ?>
                          aria-label="Move <?= $dir ?>"><?= $glyph ?></button>
                </form>
              <?php endforeach; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>

  <form method="post" action="/admin/pages/new" class="m3-addrow">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="m3-field">
      <input class="m3-field__input" type="text" name="title" id="newtitle" placeholder=" " required>
      <label class="m3-field__label" for="newtitle">New page title</label>
    </div>
    <div class="m3-field">
      <input class="m3-field__input" type="text" name="section" id="newsection" list="sections" placeholder=" "
             value="<?= e(array_key_first($bySection) ?? 'Foundations') ?>">
      <label class="m3-field__label" for="newsection">Section</label>
      <datalist id="sections">
        <?php foreach (array_keys($bySection) as $name): ?>
          <option value="<?= e($name) ?>"></option>
        <?php endforeach; ?>
      </datalist>
    </div>
    <button class="m3-button m3-button--filled m3-state" type="submit">Add page</button>
  </form>
</main>
