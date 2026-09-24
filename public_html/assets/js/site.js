/**
 * Each feature below runs inside its own guard. Without this, one failure in
 * an immediately-invoked block stops every block after it from running at all,
 * which is exactly how the header behaviour went missing once already.
 */
function feature(name, run) {
  try {
    run();
  } catch (error) {
    console.error('site.js: ' + name + ' failed', error);
  }
}

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
feature('chapter tracking', function () {
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
});

/**
 * The masthead stays on screen and becomes compact as you read down, so the
 * logo is always in the top left. Scrolling up restores it in full.
 */
feature('masthead', function () {
  const bar = document.getElementById('masthead');
  if (!bar) return;

  const COMPACT_AFTER = 120;   // how far down before it compacts
  const INTENT = 5;            // movement below this is momentum, not intent

  let last = window.scrollY;
  let compact = false;
  let queued = false;

  function apply() {
    const now = Math.max(0, window.scrollY);
    const moved = now - last;

    bar.classList.toggle('is-detached', now > 4);

    if (now <= COMPACT_AFTER) {
      // Near the top the bar is always shown in full.
      if (compact) { compact = false; bar.classList.remove('is-compact'); }
      last = now;
      queued = false;
      return;
    }

    if (Math.abs(moved) >= INTENT) {
      const wanted = moved > 0;               // down compacts, up expands
      if (wanted !== compact) {
        compact = wanted;
        bar.classList.toggle('is-compact', compact);
      }
      last = now;
    }
    queued = false;
  }

  window.addEventListener('scroll', function () {
    if (queued) return;
    queued = true;
    window.requestAnimationFrame(apply);
  }, { passive: true });

  // Jumping to a chapter scrolls down, so the bar would compact just as the
  // reader arrives. Show it in full for that moment instead.
  document.addEventListener('click', function (event) {
    if (!event.target.closest('a[href^="#"]')) return;
    compact = false;
    bar.classList.remove('is-compact');
    window.setTimeout(function () { last = window.scrollY; }, 700);
  });

  apply();
});

/**
 * The section rail as a slide-out panel on narrow screens, with the page
 * behind held still while it is open.
 */
feature('rail panel', function () {
  const toggle = document.getElementById('nav-toggle');
  const nav = document.getElementById('reader-nav');
  const scrim = document.getElementById('nav-scrim');
  if (!toggle || !nav) return;

  toggle.hidden = false;   // only pages with a rail get the button
  let offset = 0;

  function setOpen(open) {
    if (open) {
      // Fixing the body loses the scroll position, so it is recorded and put
      // back afterwards. Nothing else reliably stops iOS scrolling behind.
      offset = window.scrollY;
      document.body.style.top = '-' + offset + 'px';
      document.body.classList.add('nav-open');
    } else {
      document.body.classList.remove('nav-open');
      document.body.style.top = '';
      window.scrollTo(0, offset);
    }
    toggle.setAttribute('aria-expanded', String(open));
    if (scrim) scrim.hidden = !open;
    if (open) {
      const first = nav.querySelector('a');
      if (first) first.focus({ preventScroll: true });
    }
  }

  const isOpen = () => document.body.classList.contains('nav-open');

  toggle.addEventListener('click', function () { setOpen(!isOpen()); });
  if (scrim) scrim.addEventListener('click', function () { setOpen(false); });

  nav.addEventListener('click', function (event) {
    const link = event.target.closest('a');
    if (!link) return;

    // An anchor on this page: close first, then jump, or the scroll lock
    // swallows the movement.
    if (link.getAttribute('href').startsWith('#')) {
      event.preventDefault();
      const target = document.getElementById(link.getAttribute('href').slice(1));
      setOpen(false);
      if (target) {
        window.requestAnimationFrame(function () {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
          history.replaceState(null, '', link.getAttribute('href'));
        });
      }
      return;
    }
    setOpen(false);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && isOpen()) {
      setOpen(false);
      toggle.focus();
    }
  });

  // Returning to a wide screen must not leave the page locked.
  const wide = window.matchMedia('(min-width: 56.01rem)');
  const release = function () { if (wide.matches && isOpen()) setOpen(false); };
  if (wide.addEventListener) { wide.addEventListener('change', release); }
});

/** Tabs. Arrow keys move between them, as the pattern expects. */
feature('tabs', function () {
  document.querySelectorAll('[data-tabs]').forEach(function (group) {
    const tabs = Array.from(group.querySelectorAll('[role="tab"]'));
    if (!tabs.length) return;

    function show(index) {
      tabs.forEach(function (tab, i) {
        const on = i === index;
        tab.setAttribute('aria-selected', String(on));
        tab.tabIndex = on ? 0 : -1;
        const panel = document.getElementById(tab.getAttribute('aria-controls'));
        if (panel) panel.hidden = !on;
      });
    }

    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () { show(i); });
      tab.addEventListener('keydown', function (event) {
        const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
        if (!step) return;
        event.preventDefault();
        const next = (i + step + tabs.length) % tabs.length;
        show(next);
        tabs[next].focus();
      });
    });
  });
});

/** Copy a code snippet. */
feature('copy code', function () {
  document.addEventListener('click', async function (event) {
    const button = event.target.closest('[data-copy-code]');
    if (!button) return;

    const code = button.closest('.code');
    const text = code ? code.querySelector('code').textContent : '';
    try {
      await navigator.clipboard.writeText(text);
      const was = button.textContent;
      button.textContent = 'Copied';
      window.setTimeout(function () { button.textContent = was; }, 1400);
    } catch (error) {
      // Clipboard refused; the snippet is on screen to select by hand.
    }
  });
});
