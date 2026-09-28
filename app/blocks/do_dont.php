<?php
/**
 * Two columns of guidance, each its own list.
 *
 * The headings are the block's own, so this covers Do and Do not, "we say" and
 * "we do not say", and Use and Avoid without three block types. Only the columns
 * with something in them are drawn: a page with nothing in the right-hand list
 * should show one column, not one column and a gap.
 *
 * @var array $data @var array $options
 */
$layout  = block_option($options, 'layout', 'columns');
$markers = block_option($options, 'markers', 'show');

$columns = [
    ['key' => 'dos',   'stance' => 'do',   'fallback' => 'Do',     'mark' => '✓'],
    ['key' => 'donts', 'stance' => 'dont', 'fallback' => 'Do not', 'mark' => '✕'],
];

// Keys are kept rather than renumbered, because an editable() path is a route
// into the stored data and a filtered, renumbered list would edit the wrong row.
$filled = 0;
foreach ($columns as $column) {
    foreach ((array) ($data[$column['key']] ?? []) as $line) {
        if (trim((string) ($line['text'] ?? '')) !== '') { $filled++; break; }
    }
}
?>
<section class="block block--rules">
  <?php if (!empty($data['heading'])): ?>
    <h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3>
  <?php endif; ?>

  <div class="rules rules--<?= e($layout) ?><?= $filled === 1 ? ' rules--one' : '' ?>">
    <?php foreach ($columns as $column): ?>
      <?php
        $lines = array_filter(
            (array) ($data[$column['key']] ?? []),
            fn($line) => trim((string) ($line['text'] ?? '')) !== ''
        );
      ?>
      <?php if (!$lines) { continue; } ?>

      <div class="rules__col rules__col--<?= e($column['stance']) ?>">
        <?php
          // The editable region is a span around the words only, never the whole
          // heading: the tick is inside the heading, and editing in place reads
          // the region's text back, so a marker in it would be swallowed into the
          // stored label on the first keystroke. check-blocks --edit caught
          // exactly that. Same reason an accordion's summary wraps its own span.
          $labelKey = $column['key'] === 'dos' ? 'do_label' : 'dont_label';
        ?>
        <p class="rules__head">
          <?php if ($markers === 'show'): ?>
            <span class="rules__mark" aria-hidden="true"><?= $column['mark'] ?></span>
          <?php endif; ?>
          <span<?= editable($labelKey) ?>><?= e(trim((string) ($data[$labelKey] ?? '')) ?: $column['fallback']) ?></span>
        </p>

        <ul class="rules__list">
          <?php foreach ($lines as $i => $line): ?>
            <li class="rule">
              <p class="rule__text"<?= editable($column['key'] . ".$i.text") ?>><?= e($line['text']) ?></p>
              <?php // A div, because paragraphs() emits its own <p> and nesting
                    // one closes the outer paragraph early. ?>
              <?php if (!empty($line['why'])): ?>
                <div class="rule__why"<?= editable($column['key'] . ".$i.why", true) ?>><?= paragraphs($line['why']) ?></div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
</section>
