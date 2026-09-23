// Copy a colour value to the clipboard from any swatch.
document.addEventListener('click', async (event) => {
  const chip = event.target.closest('[data-copy]');
  if (!chip) return;
  try {
    await navigator.clipboard.writeText(chip.dataset.copy);
    const label = chip.querySelector('.swatch__copy');
    chip.classList.add('is-copied');
    if (label) label.textContent = 'Copied';
    setTimeout(() => {
      chip.classList.remove('is-copied');
      if (label) label.textContent = 'Copy';
    }, 1400);
  } catch (error) {
    // Clipboard blocked, leave the value visible for manual selection.
  }
});

// Index filters: show only the chosen section, without a page load.
(function () {
  const filters = document.querySelectorAll('.filter');
  const items = document.querySelectorAll('.cards__item');
  if (!filters.length || !items.length) return;

  filters.forEach((button) => {
    button.addEventListener('click', () => {
      const wanted = button.dataset.filter;
      filters.forEach((other) => other.classList.toggle('is-on', other === button));
      items.forEach((item) => {
        item.hidden = wanted !== 'all' && item.dataset.section !== wanted;
      });
    });
  });
})();

// Highlight the chapter currently on screen in the section rail.
(function () {
  const links = document.querySelectorAll('.reader__link');
  const chapters = document.querySelectorAll('.chapter');
  if (!links.length || !chapters.length || !('IntersectionObserver' in window)) return;

  const byId = {};
  links.forEach((link) => { byId[link.dataset.anchor] = link; });

  const seen = new Set();
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) { seen.add(entry.target.id); } else { seen.delete(entry.target.id); }
    });
    // The first visible chapter in document order wins, so the rail does not
    // flicker when two are on screen at once.
    let current = null;
    chapters.forEach((chapter) => {
      if (!current && seen.has(chapter.id)) current = chapter.id;
    });
    links.forEach((link) => link.classList.toggle('is-current', link.dataset.anchor === current));
  }, { rootMargin: '-15% 0px -70% 0px', threshold: 0 });

  chapters.forEach((chapter) => observer.observe(chapter));
})();
