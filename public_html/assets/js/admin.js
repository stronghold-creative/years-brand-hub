(function () {
  'use strict';

  // ------------------------------------------------------------- drawer

  const drawer = document.getElementById('drawer');
  const scrim = document.querySelector('.m3-scrim--drawer');
  let lastFocus = null;

  function openDrawer() {
    lastFocus = document.activeElement;
    document.body.classList.add('drawer-open');
    if (scrim) scrim.hidden = false;
    const first = drawer && drawer.querySelector('a, button');
    if (first) first.focus();
    document.addEventListener('keydown', onKey);
  }

  function closeDrawer() {
    document.body.classList.remove('drawer-open');
    if (scrim) scrim.hidden = true;
    document.removeEventListener('keydown', onKey);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  function onKey(event) {
    if (event.key === 'Escape') closeDrawer();
  }

  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-drawer-open]')) { openDrawer(); return; }
    if (event.target.closest('[data-drawer-close]')) { closeDrawer(); return; }
    // Following a link inside the drawer on a narrow screen should close it.
    if (event.target.closest('.m3-drawer a') && document.body.classList.contains('drawer-open')) {
      closeDrawer();
    }
  });

  // ------------------------------------------------------------- banners

  // Confirmations are transient. Errors stay until dismissed.
  document.querySelectorAll('.m3-banner').forEach((banner) => {
    const dismiss = document.createElement('button');
    dismiss.type = 'button';
    dismiss.className = 'm3-banner__dismiss';
    dismiss.setAttribute('aria-label', 'Dismiss');
    dismiss.textContent = '\u00d7';
    dismiss.addEventListener('click', () => banner.remove());
    banner.append(dismiss);

    if (!banner.classList.contains('m3-banner--error')) {
      setTimeout(() => {
        banner.classList.add('is-going');
        setTimeout(() => banner.remove(), 300);
      }, 6000);
    }
  });

  // Clear the notice from the address bar, so a refresh does not repeat it.
  if (window.location.search.includes('notice=')) {
    const url = new URL(window.location.href);
    url.searchParams.delete('notice');
    window.history.replaceState({}, '', url);
  }

  // --------------------------------------------------- destructive actions

  // Any form marked as destructive confirms before submitting, so the
  // behaviour cannot be forgotten on a new screen.
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm)) {
        event.preventDefault();
      }
    });
  });

  // ------------------------------------------------------- scroll shadow

  const bar = document.querySelector('.m3-page__bar');
  if (bar) {
    const main = document.querySelector('.m3-shell__main');
    const target = main || window;
    const onScroll = () => {
      const top = main ? main.scrollTop : window.scrollY;
      bar.classList.toggle('is-raised', top > 4);
    };
    target.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }
})();
