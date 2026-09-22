<main class="index">
  <div class="index__lede">
    <h1>Everything you need to make something that looks like us.</h1>
    <p>Colour, type, logo and tone, with the files to go with them. If you are about to make something for Years, start here.</p>
  </div>

  <?php if (!$sections): ?>
    <p class="empty">Nothing published yet. Sign in and add your first page.</p>
  <?php endif; ?>

  <?php foreach ($sections as $section => $pages): ?>
    <section class="index__section">
      <h2><?= e($section) ?></h2>
      <ul>
        <?php foreach ($pages as $page): ?>
          <li>
            <a href="/g/<?= e($page['slug']) ?>">
              <span class="index__name"><?= e($page['title']) ?></span>
              <?php if (!empty($page['summary'])): ?>
                <span class="index__summary"><?= e($page['summary']) ?></span>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
</main>
