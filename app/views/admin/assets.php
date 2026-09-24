<?php
$totalBytes = array_sum(array_map(fn($a) => (int) $a['bytes'], $assets));
?>
<div class="m3-page">
  <div class="m3-page__bar">
    <div class="m3-page__titles">
      <h1>Assets</h1>
      <p class="m3-page__meta">
        <?= count($assets) ?> <?= count($assets) === 1 ? 'file' : 'files' ?><?php
          if ($assets) { echo ', ' . asset_human_size($totalBytes); } ?>
      </p>
    </div>
    <div class="m3-page__action">
      <button class="m3-button m3-button--filled m3-state" type="button" data-open-upload>
        <?= ui_icon('add', 'icon') ?> Upload
      </button>
    </div>
  </div>

  <?php if ($notice): ?><p class="m3-banner"><?= e($notice) ?></p><?php endif; ?>

  <?php if (!$assets): ?>
    <div class="m3-empty-state">
      <h2>No assets yet</h2>
      <p>Upload the logo files first. They are what people come here for.</p>
      <button class="m3-button m3-button--filled m3-state" type="button" data-open-upload>Upload a file</button>
    </div>
  <?php else: ?>

    <!-- Toolbar: search and tag filters, both client side. -->
    <div class="m3-toolbar">
      <div class="m3-search">
        <?= ui_icon('assets', 'icon') ?>
        <input type="search" id="asset-search" placeholder="Search by name, label or tag" aria-label="Search assets">
      </div>
      <?php if ($tags): ?>
        <div class="m3-tagfilter" role="group" aria-label="Filter by tag">
          <button class="m3-chip m3-chip--filter is-on m3-state" type="button" data-tag="">All</button>
          <?php foreach ($tags as $tag => $count): ?>
            <button class="m3-chip m3-chip--filter m3-state" type="button" data-tag="<?= e($tag) ?>">
              <?= e($tag) ?> <span class="m3-chip__count"><?= (int) $count ?></span>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <ul class="m3-assets" id="asset-grid">
      <?php foreach ($assets as $asset): ?>
        <?php
          $ext = strtoupper(asset_extension($asset['original_name']));
          $isImage = asset_is_inline_image($asset);
        ?>
        <li class="m3-asset"
            data-name="<?= e(strtolower($asset['original_name'] . ' ' . $asset['label'] . ' ' . (string) $asset['tags'])) ?>"
            data-tags="<?= e((string) $asset['tags']) ?>">
          <button class="m3-asset__button m3-state" type="button"
                  data-asset='<?= e(json_encode([
                      'id'        => (int) $asset['id'],
                      'label'     => $asset['label'],
                      'file'      => $asset['original_name'],
                      'tags'      => (string) $asset['tags'],
                      'version'   => $asset['version'],
                      'size'      => asset_human_size((int) $asset['bytes']),
                      'uploaded'  => date('j M Y', strtotime($asset['uploaded_at'])),
                      'downloads' => (int) $asset['downloads'],
                      'usage'     => array_map(fn($p) => $p['title'], asset_usage((int) $asset['id'])),
                      'preview'   => $isImage ? asset_image_url($asset) : null,
                      'ext'       => $ext,
                  ], JSON_UNESCAPED_SLASHES)) ?>'>
            <span class="m3-asset__thumb<?= $isImage ? '' : ' m3-asset__thumb--file' ?>">
              <?php if ($isImage): ?>
                <img src="<?= e(asset_image_url($asset)) ?>" alt="" loading="lazy" decoding="async">
              <?php else: ?>
                <span class="m3-asset__ext"><?= e($ext) ?></span>
              <?php endif; ?>
            </span>
            <span class="m3-asset__name"><?= e($asset['label'] ?: $asset['original_name']) ?></span>
            <span class="m3-asset__meta">
              <?= e($ext) ?> &middot; <?= e(asset_human_size((int) $asset['bytes'])) ?> &middot; v<?= e($asset['version']) ?>
            </span>
            <?php if (!empty($asset['tags'])): ?>
              <span class="m3-asset__tags"><?= e((string) $asset['tags']) ?></span>
            <?php endif; ?>
          </button>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="m3-empty" id="asset-none" hidden>Nothing matches that.</p>
  <?php endif; ?>
</div>

