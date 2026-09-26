<div class="m3-page">
  <div class="m3-page__bar">
    <div class="m3-page__titles">
      <a class="m3-back" href="/admin/pages/<?= (int) $page['id'] ?>"><?= ui_icon('back', 'icon') ?> Back to the editor</a>
      <h1>History</h1>
      <p class="m3-page__meta"><?= e($page['title']) ?>, last <?= count($versions) ?> saves</p>
    </div>
  </div>

  <?php if (count($versions) < 2): ?>
    <p class="m3-empty">Only one version so far. Every save adds one.</p>
  <?php endif; ?>

  <?php
  /**
   * What changed between one save and the one before it.
   *
   * A count of blocks by type is crude, but it is the difference between "some
   * time on Tuesday" and "this is the one where the palette went in", and it
   * needs nothing stored that is not already there.
   */
  $summarise = function (?string $json): array {
      $out = [];
      foreach (json_decode((string) $json, true) ?: [] as $block) {
          $type = $block['type'] ?? '';
          if ($type !== '') {
              $out[$type] = ($out[$type] ?? 0) + 1;
          }
      }
      return $out;
  };

  // $isFirst is passed rather than inferred from an empty $before: a save that
  // genuinely had no blocks in it is not the same as there being no save
  // before this one, and treating them alike labelled the second save "First".
  $describe = function (array $now, array $before, bool $isFirst): string {
      if ($isFirst) {
          return 'First save';
      }
      $added = [];
      $removed = [];
      foreach (block_types() as $type => $def) {
          $difference = ($now[$type] ?? 0) - ($before[$type] ?? 0);
          if ($difference > 0) { $added[] = $difference . ' ' . strtolower($def['label']); }
          if ($difference < 0) { $removed[] = abs($difference) . ' ' . strtolower($def['label']); }
      }
      $parts = [];
      if ($added)   { $parts[] = 'added ' . implode(', ', $added); }
      if ($removed) { $parts[] = 'removed ' . implode(', ', $removed); }
      // No change in the shape means the change was inside the blocks.
      return $parts ? ucfirst(implode('; ', $parts)) : 'Edited the content';
  };
  ?>

  <table class="m3-table">
    <thead><tr><th>Saved</th><th>Title at the time</th><th>What changed</th><th>By</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($versions as $i => $version): ?>
      <?php
        // The list runs newest first, so the version before this one is the
        // next row down.
        $hasPrevious = isset($versions[$i + 1]);
        $now = $summarise($version['blocks']);
        $previous = $hasPrevious ? $summarise($versions[$i + 1]['blocks']) : [];
        $blockCount = array_sum($now);
      ?>
      <tr>
        <td><?= e(date('j M Y, H:i', strtotime($version['created_at']))) ?></td>
        <td><?= e($version['title']) ?></td>
        <td class="m3-table__what">
          <?= e($describe($now, $previous, !$hasPrevious)) ?>
          <span class="m3-table__count"><?= (int) $blockCount ?> <?= $blockCount === 1 ? 'block' : 'blocks' ?></span>
        </td>
        <td><?= e($version['author'] ?? 'Unknown') ?></td>
        <td class="m3-table__actions">
          <a class="m3-button m3-button--text m3-state"
             href="/admin/pages/<?= (int) $page['id'] ?>/versions/<?= (int) $version['id'] ?>/preview"
             target="_blank" rel="noopener">Preview</a>
          <?php if ($i === 0): ?>
            <span class="m3-chip m3-chip--assist">Current</span>
          <?php else: ?>
            <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/restore/<?= (int) $version['id'] ?>"
                  data-confirm="Put the page back to how it was on <?= e(date('j M Y \a\t H:i', strtotime($version['created_at']))) ?>? The version you have now is saved to history first.">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <button class="m3-button m3-button--text m3-state" type="submit">Restore this</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="m3-empty">Restoring does not lose anything. The current version is saved to history first.</p>
</div>
