<?php
/**
 * The derived spec table, shared by the public component page and the admin
 * screen so the two cannot describe the same component differently.
 *
 * Expects: $spec (from component_spec), and optionally $specEditable — an
 * `/admin/...` action, which turns each row's note into a field.
 *
 * The honesty line at the bottom is not decoration. This reads declarations; it
 * does not resolve the cascade or follow a var() to a value, and a reader who
 * assumed otherwise would be misled by a table that looks authoritative.
 */
$editable = !empty($specEditable);
?>
<?php if ($spec['groups'] || $spec['states']): ?>
<section class="devspec">
  <h2 class="devspec__title">For developers</h2>

  <?php if ($spec['flagged'] > 0): ?>
    <p class="devspec__flagged">
      <?= (int) $spec['flagged'] ?>
      <?= $spec['flagged'] === 1 ? 'value is' : 'values are' ?>
      not one of the brand's — either deliberate, and worth a note saying why, or
      worth changing.
    </p>
  <?php endif; ?>

  <?php if ($editable): ?><form method="post" action="<?= e($specEditable) ?>" data-guard>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><?php endif; ?>

  <?php foreach ($spec['groups'] as $group): ?>
    <h3 class="devspec__group"><?= e($group['label']) ?></h3>
    <table class="devspec__table">
      <thead>
        <tr><th>Property</th><th>Value</th><th>Token</th><th>Where</th></tr>
      </thead>
      <tbody>
        <?php foreach ($group['rows'] as $row): ?>
          <tr<?= $row['flagged'] ? ' class="devspec__row--flagged"' : '' ?>>
            <td class="devspec__prop"><?= e($row['property']) ?></td>
            <td class="devspec__value"><code><?= e($row['value']) ?></code></td>
            <td class="devspec__token">
              <?php if (!empty($row['partial'])): ?>
                <span class="devspec__partly">partly <code><?= e($row['token']) ?></code></span>
              <?php elseif ($row['token'] !== null): ?>
                <code><?= e($row['token']) ?></code>
              <?php elseif ($row['flagged']): ?>
                <span class="devspec__off">not a token</span>
              <?php else: ?>
                <span class="devspec__off">&mdash;</span>
              <?php endif; ?>
            </td>
            <td class="devspec__where">
              <code><?= e(str_trim_to(implode(', ', $row['where']), 60)) ?></code>
            </td>
          </tr>

          <?php if ($editable): ?>
            <tr class="devspec__noterow">
              <td colspan="4">
                <input class="devspec__notefield" type="text" maxlength="400"
                       name="note[<?= e($row['id']) ?>]"
                       value="<?= e((string) ($row['note'] ?? '')) ?>"
                       aria-label="Note on <?= e($row['property']) ?> <?= e($row['value']) ?>"
                       placeholder="<?= $row['flagged'] ? 'Why is this not a token?' : 'Anything a developer should know' ?>">
              </td>
            </tr>
          <?php elseif (!empty($row['note'])): ?>
            <tr class="devspec__noterow">
              <td colspan="4"><p class="devspec__note"><?= e($row['note']) ?></p></td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; ?>

  <?php if ($editable): ?>
    <button class="m3-button m3-button--filled m3-state" type="submit">Save notes</button>
    </form>
  <?php endif; ?>

  <?php if ($spec['states']): ?>
    <h3 class="devspec__group">States it defines</h3>
    <ul class="devspec__states">
      <?php foreach ($spec['states'] as $state): ?>
        <li><?= e($state) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php // An absence is worth naming: a component with no keyboard focus style is
        // a real finding, and a list of what is present does not show it. ?>
  <?php if ($spec['groups'] && !in_array('Keyboard focus', $spec['states'], true)): ?>
    <p class="devspec__missing">No keyboard focus style is defined. If this component
       can be focused, that is a problem for anyone not using a mouse.</p>
  <?php endif; ?>

  <p class="devspec__caveat">
    Read from this component's own CSS. It reports what the declarations say — it
    does not work out which rule wins, or follow a token to its value, so treat it
    as a summary of the code rather than of the rendered result.
  </p>
</section>
<?php endif; ?>
