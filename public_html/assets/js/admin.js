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

  // --------------------------------------------------------- overflow menus

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-more]');
    document.querySelectorAll('.m3-more__menu').forEach((menu) => {
      const owner = menu.parentElement.querySelector('[data-more]');
      const isThis = trigger && owner === trigger;
      const opening = isThis && menu.hasAttribute('hidden');
      if (opening) { menu.removeAttribute('hidden'); } else { menu.setAttribute('hidden', ''); }
      if (owner) owner.setAttribute('aria-expanded', String(opening));

      // The row has to be raised too, not just the menu: each row is its own
      // stacking context, so a later row would otherwise paint over it.
      const row = menu.closest('.m3-list-item, tr');
      if (row) row.classList.toggle('has-menu', opening);
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.has-menu').forEach((row) => row.classList.remove('has-menu'));
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.m3-more__menu').forEach((menu) => menu.setAttribute('hidden', ''));
    document.querySelectorAll('.has-menu').forEach((row) => row.classList.remove('has-menu'));
  });

  // ------------------------------------------------------- section headers

  // The save control stays hidden until something has actually changed, so an
  // unchanged section header has nothing to click by accident.
  document.querySelectorAll('[data-autosave]').forEach((form) => {
    const initial = new FormData(form);
    const isDirty = () => {
      const now = new FormData(form);
      for (const [key, value] of now.entries()) {
        if (key === '_csrf') continue;
        if (String(initial.get(key)) !== String(value)) return true;
      }
      return false;
    };
    const update = () => form.classList.toggle('is-dirty', isDirty());
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
  });

  // ------------------------------------------------- appearance controls

  // A number box, a slider and any presets all drive the same value.
  document.querySelectorAll('.numfield').forEach((field) => {
    const input  = field.querySelector('.numfield__input');
    const slider = field.querySelector('.numfield__slider');
    const swatch = field.querySelector('.colourfield__swatch');
    if (!input) return;

    if (slider) {
      slider.addEventListener('input', () => { input.value = slider.value; });
      input.addEventListener('input', () => { slider.value = input.value; });
    }

    field.querySelectorAll('[data-preset]').forEach((chip) => {
      chip.addEventListener('click', () => {
        input.value = chip.dataset.preset;
        if (slider) slider.value = chip.dataset.preset;
        input.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });

    if (swatch) {
      swatch.addEventListener('input', () => { input.value = swatch.value.toUpperCase(); });
      input.addEventListener('input', () => {
        if (/^#[0-9a-f]{6}$/i.test(input.value)) swatch.value = input.value;
      });
    }
  });

  // ------------------------------------------------------------ logo picker

  // Reuses the same asset library dialog as the block editor.
  const logoMount = document.getElementById('logo-picker');
  if (logoMount) {
    const hidden = document.getElementById('site_logo');
    let images = null;

    const load = async () => {
      if (images) return images;
      const response = await fetch('/admin/assets.json', { headers: { Accept: 'application/json' } });
      images = (await response.json()).images || [];
      return images;
    };

    const paint = async (id) => {
      logoMount.innerHTML = '<span class="field__label">Logo, shown in the top bar</span>';
      const row = document.createElement('div');
      row.className = 'assetpick';

      const list = await load();
      const match = list.find((image) => String(image.id) === String(id));
      if (match) {
        const thumb = document.createElement('span');
        thumb.className = 'assetpick__preview';
        thumb.innerHTML = '<img src="' + match.url + '" alt="">';
        const name = document.createElement('span');
        name.className = 'assetpick__name';
        name.textContent = match.label || match.file;
        row.append(thumb, name);
      }

      const choose = document.createElement('button');
      choose.type = 'button';
      choose.className = 'assetpick__choose m3-state';
      choose.textContent = match ? 'Change' : 'Choose an image';
      choose.addEventListener('click', () => openLogoPicker(list));
      row.append(choose);

      if (match) {
        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'assetpick__clear m3-state';
        clear.textContent = 'Remove';
        clear.addEventListener('click', () => { hidden.value = '0'; paint('0'); });
        row.append(clear);
      }
      logoMount.append(row);
    };

    function openLogoPicker(list) {
      const overlay = document.createElement('div');
      overlay.className = 'picker';
      overlay.innerHTML =
        '<div class="picker__panel" role="dialog" aria-modal="true" aria-label="Choose a logo">' +
          '<header class="picker__head">' +
            '<input class="picker__search" type="search" placeholder="Search" aria-label="Search images">' +
            '<button type="button" class="picker__close m3-state">Close</button>' +
          '</header><div class="picker__grid"></div>' +
          '<p class="picker__hint">Upload the logo under Assets first. SVG works best.</p></div>';
      document.body.append(overlay);

      const grid = overlay.querySelector('.picker__grid');
      const close = () => overlay.remove();
      overlay.querySelector('.picker__close').addEventListener('click', close);
      overlay.addEventListener('click', (event) => { if (event.target === overlay) close(); });

      const draw = (items) => {
        grid.innerHTML = items.length ? '' : 'No images yet. Upload one under Assets.';
        items.forEach((image) => {
          const tile = document.createElement('button');
          tile.type = 'button';
          tile.className = 'picker__tile m3-state';
          tile.innerHTML = '<span class="picker__thumb"><img src="' + image.url + '" alt=""></span><span class="picker__label"></span>';
          tile.querySelector('.picker__label').textContent = image.label || image.file;
          tile.addEventListener('click', () => { hidden.value = String(image.id); paint(String(image.id)); close(); });
          grid.append(tile);
        });
      };
      draw(list);
      const search = overlay.querySelector('.picker__search');
      search.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        draw(!term ? list : list.filter((i) => (i.label + ' ' + i.file + ' ' + i.tags).toLowerCase().includes(term)));
      });
    }

    paint(logoMount.dataset.cover || '0');
  }

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