<!-- Upload, in a dialog so it is not permanently taking up the page. -->
<div class="m3-scrim" id="upload-dialog" hidden>
  <div class="m3-dialog" role="dialog" aria-modal="true" aria-labelledby="upload-title">
    <div class="m3-dialog__head">
      <h2 id="upload-title" class="m3-title-large">Upload a file</h2>
      <button class="m3-icon-button m3-state" type="button" data-close-dialog aria-label="Close"><?= ui_icon('close') ?></button>
    </div>
    <form method="post" action="/admin/assets/upload" enctype="multipart/form-data">
      <div class="m3-dialog__body">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label class="m3-drop">
          <input type="file" name="file" required>
          <span class="m3-drop__text">Choose a file</span>
          <span class="m3-drop__hint">SVG, PNG, JPG, WEBP, GIF, PDF, EPS, AI, ZIP, fonts, Office files, MP4. Up to 50 MB.</span>
        </label>
        <div class="m3-field">
          <input class="m3-field__input" type="text" name="label" id="up-label" placeholder=" ">
          <label class="m3-field__label" for="up-label">Label</label>
          <span class="m3-field__support">What people will see. Defaults to the file name.</span>
        </div>
        <div class="m3-editor__metarow">
          <div class="m3-field">
            <input class="m3-field__input" type="text" name="tags" id="up-tags" placeholder=" ">
            <label class="m3-field__label" for="up-tags">Tags</label>
            <span class="m3-field__support">Comma separated, for example: logo, print</span>
          </div>
          <div class="m3-field">
            <input class="m3-field__input" type="text" name="version" id="up-version" placeholder=" " value="1.0">
            <label class="m3-field__label" for="up-version">Version</label>
          </div>
        </div>
      </div>
      <div class="m3-dialog__foot">
        <button class="m3-button m3-button--text m3-state" type="button" data-close-dialog>Cancel</button>
        <button class="m3-button m3-button--filled m3-state" type="submit">Upload</button>
      </div>
    </form>
  </div>
</div>

<!-- Details, filled in by admin.js from the tile that was clicked. -->
<div class="m3-scrim" id="asset-dialog" hidden>
  <div class="m3-dialog" role="dialog" aria-modal="true" aria-labelledby="asset-title">
    <div class="m3-dialog__head">
      <h2 id="asset-title" class="m3-title-large">File</h2>
      <button class="m3-icon-button m3-state" type="button" data-close-dialog aria-label="Close"><?= ui_icon('close') ?></button>
    </div>
    <div class="m3-dialog__body">
      <div class="m3-detail">
        <div class="m3-detail__preview" id="asset-preview"></div>
        <dl class="m3-detail__facts" id="asset-facts"></dl>
          <p class="m3-usage" id="asset-usage"></p>
      </div>

      <form method="post" id="asset-save" class="m3-detail__form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="m3-field">
          <input class="m3-field__input" type="text" name="label" id="d-label" placeholder=" ">
          <label class="m3-field__label" for="d-label">Label</label>
        </div>
        <div class="m3-editor__metarow">
          <div class="m3-field">
            <input class="m3-field__input" type="text" name="tags" id="d-tags" placeholder=" ">
            <label class="m3-field__label" for="d-tags">Tags</label>
          </div>
          <div class="m3-field">
            <input class="m3-field__input" type="text" name="version" id="d-version" placeholder=" ">
            <label class="m3-field__label" for="d-version">Version</label>
          </div>
        </div>
        <button class="m3-button m3-button--tonal m3-state" type="submit">Save changes</button>
      </form>

      <form method="post" id="asset-replace" enctype="multipart/form-data" class="m3-detail__form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label class="m3-drop m3-drop--small">
          <input type="file" name="file" required>
          <span class="m3-drop__text">Replace this file</span>
          <span class="m3-drop__hint">Keeps the same link, so every page using it updates.</span>
        </label>
        <button class="m3-button m3-button--outlined m3-state" type="submit">Upload replacement</button>
      </form>
    </div>
    <div class="m3-dialog__foot">
      <form method="post" id="asset-delete" data-confirm="Remove this file? Any page using it will stop showing it.">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button class="m3-button m3-button--text m3-button--danger m3-state" type="submit">Remove</button>
      </form>
      <a class="m3-button m3-button--text m3-state" id="asset-download" href="#">Download</a>
      <button class="m3-button m3-button--filled m3-state" type="button" data-close-dialog>Done</button>
    </div>
  </div>
</div>
