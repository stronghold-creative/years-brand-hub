<main class="wrap">
  <div class="wrap__head"><h1>Assets</h1></div>

  <?php if ($notice): ?><p class="notice"><?= e($notice) ?></p><?php endif; ?>

  <section class="panel">
    <h2>Upload a file</h2>
    <form method="post" action="/admin/assets/upload" enctype="multipart/form-data" class="grid4">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <label class="field">File<input type="file" name="file" required></label>
      <label class="field">Label<input type="text" name="label" placeholder="Primary logo, white"></label>
      <label class="field">Tags, comma separated<input type="text" name="tags" placeholder="logo, print"></label>
      <label class="field">Version<input type="text" name="version" value="1.0"></label>
      <button type="submit">Upload</button>
    </form>
    <p class="hint">Accepted: SVG, PNG, JPG, WEBP, GIF, PDF, EPS, AI, ZIP, OTF, TTF, WOFF, WOFF2, PPTX, DOCX, XLSX, MP4. Up to 50 MB.</p>
  </section>

  <?php if ($tags): ?>
    <p class="hint tagline">Tags in use:
      <?php foreach ($tags as $tag => $count): ?>
        <span class="tag"><?= e($tag) ?> <span class="tag__count"><?= (int) $count ?></span></span>
      <?php endforeach; ?>
    </p>
  <?php endif; ?>

  <?php if (!$assets): ?>
    <p class="empty">No assets yet. Upload the logo files first, they are what people come for.</p>
  <?php else: ?>
    <table class="pages assets">
      <thead>
        <tr><th>File</th><th>Label and tags</th><th>Size</th><th>Downloads</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($assets as $asset): ?>
        <tr>
          <td>
            <a href="/d/<?= (int) $asset['id'] ?>"><?= e($asset['original_name']) ?></a>
            <span class="hint">v<?= e($asset['version']) ?>, <?= e(date('j M Y', strtotime($asset['uploaded_at']))) ?></span>
          </td>
          <td>
            <form method="post" action="/admin/assets/<?= (int) $asset['id'] ?>/update" class="stack">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="text" name="label" value="<?= e($asset['label']) ?>" aria-label="Label">
              <input type="text" name="tags" value="<?= e((string) $asset['tags']) ?>" aria-label="Tags" placeholder="tags">
              <input type="text" name="version" value="<?= e($asset['version']) ?>" aria-label="Version" size="4">
              <button class="linkbutton" type="submit">Save</button>
            </form>
          </td>
          <td><?= e(asset_human_size((int) $asset['bytes'])) ?></td>
          <td><?= (int) $asset['downloads'] ?></td>
          <td class="rowactions">
            <form method="post" action="/admin/assets/<?= (int) $asset['id'] ?>/replace" enctype="multipart/form-data" class="stack">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="file" name="file" required aria-label="Replacement file">
              <button class="linkbutton" type="submit">Replace</button>
            </form>
            <form method="post" action="/admin/assets/<?= (int) $asset['id'] ?>/delete"
                  onsubmit="return confirm('Remove <?= e($asset['label']) ?>? Any page linking to it will stop showing it.')">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <button class="linkbutton danger" type="submit">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="hint">Replacing a file keeps its link, so pages and bundles pick up the new version straight away.</p>
  <?php endif; ?>
</main>
