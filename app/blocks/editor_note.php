<?php
/**
 * A note to whoever is editing. Never shown to a reader.
 *
 * **It is not written at all unless the person reading can edit.** Not hidden
 * with CSS, not rendered and then removed by script — simply absent from the
 * HTML. Anything else means the text is in the page source of a public site,
 * which for a note saying "check this with legal before we publish" is the whole
 * problem rather than a near miss.
 *
 * `can_edit()` is the same test the draft pages use, so a note and a draft are
 * visible to exactly the same people.
 *
 * @var array $data @var array $options
 */
if (!can_edit()) {
    return;
}

$tone = block_option($options, 'tone', 'note');
$labels = ['note' => 'Note', 'question' => 'Open question', 'todo' => 'To do'];
?>
<aside class="block block--note note note--<?= e($tone) ?>">
  <p class="note__flag">
    <span class="note__badge"><?= e($labels[$tone] ?? 'Note') ?></span>
    <span class="note__only">Only people signed in can see this</span>
  </p>

  <?php if (!empty($data['heading'])): ?>
    <h3 class="note__heading"<?= editable('heading') ?>><?= e($data['heading']) ?></h3>
  <?php endif; ?>

  <div class="note__body"<?= editable('body', true) ?>><?= paragraphs($data['body'] ?? '') ?></div>

  <?php if (!empty($data['who'])): ?>
    <p class="note__who"<?= editable('who') ?>>For <?= e($data['who']) ?></p>
  <?php endif; ?>
</aside>
