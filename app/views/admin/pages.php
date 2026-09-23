<?php
$bySection = [];
foreach ($pages as $page) {
    $bySection[$page['section']][] = $page;
}
$drafts = count(array_filter($pages, fn($p) => $p['status'] !== 'published'));
$stale  = count(array_filter($pages, fn($p) => strtotime($p['updated_at']) < strtotime('-6 months')));
?>
<div class="m3-page">
  <div class="m3-page__bar">
    <div class="m3-page__titles">
      <h1>Pages</h1>
      <p class="m3-page__meta">
        <?= count($pages) ?> <?= count($pages) === 1 ? 'page' : 'pages' ?><?php
          if ($drafts) { echo ', ' . $drafts . ' in draft'; }
          if ($stale)  { echo ', ' . $stale . ' not updated in six months'; }
        ?>
      </p>
    </div>
    <div class="m3-page__action">
      <a class="m3-button m3-button--filled m3-state" href="#newpage"><?= ui_icon('add', 'icon') ?> Add page</a>
    </div>
  </div>

  <?php if (!$pages): ?>
    <div class="m3-empty-state">
      <h2>No pages yet</h2>
      <p>Start with one of the foundations: logo, colour or typography.</p>
    </div>
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
        <button class="m3-button m3-button--text m3-state m3-section__save" type="submit">Save section</button>
      </form>

      <div class="m3-list-head">
        <span>Page</span>
        <span>Summary</span>
        <span>Status</span>
        <span>Edited</span>
        <span>Actions</span>
      </div>
      <hr class="m3-divider">
      <ul class="m3-list">
        <?php foreach ($rows as $i => $page): ?>
          <li class="m3-list-item m3-state">
            <a class="m3-list-item__headline" href="/admin/pages/<?= (int) $page['id'] ?>"><?= e($page['title']) ?></a>

            <span class="m3-list-item__summary"><?= e($page['summary'] ?? '') ?></span>

            <span class="m3-list-item__state">
              <?php if ($page['status'] !== 'published'): ?>
                <span class="m3-chip m3-chip--draft">Draft</span>
              <?php else: ?>
                <span class="m3-list-item__date">Published</span>
              <?php endif; ?>
            </span>

            <span class="m3-list-item__date">Edited <?= e(date('j M', strtotime($page['updated_at']))) ?></span>

            <span class="m3-list-item__actions">
              <a class="m3-button m3-button--text m3-state" href="/admin/pages/<?= (int) $page['id'] ?>/versions">History</a>
              <a class="m3-button m3-button--text m3-state" href="/g/<?= e($page['slug']) ?>" target="_blank" rel="noopener">View</a>
              <?php if ((current_user()['role'] ?? '') === 'admin'): ?>
                <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/delete"
                      data-confirm="Delete &quot;<?= e($page['title']) ?>&quot; and everything on it? This cannot be undone.">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <button class="m3-button m3-button--text m3-button--danger m3-state" type="submit">Delete</button>
                </form>
              <?php endif; ?>
              <?php foreach (['up' => '&uarr;', 'down' => '&darr;'] as $dir => $glyph): ?>
                <?php $off = ($dir === 'up' && $i === 0) || ($dir === 'down' && $i === count($rows) - 1); ?>
                <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/move/<?= $dir ?>">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <button class="m3-icon-button m3-state" type="submit"<?= $off ? ' disabled' : '' ?>
                          aria-label="Move <?= e($page['title']) ?> <?= $dir ?>"><?= $glyph ?></button>
                </form>
              <?php endforeach; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>

  <section class="m3-card" id="newpage">
    <h2 class="m3-card__title">Add a page</h2>
    <form method="post" action="/admin/pages/new">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div class="m3-editor__metarow">
        <div class="m3-field">
          <input class="m3-field__input" type="text" name="title" id="newtitle" placeholder=" " required>
          <label class="m3-field__label" for="newtitle">Page title</label>
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
      </div>
      <button class="m3-button m3-button--filled m3-state" type="submit">Add page</button>
    </form>
  </section>
</div>
