<?php
$defaults = [];
foreach (array_keys(block_types()) as $type) {
    $defaults[$type] = block_defaults($type);
}
?>
<main class="m3-editor" id="editor"
      data-page-id="<?= (int) $page['id'] ?>"
      data-schema='<?= e(json_encode(block_types(), JSON_UNESCAPED_SLASHES)) ?>'
      data-defaults='<?= e(json_encode($defaults, JSON_UNESCAPED_SLASHES)) ?>'
      data-blocks='<?= e(json_encode($blocks, JSON_UNESCAPED_SLASHES)) ?>'>

  <div class="m3-editor__pane">
    <div class="m3-editor__meta">
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
        <span class="m3-field__support">One line, shown on the index.</span>
      </div>
    </div>

    <div class="blocks" id="blocks"></div>

    <div class="adder">
      <button type="button" class="adder__toggle m3-state" id="add-block">Add a block</button>
      <div class="adder__menu" id="add-menu" hidden></div>
    </div>
  </div>

  <aside class="m3-editor__preview">
    <div class="m3-editor__status">
      <span id="save-state">All changes saved</span>
      <a href="/admin/pages/<?= (int) $page['id'] ?>/versions">History</a>
      <a href="/g/<?= e($page['slug']) ?>" target="_blank" rel="noopener">Open page</a>
    </div>
    <iframe id="preview" src="/admin/pages/<?= (int) $page['id'] ?>/preview" title="Preview"></iframe>
  </aside>
</main>
