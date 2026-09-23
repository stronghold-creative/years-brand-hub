<?php
$defaults = [];
$optionSchema = [];
$optionDefaults = [];
foreach (array_keys(block_types()) as $type) {
    $defaults[$type]       = block_defaults($type);
    $optionSchema[$type]   = block_options($type);
    $optionDefaults[$type] = block_options_defaults($type);
}
$isAdmin = (current_user()['role'] ?? '') === 'admin';
?>
<div class="ed" id="editor"
     data-page-id="<?= (int) $page['id'] ?>"
     data-schema='<?= e(json_encode(block_types(), JSON_UNESCAPED_SLASHES)) ?>'
     data-defaults='<?= e(json_encode($defaults, JSON_UNESCAPED_SLASHES)) ?>'
     data-settings='<?= e(json_encode(block_settings_fields(), JSON_UNESCAPED_SLASHES)) ?>'
     data-settings-defaults='<?= e(json_encode(block_settings_defaults(), JSON_UNESCAPED_SLASHES)) ?>'
     data-options='<?= e(json_encode($optionSchema, JSON_UNESCAPED_SLASHES)) ?>'
     data-options-defaults='<?= e(json_encode($optionDefaults, JSON_UNESCAPED_SLASHES)) ?>'
     data-blocks='<?= e(json_encode($blocks, JSON_UNESCAPED_SLASHES)) ?>'
     data-page-title="<?= e($page['title']) ?>"
     data-page-section="<?= e($page['section']) ?>"
     data-page-summary="<?= e($page['summary'] ?? '') ?>"
     data-page-status="<?= e($page['status']) ?>"
     data-page-home="<?= !isset($page['show_on_home']) || $page['show_on_home'] ? '1' : '0' ?>">

  <header class="ed__bar">
    <a class="m3-icon-button m3-state" href="/admin" aria-label="Back to pages"><?= ui_icon('back') ?></a>
    <span class="ed__name" id="editor-title"><?= e($page['title']) ?></span>
    <span class="m3-chip m3-chip--assist" id="save-state">All changes saved</span>

    <span class="ed__tools">
      <button class="m3-button m3-button--text m3-state" type="button" id="outline-toggle"
              aria-pressed="true">Outline</button>
      <button class="m3-button m3-button--text m3-state" type="button" id="page-settings-toggle">Page settings</button>
      <span class="ed__widths">
        <button class="m3-chip m3-chip--filter is-on m3-state" type="button" data-preview-width="none">Full</button>
        <button class="m3-chip m3-chip--filter m3-state" type="button" data-preview-width="24rem">Phone</button>
      </span>
      <a class="m3-button m3-button--text m3-state" href="/admin/pages/<?= (int) $page['id'] ?>/versions">History</a>
      <a class="m3-button m3-button--text m3-state" href="/s/<?= e(slugify($page['section'])) ?>#<?= e($page['slug']) ?>"
         target="_blank" rel="noopener">Open</a>
    </span>
  </header>

  <!-- Outline: navigation, not the editing surface. -->
  <nav class="ed__outline" id="outline" aria-label="Blocks on this page">
    <div class="ed__outlinehead">
      <span>Blocks</span>
      <span class="ed__count" id="outline-count"></span>
    </div>
    <ol class="outline" id="outline-list"></ol>
    <button class="outline__add m3-state" type="button" id="outline-add">Add a block</button>
  </nav>

  <!-- Canvas: the real page, clickable. -->
  <div class="ed__canvas">
    <iframe id="preview" src="/admin/pages/<?= (int) $page['id'] ?>/preview?edit=1"
            title="Page, click a block to edit it"></iframe>
  </div>

  <!-- Inspector: whatever is selected, or the page itself. -->
  <aside class="ed__inspector" id="inspector" aria-label="Settings"></aside>

  <!-- Page settings, held here so the inspector can show them when nothing is selected. -->
  <template id="page-settings-template">
    <div class="insp__section">
      <h2 class="insp__title">Page</h2>
      <div class="m3-field">
        <input class="m3-field__input" type="text" id="f-title" placeholder=" " value="<?= e($page['title']) ?>">
        <label class="m3-field__label" for="f-title">Title</label>
      </div>
      <div class="m3-field">
        <input class="m3-field__input" type="text" id="f-section" placeholder=" " value="<?= e($page['section']) ?>">
        <label class="m3-field__label" for="f-section">Section</label>
      </div>
      <div class="m3-field m3-field--select">
        <select class="m3-field__select" id="f-status">
          <option value="draft"<?= $page['status'] === 'draft' ? ' selected' : '' ?>>Draft</option>
          <option value="published"<?= $page['status'] === 'published' ? ' selected' : '' ?>>Published</option>
        </select>
        <label class="m3-field__label" for="f-status">Status</label>
      </div>
      <div class="m3-field">
        <input class="m3-field__input" type="text" id="f-summary" placeholder=" " value="<?= e($page['summary'] ?? '') ?>">
        <label class="m3-field__label" for="f-summary">Summary</label>
      </div>
      <div id="f-cover" data-cover="<?= (int) ($page['cover_asset_id'] ?? 0) ?>"></div>
      <label class="m3-switch">
        <input class="m3-switch__input" type="checkbox" id="f-home" role="switch"
               <?= !isset($page['show_on_home']) || $page['show_on_home'] ? ' checked' : '' ?>>
        <span class="m3-switch__track"><span class="m3-switch__handle"></span></span>
        <span class="m3-switch__text">
          <strong>Show on the front grid</strong>
          <span>Off keeps the page in its section but hides its card.</span>
        </span>
      </label>
      <?php if ($isAdmin): ?>
        <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/delete" class="insp__danger"
              data-confirm="Delete &quot;<?= e($page['title']) ?>&quot; and everything on it? This cannot be undone.">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <button class="m3-button m3-button--text m3-button--danger m3-state" type="submit">Delete this page</button>
        </form>
      <?php endif; ?>
    </div>
  </template>
</div>
