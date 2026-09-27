<?php
/**
 * A component's history.
 *
 * The same idea as a page's, and the same collapsing and pruning policy, but what
 * it can say about a version is different: a page's shape is a list of block
 * types, a component's is its variants and how much code is in them. So the
 * summary describes what actually distinguishes one save from the next here —
 * which variants existed, and whether the code or only the details moved.
 *
 * Nothing on this page prints a variant's markup. The snapshot is decoded to
 * count and name things, never to render them.
 */

/** The parts of a snapshot worth comparing between saves. */
$shape = function (?string $json): array {
    $data = json_decode((string) $json, true);
    if (!is_array($data)) {
        return ['labels' => [], 'code' => 0, 'meta' => ''];
    }
    $labels = [];
    $code = strlen((string) ($data['component']['base_css'] ?? ''))
          + strlen((string) ($data['component']['base_js'] ?? ''));
    foreach ($data['variants'] ?? [] as $variant) {
        $labels[] = (string) ($variant['label'] ?? '');
        $code += strlen((string) ($variant['markup'] ?? ''))
               + strlen((string) ($variant['variant_css'] ?? ''));
    }
    return [
        'labels' => $labels,
        'code'   => $code,
        // Everything that is not code, so a details-only edit can be named as one.
        'meta'   => implode('|', [
            $data['component']['name'] ?? '',
            $data['component']['group_name'] ?? '',
            $data['component']['status'] ?? '',
            $data['component']['summary'] ?? '',
            $data['component']['slug'] ?? '',
            $data['component']['figma_url'] ?? '',
        ]),
    ];
};

$describe = function (array $now, array $was, bool $isFirst): string {
    if ($isFirst) {
        return 'First save';
    }
    $parts = [];

    $added   = array_diff($now['labels'], $was['labels']);
    $removed = array_diff($was['labels'], $now['labels']);
    if ($added)   { $parts[] = 'added ' . implode(', ', $added); }
    if ($removed) { $parts[] = 'removed ' . implode(', ', $removed); }

    $difference = $now['code'] - $was['code'];
    if ($difference !== 0 && !$added && !$removed) {
        $parts[] = ($difference > 0 ? 'code grew by ' : 'code shrank by ')
                 . number_format(abs($difference)) . ' characters';
    }
    if ($now['meta'] !== $was['meta']) {
        $parts[] = 'details changed';
    }

    // Reordering and a rewrite that happens to be the same length both land here.
    return $parts ? ucfirst(implode(', ', $parts)) : 'Edited';
};
?>
<div class="m3-page">
  <div class="m3-page__bar">
    <div class="m3-page__titles">
      <a class="m3-back" href="/admin/components/<?= (int) $component['id'] ?>"><?= ui_icon('back', 'icon') ?> Back to the component</a>
      <h1>History</h1>
      <p class="m3-page__meta">
        <?= e($component['name']) ?>, last <?= count($versions) ?>
        <?= count($versions) === 1 ? 'save' : 'saves' ?>
      </p>
    </div>
  </div>

  <?php if (count($versions) < 2): ?>
    <p class="m3-empty">Only one version so far. Every save adds one, and saves a
       few minutes apart by the same person fold into a single entry.</p>
  <?php endif; ?>

  <?php if ($versions): ?>
    <div class="m3-list-head m3-list-head--versions">
      <span>Saved</span>
      <span>By</span>
      <span>What changed</span>
      <span>Variants</span>
      <span>Actions</span>
    </div>
    <hr class="m3-divider">
    <ul class="m3-list m3-list--versions">
      <?php foreach ($versions as $i => $version): ?>
        <?php
          $now  = $shape($version['snapshot']);
          $next = $versions[$i + 1] ?? null;                 // the save before this
          $was  = $next ? $shape($next['snapshot']) : ['labels' => [], 'code' => 0, 'meta' => ''];
          $at   = strtotime((string) $version['created_at']);
        ?>
        <li class="m3-list-item m3-state">
          <span class="m3-list-item__headline">
            <?= e(date('j M, H:i', $at)) ?>
            <?php if ($i === 0): ?><span class="m3-chip m3-chip--draft">Current</span><?php endif; ?>
          </span>
          <span class="m3-list-item__date"><?= e($version['author'] ?? 'Unknown') ?></span>
          <span class="m3-list-item__summary"><?= e($describe($now, $was, $next === null)) ?></span>
          <span class="m3-list-item__date">
            <?= count($now['labels']) ?><?php if ($now['labels']): ?>:
              <?= e(str_trim_to(implode(', ', $now['labels']), 40)) ?>
            <?php endif; ?>
          </span>
          <span class="m3-list-item__actions">
            <?php if ($i > 0): ?>
              <?php // Restoring records the current state first, so it is undoable. ?>
              <form method="post" action="/admin/components/<?= (int) $component['id'] ?>/restore/<?= (int) $version['id'] ?>"
                    data-confirm="Put &quot;<?= e($component['name']) ?>&quot; back to how it was on <?= e(date('j M \a\t H:i', $at)) ?>? The version you are replacing is saved first, so this can be undone.">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <button class="m3-button m3-button--text m3-state" type="submit">Restore</button>
              </form>
            <?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
