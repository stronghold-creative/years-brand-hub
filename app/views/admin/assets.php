<div class="m3-page m3-page--wide">
  <div class="m3-page__bar">
    <div class="m3-page__titles">
      <h1>Assets</h1>
      <p class="m3-page__meta"><?= count($assets) ?> <?= count($assets) === 1 ? 'file' : 'files' ?></p>
    </div>
    <div class="m3-page__action">
      <a class="m3-button m3-button--filled m3-state" href="#upload">Upload a file</a>
    </div>
  </div>

  <?php if ($notice): ?><p class="m3-banner"><?= e($notice) ?></p><?php endif; ?>

  <section class="m3-card" id="upload">
    <h2 class="m3-card__title">Upload a file</h2>
    <form method="post" action="/admin/assets/upload" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div class="m3-field">
        <input class="m3-field__input" type="file" name="file" id="a-file" required style="padding:.875rem 1rem">
        <span class="m3-field__support">SVG, PNG, JPG, WEBP, GIF, PDF, EPS, AI, ZIP, OTF, TTF, WOFF, WOFF2, PPTX, DOCX, XLSX, MP4. Up to 50 MB.</span>
      </div>
      <div class="m3-editor__metarow">
        <div class="m3-field">
          <input class="m3-field__input" type="text" name="label" id="a-label" placeholder=" ">
          <label class="m3-field__label" for="a-label">Label</label>
        </div>
        <div class="m3-field">
          <input class="m3-field__input" type="text" name="version" id="a-version" placeholder=" " value="1.0">
          <label class="m3-field__label" for="a-version">Version</label>
        </div>
      </div>
      <div class="m3-field">
        <input class="m3-field__input" type="text" name="tags" id="a-tags" placeholder=" ">
        <label class="m3-field__label" for="a-tags">Tags</label>
        <span class="m3-field__support">Comma separated. Download blocks pull files in by tag, for example: logo, print</span>
      </div>
      <button class="m3-button m3-button--filled m3-state" type="submit">Upload</button>
    </form>
  </section>

  <?php if ($tags): ?>
    <div class="m3-tags">
      <?php foreach ($tags as $tag => $count): ?>
        <span class="m3-chip m3-chip--assist"><?= e($tag) ?> <span class="m3-chip__count"><?= (int) $count ?></span></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$assets): ?>
    <div class="m3-empty-state">
      <h2>No assets yet</h2>
      <p>Upload the logo files first. They are what people come here for.</p>
    </div>
  <?php else: ?>
    <table class="m3-table">
      <thead><tr><th>File</th><th>Label, tags and version</th><th>Size</th><th>Downloads</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($assets as $asset): ?>
        <tr>
          <td>
            <a href="/d/<?= (int) $asset['id'] ?>"><?= e($asset['original_name']) ?></a><br>
            <span class="m3-body-small"><?= e(date('j M Y', strtotime($asset['uploaded_at']))) ?></span>
          </td>
          <td>
            <form method="post" action="/admin/assets/<?= (int) $asset['id'] ?>/update" class="m3-inline">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="text" name="label" value="<?= e($asset['label']) ?>" aria-label="Label">
              <input type="text" name="tags" value="<?= e((string) $asset['tags']) ?>" aria-label="Tags" placeholder="tags">
              <input type="text" name="version" value="<?= e($asset['version']) ?>" aria-label="Version" size="4" style="max-width:5rem">
              <button class="m3-button m3-button--text m3-state" type="submit">Save</button>
            </form>
          </td>
          <td><?= e(asset_human_size((int) $asset['bytes'])) ?></td>
          <td><?= (int) $asset['downloads'] ?></td>
          <td>
            <form method="post" action="/admin/assets/<?= (int) $asset['id'] ?>/replace" enctype="multipart/form-data" class="m3-inline">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="file" name="file" required aria-label="Replacement file">
              <button class="m3-button m3-button--text m3-state" type="submit">Replace</button>
            </form>
            <form method="post" action="/admin/assets/<?= (int) $asset['id'] ?>/delete"
                  data-confirm="Remove <?= e($asset['label']) ?>? Any page using it will stop showing it.">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <button class="m3-button m3-button--text m3-button--danger m3-state" type="submit">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="m3-empty">Replacing a file keeps its link, so pages and bundles pick up the new version straight away.</p>
  <?php endif; ?>
</div>
