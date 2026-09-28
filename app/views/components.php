<?php
/**
 * The component index: every component, grouped, each showing the real thing.
 *
 * The thumbnail is the component itself in its own sandboxed frame, not a
 * screenshot. A screenshot would have to be generated, stored, and regenerated
 * whenever the CSS moved — and the one time it did not, the index would be
 * showing something that no longer exists. This cannot go out of date.
 *
 * The cost is one iframe per card. Three things keep that reasonable: only the
 * default variant is shown, every frame is `loading="lazy"` so only what is on
 * screen is fetched, and the thumbnails carry no height bridge — they are a
 * fixed window onto the component, cropped, rather than sized to it. If this
 * ever gets slow with a hundred components, the answer is paging the index, not
 * going back to pictures.
 */
?>
<main class="index index--components" id="content">

  <?php if (!$components): ?>
    <div class="index__empty">
      <h1>No components yet.</h1>
      <p>This is where web components are documented for design and development:
         what each one is for, its states, and the code to use it.</p>
      <?php if (can_edit()): ?>
        <p class="index__note"><a href="/admin/components">Add the first one</a>.</p>
      <?php else: ?>
        <p class="index__note">The brand guidelines are under <a href="/">Brand</a>.</p>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <header class="components__head">
      <h1>Components</h1>
      <p>The real thing, its states, and the code to use it. What is here is what
         is in the theme — if the two disagree, this is the one to fix.</p>
    </header>

    <?php foreach ($groups as $group => $rows): ?>
      <section class="components__group">
        <h2 class="components__groupname"><?= e($group) ?></h2>

        <ul class="components__list">
          <?php foreach ($rows as $component): ?>
            <?php
              // Attached by components_with_variants(), in one query for the
              // whole page rather than one per card.
              $rowVariants = $component['variants'] ?? [];
              $default     = component_default_variant($rowVariants);
            ?>
            <li class="components__item">
              <a class="ccard" href="/components/<?= e($component['slug']) ?>">
                <span class="ccard__stage">
                  <?php if ($default): ?>
                    <?php /*
                      Same sandbox as the full preview: allow-scripts and not
                      allow-same-origin. pointer-events are off in CSS so the
                      card stays one link rather than a component you can half
                      interact with inside something that navigates.
                    */ ?>
                    <iframe class="ccard__frame"
                            src="/components/<?= e($component['slug']) ?>/frame/<?= (int) $default['id'] ?>"
                            title="<?= e($component['name']) ?>"
                            sandbox="allow-scripts"
                            referrerpolicy="no-referrer"
                            tabindex="-1"
                            aria-hidden="true"
                            loading="lazy"></iframe>
                  <?php else: ?>
                    <span class="ccard__none">Nothing to show yet</span>
                  <?php endif; ?>
                </span>

                <span class="ccard__body">
                  <span class="ccard__name">
                    <?= e($component['name']) ?>
                    <?php if ($component['status'] !== 'ready'): ?>
                      <span class="ccard__chip ccard__chip--<?= e($component['status']) ?>"><?= e(component_status_label($component['status'])) ?></span>
                    <?php endif; ?>
                  </span>
                  <?php if (!empty($component['summary'])): ?>
                    <span class="ccard__summary"><?= e($component['summary']) ?></span>
                  <?php endif; ?>
                  <span class="ccard__count">
                    <?= count($rowVariants) ?> <?= count($rowVariants) === 1 ? 'variant' : 'variants' ?>
                  </span>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
