<?php
/**
 * A heading on its own, with an optional standfirst under it.
 *
 * The level and the size are separate settings on purpose. The level is what the
 * heading means — where it sits in the document's outline, which is what a screen
 * reader follows and what the search index ranks by — and the size is how much
 * emphasis it carries on the page. Tying the two together is the usual reason
 * people reach for the wrong level to get the right size.
 *
 * @var array $data @var array $options
 */
$level = block_option($options, 'level', 'h2');
$size  = block_option($options, 'size', 'inherit');
$align = block_option($options, 'align', 'left');
$rule  = block_option($options, 'rule', 'no');

// Only these three are ever emitted as a tag name. h1 is the page's own, and is
// not on offer here: a second one on a page leaves the outline with two roots.
$tag = in_array($level, ['h2', 'h3', 'h4'], true) ? $level : 'h2';
?>
<section class="block block--heading heading heading--<?= e($align) ?> heading--size-<?= e($size) ?><?= $rule === 'yes' ? ' heading--ruled' : '' ?>">
  <<?= $tag ?> class="heading__text"<?= editable('text') ?>><?= e($data['text'] ?? '') ?></<?= $tag ?>>
  <?php if (!empty($data['standfirst'])): ?>
    <?php // A div, not a p: paragraphs() emits its own <p> tags and a nested one
          // is invalid — the browser closes the outer paragraph early and the
          // rest of the standfirst escapes the element it was styled by. ?>
    <div class="heading__standfirst"<?= editable('standfirst', true) ?>><?= paragraphs($data['standfirst']) ?></div>
  <?php endif; ?>
</section>
