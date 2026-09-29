<?php
/**
 * A style shown at its real size, with its spec beside it.
 *
 * Type is specified at two sizes — one for desktop, one for narrow screens — so
 * the specimen says both. A single number was a spec that described half of what
 * was designed, and left whoever was building it to guess the other half.
 *
 * The sizes are written as custom properties and switched by a media query, so
 * the sample is genuinely at its mobile size on a phone rather than only
 * claiming to be.
 *
 * @var array $data @var array $options
 */
$spec = block_option($options, 'spec', 'beside');
?>
<section class="block block--type block--spec-<?= e($spec) ?>">
  <?php if (!empty($data['heading'])): ?>
    <h3<?= editable('heading') ?>><?= e($data['heading']) ?></h3>
  <?php endif; ?>

  <?php foreach (($data['styles'] ?? []) as $i => $style): ?>
    <?php
      // Written into a style attribute, so each one is constrained to what it
      // is: a length, a number, a weight. A family is one of the names the
      // dropdown offers and nothing else, which is why it can be quoted here.
      $family  = trim((string) ($style['family'] ?? '')) ?: 'system-ui';
      $quoted  = preg_match('/^[A-Za-z][A-Za-z0-9 ]*$/', $family) === 1
               ? "'" . $family . "'"
               : $family;
      $desktop = spec_length((string) ($style['size'] ?? ''), '2rem');
      $mobile  = spec_length((string) ($style['size_mobile'] ?? ''), $desktop);
    ?>
    <article class="specimen" style="--sample-size: <?= e($desktop) ?>; --sample-size-mobile: <?= e($mobile) ?>">
      <p class="specimen__sample" style="font-family: <?= e($quoted) ?>, system-ui, sans-serif;
         font-weight: <?= e(spec_weight((string) ($style['weight'] ?? ''))) ?>;
         line-height: <?= e(spec_number((string) ($style['line_height'] ?? ''), '1.2')) ?>;
         letter-spacing: <?= e(spec_length((string) ($style['tracking'] ?? ''), 'normal')) ?>;">
        <span<?= editable("styles.$i.sample") ?>><?= e($style['sample'] ?? '') ?></span>
      </p>

      <div class="specimen__spec">
        <h4<?= editable("styles.$i.name") ?>><?= e($style['name'] ?? '') ?></h4>
        <p>
          <?= e($family) ?> <?= e($style['weight'] ?? '') ?>
          &middot; <?= e($style['size'] ?? '') ?><?php
            if (trim((string) ($style['size_mobile'] ?? '')) !== ''): ?>
            <span class="specimen__mobile">/ <?= e($style['size_mobile']) ?> on mobile</span><?php
            endif; ?>
          <?php if (!empty($style['line_height'])): ?>&middot; <?= e($style['line_height']) ?> line<?php endif; ?>
          <?php if (!empty($style['tracking'])): ?>&middot; <?= e($style['tracking']) ?> tracking<?php endif; ?>
        </p>
        <?php // The family, weight and sizes are not editable on the canvas:
              // each is written straight into a style attribute, so a half-typed
              // value would break the specimen as it was being typed. ?>
        <?php if (!empty($style['use_for'])): ?>
          <p class="specimen__use"<?= editable("styles.$i.use_for") ?>><?= e($style['use_for']) ?></p>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</section>
