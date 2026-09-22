<div class="reader<?= count($nav) < 2 ? ' reader--solo' : '' ?>">
  <nav class="reader__nav" aria-label="Guidelines">
    <?php $currentSection = null; foreach ($nav as $item): ?>
      <?php if ($item['section'] !== $currentSection): $currentSection = $item['section']; ?>
        <h2><?= e($currentSection) ?></h2>
      <?php endif; ?>
      <a href="/g/<?= e($item['slug']) ?>"<?= $item['slug'] === $page['slug'] ? ' aria-current="page"' : '' ?>><?= e($item['title']) ?><?php if (($item['status'] ?? 'published') !== 'published'): ?><span class="reader__draftdot" title="Draft"></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>

  <main class="reader__body">
    <?php if ($page['status'] !== 'published'): ?>
      <p class="draft-flag">Draft. Only editors can see this.</p>
    <?php endif; ?>
    <h1><?= e($page['title']) ?></h1>
    <?php if (!empty($page['summary'])): ?><p class="reader__summary"><?= e($page['summary']) ?></p><?php endif; ?>
    <?php render_blocks($blocks); ?>
    <p class="reader__updated">Last updated <?= e(date('j F Y', strtotime($page['updated_at']))) ?></p>
  </main>
</div>
