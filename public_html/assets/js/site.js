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

/**
 * The masthead gets out of the way when you read down the page and comes back
 * the moment you scroll up, so navigation is always one small gesture away
 * rather than a trip to the top.
 */
(function () {
  const bar = document.getElementById('masthead');
  if (!bar) return;

  const height = () => bar.offsetHeight;
  let last = window.scrollY;
  let ticking = false;

  function update() {
    const now = Math.max(0, window.scrollY);
    const movement = now - last;

    bar.classList.toggle('is-detached', now > 8);

    // Ignore small movements, which are usually momentum rather than intent.
    if (Math.abs(movement) > 6) {
      // Never tuck it away before the reader has left the top of the page.
      const tuck = movement > 0 && now > height() * 2;
      bar.classList.toggle('is-tucked', tuck);
      last = now;
    }
    ticking = false;
  }

  window.addEventListener('scroll', () => {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(update);
  }, { passive: true });

  // Jumping to a chapter scrolls down, which would otherwise tuck the header
  // away just as the reader arrives. Keep it visible for that moment.
  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href^="#"]');
    if (!link) return;
    bar.classList.remove('is-tucked');
    last = window.scrollY;
    setTimeout(() => { last = window.scrollY; }, 600);
  });

  update();
})();

/** The section rail, as a slide-out panel on narrow screens. */
(function () {
  const toggle = document.getElementById('nav-toggle');
  const nav = document.getElementById('reader-nav');
  const scrim = document.getElementById('nav-scrim');
  if (!toggle || !nav) return;

  // The button only makes sense on a page that has a rail to open.
  toggle.hidden = false;

  const setOpen = (open) => {
    document.body.classList.toggle('nav-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    if (scrim) scrim.hidden = !open;
    if (open) {
      const first = nav.querySelector('a');
      if (first) first.focus({ preventScroll: true });
    }
  };

  toggle.addEventListener('click', () => setOpen(!document.body.classList.contains('nav-open')));
  if (scrim) scrim.addEventListener('click', () => setOpen(false));

  // Following a link should close it, since the destination is on this page.
  nav.addEventListener('click', (event) => {
    if (event.target.closest('a')) setOpen(false);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.body.classList.contains('nav-open')) {
      setOpen(false);
      toggle.focus();
    }
  });
})();
