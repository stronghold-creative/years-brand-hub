<?php
$defaults = [];
foreach (array_keys(block_types()) as $type) {
    $defaults[$type] = block_defaults($type);
}
$isAdmin = (current_user()['role'] ?? '') === 'admin';

$optionSchema = [];
$optionDefaults = [];
foreach (array_keys(block_types()) as $type) {
    $optionSchema[$type]   = block_options($type);
    $optionDefaults[$type] = block_options_defaults($type);
}
?>
<div class="m3-editor" id="editor"
     data-page-id="<?= (int) $page['id'] ?>"
     data-schema='<?= e(json_encode(block_types(), JSON_UNESCAPED_SLASHES)) ?>'
     data-defaults='<?= e(json_encode($defaults, JSON_UNESCAPED_SLASHES)) ?>'
     data-settings='<?= e(json_encode(block_settings_fields(), JSON_UNESCAPED_SLASHES)) ?>'
     data-settings-defaults='<?= e(json_encode(block_settings_defaults(), JSON_UNESCAPED_SLASHES)) ?>'
     data-options='<?= e(json_encode($optionSchema, JSON_UNESCAPED_SLASHES)) ?>'
     data-options-defaults='<?= e(json_encode($optionDefaults, JSON_UNESCAPED_SLASHES)) ?>'
     data-blocks='<?= e(json_encode($blocks, JSON_UNESCAPED_SLASHES)) ?>'>

  <header class="m3-editor__bar">
    <a class="m3-icon-button m3-state" href="/admin" aria-label="Back to pages"><?= ui_icon('back') ?></a>
    <span class="m3-editor__name" id="editor-title"><?= e($page['title']) ?></span>
    <span class="m3-chip m3-chip--assist" id="save-state">All changes saved</span>

    <span class="m3-editor__tools">
      <button class="m3-button m3-button--text m3-state" type="button" id="page-meta-toggle" aria-expanded="false">
        Page settings
      </button>
      <button class="m3-button m3-button--text m3-state" type="button" id="preview-toggle" aria-pressed="true">
        Hide preview
      </button>
      <a class="m3-button m3-button--text m3-state" href="/admin/pages/<?= (int) $page['id'] ?>/versions">History</a>
      <a class="m3-button m3-button--text m3-state" href="/s/<?= e(slugify($page['section'])) ?>#<?= e($page['slug']) ?>" target="_blank" rel="noopener">Open</a>
    </span>
  </header>

  <div class="m3-editor__pane">
    <!-- Everything about the page rather than its content, collapsed by default. -->
    <section class="m3-editor__meta" id="page-meta" hidden>
      <div class="m3-field">
        <input class="m3-field__input" type="text" id="f-title" placeholder=" " value="<?= e($page['title']) ?>">
        <label class="m3-field__label" for="f-title">Title</label>
      </div>

      <div class="m3-editor__metarow">
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
      </div>

      <div class="m3-field">
        <input class="m3-field__input" type="text" id="f-summary" placeholder=" " value="<?= e($page['summary'] ?? '') ?>">
        <label class="m3-field__label" for="f-summary">Summary</label>
        <span class="m3-field__support">One line, shown on the index card and in the pages list.</span>
      </div>

      <div id="f-cover" data-cover="<?= (int) ($page['cover_asset_id'] ?? 0) ?>"></div>

      <label class="m3-switch">
        <input class="m3-switch__input" type="checkbox" id="f-home" role="switch"
               <?= !isset($page['show_on_home']) || $page['show_on_home'] ? ' checked' : '' ?>>
        <span class="m3-switch__track"><span class="m3-switch__handle"></span></span>
        <span class="m3-switch__text">
          <strong>Show on the front grid</strong>
          <span>Off keeps the page in its section but hides its card on the home page.</span>
        </span>
      </label>

      <?php if ($isAdmin): ?>
        <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/delete" class="m3-editor__danger"
              data-confirm="Delete &quot;<?= e($page['title']) ?>&quot; and everything on it? This cannot be undone.">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <button class="m3-button m3-button--text m3-button--danger m3-state" type="submit">Delete this page</button>
        </form>
      <?php endif; ?>
    </section>

    <div class="blocks" id="blocks"></div>
  </div>

  <aside class="m3-editor__preview">
    <div class="m3-editor__previewbar">
      <span class="m3-editor__previewlabel">Preview</span>
      <span class="m3-editor__widths">
        <button class="m3-chip m3-chip--filter is-on m3-state" type="button" data-preview-width="none">Full</button>
        <button class="m3-chip m3-chip--filter m3-state" type="button" data-preview-width="24rem">Phone</button>
      </span>
    </div>
    <iframe id="preview" src="/admin/pages/<?= (int) $page['id'] ?>/preview" title="Preview of this page"></iframe>
  </aside>
</div>
