<?php
/**
 * The written guidance on a component, in the same block editor the guidelines
 * use — because it is the same thing: blocks on a row.
 *
 * It is the page editor with three differences, and no second copy of it: the
 * save endpoint comes from `data-save-url`, the canvas points at this
 * component's own preview, and there is no page to configure, so the panel that
 * would hold page settings holds a note instead. `editor.js` needs
 * `#page-settings-template` to exist, and every field it then looks for is
 * optional, so an empty one is enough.
 *
 * Expects: $component, $blocks.
 */
$defaults = [];
$optionSchema = [];
$optionDefaults = [];
foreach (array_keys(block_types()) as $type) {
    $defaults[$type]       = block_defaults($type);
    $optionSchema[$type]   = block_options($type);
    $optionDefaults[$type] = block_options_defaults($type);
}
?>
<div class="ed" id="editor"
     data-page-id="<?= (int) $component['id'] ?>"
     data-save-url="/admin/components/<?= (int) $component['id'] ?>/docs/save"
     data-block-icons='<?= e(json_encode(block_icon_set(), JSON_UNESCAPED_SLASHES)) ?>'
     data-block-groups='<?= e(json_encode(block_groups(), JSON_UNESCAPED_SLASHES)) ?>'
     data-schema='<?= e(json_encode(block_types(), JSON_UNESCAPED_SLASHES)) ?>'
     data-defaults='<?= e(json_encode($defaults, JSON_UNESCAPED_SLASHES)) ?>'
     data-settings='<?= e(json_encode(block_settings_fields(), JSON_UNESCAPED_SLASHES)) ?>'
     data-settings-defaults='<?= e(json_encode(block_settings_defaults(), JSON_UNESCAPED_SLASHES)) ?>'
     data-options='<?= e(json_encode($optionSchema, JSON_UNESCAPED_SLASHES)) ?>'
     data-options-defaults='<?= e(json_encode($optionDefaults, JSON_UNESCAPED_SLASHES)) ?>'
     data-blocks='<?= e(json_encode($blocks, JSON_UNESCAPED_SLASHES)) ?>'>

  <header class="ed__bar">
    <a class="m3-icon-button m3-state" href="/admin/components/<?= (int) $component['id'] ?>"
       aria-label="Back to the component"><?= ui_icon('back') ?></a>
    <span class="ed__name" id="editor-title"><?= e($component['name']) ?> &middot; guidance</span>
    <span class="m3-chip m3-chip--assist" id="save-state">All changes saved</span>

    <span class="ed__tools">
      <button class="m3-button m3-button--text m3-state" type="button" id="outline-toggle"
              aria-pressed="true">Outline</button>
      <span class="ed__widths">
        <button class="m3-chip m3-chip--filter is-on m3-state" type="button" data-preview-width="none">Full</button>
        <button class="m3-chip m3-chip--filter m3-state" type="button" data-preview-width="24rem">Phone</button>
      </span>
      <a class="m3-button m3-button--text m3-state"
         href="/admin/components/<?= (int) $component['id'] ?>/versions">History</a>
      <a class="m3-button m3-button--text m3-state" href="/components/<?= e($component['slug']) ?>"
         target="_blank" rel="noopener">Open</a>
    </span>
  </header>

  <nav class="ed__outline" id="outline" aria-label="Blocks in this guidance">
    <div class="ed__outlinehead">
      <span>Blocks</span>
      <span class="ed__count" id="outline-count"></span>
    </div>
    <ol class="outline" id="outline-list"></ol>
    <button class="outline__add m3-state" type="button" id="outline-add">Add a block</button>
    <div id="outline-paste" hidden></div>
  </nav>

  <div class="ed__canvas">
    <iframe id="preview" src="/admin/components/<?= (int) $component['id'] ?>/docs/preview?edit=1"
            title="Guidance, click a block to edit it"></iframe>
  </div>

  <aside class="ed__inspector" id="inspector" aria-label="Settings"></aside>

  <?php // Nothing to configure here — the component's own fields are on its
        // screen — but the inspector needs something to show when no block is
        // selected, and a blank panel reads as broken. ?>
  <template id="page-settings-template">
    <div class="insp__section">
      <h2 class="insp__title">Guidance</h2>
      <p class="insp__note">
        The written part of this component: what it is for, when not to use it,
        anything a spec table cannot say. It appears under the code on
        <a href="/components/<?= e($component['slug']) ?>" target="_blank" rel="noopener">the component's page</a>.
      </p>
      <p class="insp__note">
        Select a block to edit it, or add one from the outline. The component's
        own name, status and variants are on
        <a href="/admin/components/<?= (int) $component['id'] ?>">its screen</a>.
      </p>
    </div>
  </template>
</div>
