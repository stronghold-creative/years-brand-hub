<?php
$defaults = [];
foreach (array_keys(block_types()) as $type) {
    $defaults[$type] = block_defaults($type);
}
?>
<main class="editor" id="editor"
      data-page-id="<?= (int) $page['id'] ?>"
      data-schema='<?= e(json_encode(block_types(), JSON_UNESCAPED_SLASHES)) ?>'
      data-defaults='<?= e(json_encode($defaults, JSON_UNESCAPED_SLASHES)) ?>'
      data-blocks='<?= e(json_encode($blocks, JSON_UNESCAPED_SLASHES)) ?>'>

  <div class="editor__pane">
    <div class="editor__meta">
      <label class="field field--title">Title
        <input type="text" id="f-title" value="<?= e($page['title']) ?>">
      </label>
      <div class="editor__metarow">
        <label class="field">Section
          <input type="text" id="f-section" value="<?= e($page['section']) ?>">
        </label>
        <label class="field">Status
          <select id="f-status">
            <option value="draft"<?= $page['status'] === 'draft' ? ' selected' : '' ?>>Draft</option>
            <option value="published"<?= $page['status'] === 'published' ? ' selected' : '' ?>>Published</option>
          </select>
        </label>
      </div>
      <label class="field">Summary
        <input type="text" id="f-summary" value="<?= e($page['summary'] ?? '') ?>" placeholder="One line, shown on the index">
      </label>
    </div>

    <div class="blocks" id="blocks"></div>

    <div class="adder">
      <button type="button" class="adder__toggle" id="add-block">Add a block</button>
      <div class="adder__menu" id="add-menu" hidden></div>
    </div>
  </div>

  <aside class="editor__preview">
    <div class="editor__status">
      <span id="save-state">All changes saved</span>
      <a href="/g/<?= e($page['slug']) ?>" target="_blank" rel="noopener">Open page</a>
    </div>
    <iframe id="preview" src="/admin/pages/<?= (int) $page['id'] ?>/preview" title="Preview"></iframe>
  </aside>
</main>
