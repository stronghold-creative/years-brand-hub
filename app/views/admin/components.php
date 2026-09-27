<?php
/**
 * The component list. Grouped, filterable, reorderable — the same shape as the
 * pages list, because it is the same job and a second layout for it would only
 * be something else to keep in step.
 *
 * Phase 1: no editor to link to yet, so a component's name is not a link. The
 * row is otherwise complete.
 */
$byGroup = [];
foreach ($components as $component) {
    $byGroup[$component['group_name']][] = $component;
}
ksort($byGroup);

$drafts = count(array_filter($components, fn($c) => $c['status'] === 'draft'));
$gone   = count(array_filter($components, fn($c) => $c['status'] === 'deprecated'));
?>
<div class="m3-page">
  <div class="m3-page__bar">
    <div class="m3-page__titles">
      <h1>Components</h1>
      <p class="m3-page__meta">
        <?php if (!$installed): ?>
          Not installed yet
        <?php else: ?>
          <?= count($components) ?> <?= count($components) === 1 ? 'component' : 'components' ?><?php
            if ($drafts) { echo ', ' . $drafts . ' in draft'; }
            if ($gone)   { echo ', ' . $gone . ' deprecated'; }
          ?>
        <?php endif; ?>
      </p>
    </div>
    <?php if ($installed): ?>
      <div class="m3-page__action">
        <a class="m3-button m3-button--filled m3-state" href="#newcomponent"><?= ui_icon('add', 'icon') ?> Add component</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$installed): ?>
    <?php // The migration ships with the code but is applied by hand, so this
          // state is real for the minutes between a deploy and Apply updates. ?>
    <div class="m3-empty-state">
      <h2>The component tables are not there yet</h2>
      <p>This area needs a database update. Open <a href="/admin/settings">Settings</a>
         and choose Apply updates, then come back.</p>
    </div>

  <?php else: ?>

    <?php if (count($components) > 8): ?>
      <div class="m3-pagefilter">
        <div class="m3-field m3-field--dense">
          <input class="m3-field__input" type="search" id="page-search" placeholder=" "
                 autocomplete="off" aria-controls="page-sections">
          <label class="m3-field__label" for="page-search">Find a component</label>
        </div>
        <span class="m3-pagefilter__chips">
          <button class="m3-chip m3-chip--filter is-on m3-state" type="button" data-page-state="">All</button>
          <button class="m3-chip m3-chip--filter m3-state" type="button" data-page-state="draft">Drafts</button>
          <button class="m3-chip m3-chip--filter m3-state" type="button" data-page-state="ready">Ready</button>
        </span>
        <span class="m3-pagefilter__count" id="page-count" aria-live="polite"></span>
      </div>
    <?php endif; ?>

    <div id="page-sections">

      <?php if (!$components): ?>
        <div class="m3-empty-state">
          <h2>No components yet</h2>
          <p>Start with the one most often got wrong: the button, or the product card.</p>
        </div>
      <?php endif; ?>

      <?php foreach ($byGroup as $group => $rows): ?>
        <section class="m3-section">
          <div class="m3-section__head">
            <span class="m3-section__name m3-section__name--static"><?= e($group) ?></span>
            <span class="m3-section__count"><?= count($rows) ?> <?= count($rows) === 1 ? 'component' : 'components' ?></span>
          </div>

          <div class="m3-list-head m3-list-head--components">
            <span>Component</span>
            <span>Summary</span>
            <span>Status</span>
            <span>Edited</span>
            <span>Actions</span>
          </div>
          <hr class="m3-divider">
          <ul class="m3-list m3-list--components">
            <?php foreach ($rows as $i => $component): ?>
              <?php // The filter reads these attributes, never the markup. ?>
              <li class="m3-list-item m3-state"
                  data-page-name="<?= e(strtolower($component['name'] . ' ' . $component['group_name'] . ' ' . ($component['summary'] ?? ''))) ?>"
                  data-page-state="<?= e($component['status']) ?>">

                <span class="m3-list-item__headline"><?= e($component['name']) ?></span>

                <span class="m3-list-item__summary"><?= e($component['summary'] ?? '') ?></span>

                <span class="m3-list-item__state">
                  <?php if ($component['status'] === 'ready'): ?>
                    <span class="m3-list-item__date">Ready</span>
                  <?php else: ?>
                    <span class="m3-chip m3-chip--draft"><?= e(component_status_label($component['status'])) ?></span>
                  <?php endif; ?>
                </span>

                <span class="m3-list-item__date">Edited <?= e(date('j M', strtotime($component['updated_at']))) ?></span>

                <span class="m3-list-item__actions">
                  <?php $figma = component_link($component['figma_url'] ?? null); ?>
                  <?php if ($figma !== null): ?>
                    <a class="m3-button m3-button--text m3-state" href="<?= e($figma) ?>"
                       target="_blank" rel="noopener noreferrer">Figma</a>
                  <?php endif; ?>

                  <?php foreach (['up' => '&uarr;', 'down' => '&darr;'] as $dir => $glyph): ?>
                    <?php $off = ($dir === 'up' && $i === 0) || ($dir === 'down' && $i === count($rows) - 1); ?>
                    <form method="post" action="/admin/components/<?= (int) $component['id'] ?>/move/<?= $dir ?>">
                      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                      <button class="m3-icon-button m3-state" type="submit"<?= $off ? ' disabled' : '' ?>
                              aria-label="Move <?= e($component['name']) ?> <?= $dir ?>"><?= $glyph ?></button>
                    </form>
                  <?php endforeach; ?>

                  <?php if ((current_user()['role'] ?? '') === 'admin'): ?>
                    <span class="m3-more">
                      <button class="m3-icon-button m3-state" type="button" data-more
                              aria-label="More actions for <?= e($component['name']) ?>" aria-expanded="false">&#8942;</button>
                      <span class="m3-more__menu" hidden>
                        <form method="post" action="/admin/components/<?= (int) $component['id'] ?>/delete"
                              data-confirm="Delete &quot;<?= e($component['name']) ?>&quot;, its variants and its history? This cannot be undone.">
                          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                          <button class="m3-more__item m3-more__item--danger" type="submit">Delete component</button>
                        </form>
                      </span>
                    </span>
                  <?php endif; ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endforeach; ?>
    </div>

    <p class="m3-empty-state" id="page-none" hidden>No component matches that.</p>

    <section class="m3-card" id="newcomponent">
      <h2 class="m3-card__title">Add a component</h2>
      <form method="post" action="/admin/components/new">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="m3-editor__metarow">
          <div class="m3-field">
            <input class="m3-field__input" type="text" name="name" id="newname" placeholder=" " required
                   maxlength="190">
            <label class="m3-field__label" for="newname">Component name</label>
          </div>
          <div class="m3-field">
            <input class="m3-field__input" type="text" name="group" id="newgroup" list="componentgroups"
                   placeholder=" " maxlength="120"
                   value="<?= e($groups[0] ?? 'Actions') ?>">
            <label class="m3-field__label" for="newgroup">Group</label>
            <datalist id="componentgroups">
              <?php foreach ($groups as $name): ?>
                <option value="<?= e($name) ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>
        </div>
        <p class="m3-field__support">Actions, Forms, Navigation, Commerce — whatever
           grouping the two teams already use when they talk about these.</p>
        <button class="m3-button m3-button--filled m3-state" type="submit">Add component</button>
      </form>
    </section>

  <?php endif; ?>
</div>
