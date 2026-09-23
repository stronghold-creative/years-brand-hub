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

  // --------------------------------------------------------- dialogs

  let dialogReturn = null;

  function openDialog(el) {
    if (!el) return;
    dialogReturn = document.activeElement;
    el.hidden = false;
    const focusable = el.querySelector('input, button, select, textarea');
    if (focusable) focusable.focus();
    document.addEventListener('keydown', onDialogKey);
  }

  function closeDialogs() {
    document.querySelectorAll('.m3-scrim:not(.m3-scrim--drawer)').forEach((el) => { el.hidden = true; });
    document.removeEventListener('keydown', onDialogKey);
    if (dialogReturn && dialogReturn.focus) dialogReturn.focus();
  }

  function onDialogKey(event) {
    if (event.key === 'Escape') closeDialogs();
  }

  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-open-upload]')) { openDialog(document.getElementById('upload-dialog')); return; }
    if (event.target.closest('[data-close-dialog]')) { closeDialogs(); return; }
    // Clicking the backdrop itself, rather than the panel, closes it.
    if (event.target.classList.contains('m3-scrim') && !event.target.classList.contains('m3-scrim--drawer')) {
      closeDialogs();
    }
  });

  // ------------------------------------------------------ asset library

  const assetDialog = document.getElementById('asset-dialog');

  document.querySelectorAll('[data-asset]').forEach((tile) => {
    tile.addEventListener('click', () => {
      const asset = JSON.parse(tile.dataset.asset);

      document.getElementById('asset-title').textContent = asset.label || asset.file;

      const preview = document.getElementById('asset-preview');
      preview.innerHTML = '';
      if (asset.preview) {
        const img = document.createElement('img');
        img.src = asset.preview;
        img.alt = '';
        preview.append(img);
      } else {
        const badge = document.createElement('span');
        badge.className = 'm3-asset__ext';
        badge.textContent = asset.ext;
        preview.append(badge);
      }

      const facts = document.getElementById('asset-facts');
      facts.innerHTML = '';
      [
        ['File', asset.file],
        ['Size', asset.size],
        ['Uploaded', asset.uploaded],
        ['Downloads', String(asset.downloads)],
      ].forEach(([term, value]) => {
        const dt = document.createElement('dt');
        dt.textContent = term;
        const dd = document.createElement('dd');
        dd.textContent = value;
        facts.append(dt, dd);
      });

      document.getElementById('d-label').value = asset.label || '';
      document.getElementById('d-tags').value = asset.tags || '';
      document.getElementById('d-version').value = asset.version || '';

      document.getElementById('asset-save').action = '/admin/assets/' + asset.id + '/update';
      document.getElementById('asset-replace').action = '/admin/assets/' + asset.id + '/replace';
      document.getElementById('asset-delete').action = '/admin/assets/' + asset.id + '/delete';
      document.getElementById('asset-download').href = '/d/' + asset.id;

      openDialog(assetDialog);
    });
  });

  // Search and tag filtering, both client side over the rendered grid.
  const search = document.getElementById('asset-search');
  const grid = document.getElementById('asset-grid');
  if (search && grid) {
    const tiles = Array.from(grid.querySelectorAll('.m3-asset'));
    const none = document.getElementById('asset-none');
    let tag = '';

    function apply() {
      const term = search.value.trim().toLowerCase();
      let shown = 0;
      tiles.forEach((tile) => {
        const matchesTerm = !term || tile.dataset.name.includes(term);
        const tags = (tile.dataset.tags || '').split(',').map((t) => t.trim());
        const matchesTag = !tag || tags.includes(tag);
        const show = matchesTerm && matchesTag;
        tile.hidden = !show;
        if (show) shown += 1;
      });
      if (none) none.hidden = shown > 0;
    }

    search.addEventListener('input', apply);
    document.querySelectorAll('[data-tag]').forEach((chip) => {
      chip.addEventListener('click', () => {
        tag = chip.dataset.tag;
        document.querySelectorAll('[data-tag]').forEach((other) => other.classList.toggle('is-on', other === chip));
        apply();
      });
    });
  }

  // The file input inside a drop zone should show what was chosen.
  document.querySelectorAll('.m3-drop input[type="file"]').forEach((input) => {
    input.addEventListener('change', () => {
      const text = input.parentElement.querySelector('.m3-drop__text');
      if (text && input.files.length) text.textContent = input.files[0].name;
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
