(function () {
  'use strict';

  /**
   * Each feature runs inside its own guard, for the same reason site.js does:
   * this file is a series of blocks that run on load, and one throw stops every
   * block after it. The admin has more screens than the public site and most
   * blocks only apply to one of them, so a single missing element on a single
   * screen could take the drawer, the menus and the pickers down with it.
   */
  function feature(name, run) {
    try {
      run();
    } catch (error) {
      console.error('admin.js: ' + name + ' failed', error);
    }
  }

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
  feature('banners', function () {
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
  });

  // --------------------------------------------------- destructive actions

  // Any form marked as destructive confirms before submitting, so the
  // behaviour cannot be forgotten on a new screen.
  feature('confirmations', function () {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
          event.preventDefault();
        }
      });
    });
  });

  // --------------------------------------------------------- dialogs

  let dialogReturn = null;

  function openDialog(el) {
    if (!el) return;
    dialogReturn = document.activeElement;
    el.hidden = false;
    UIMotion.enterDialog(el.querySelector('.m3-dialog') || el, el);
    const focusable = el.querySelector('input, button, select, textarea');
    if (focusable) focusable.focus();
    document.addEventListener('keydown', onDialogKey);
  }

  function closeDialogs() {
    document.querySelectorAll('.m3-scrim:not(.m3-scrim--drawer)').forEach((el) => {
      if (el.hidden) return;
      // Hidden only once it has finished leaving, or it would vanish instantly.
      UIMotion.settled(UIMotion.exitDialog(el.querySelector('.m3-dialog') || el, el))
        .then(() => { el.hidden = true; });
    });
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

  feature('asset detail', function () {
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
      // Say what a delete would break, rather than finding out afterwards.
      const del = document.getElementById('asset-delete');
      del.action = '/admin/assets/' + asset.id + '/delete';
      const used = asset.usage || [];
      del.dataset.confirm = used.length
        ? 'This file is used on ' + used.length + (used.length === 1 ? ' page' : ' pages') +
          ' (' + used.slice(0, 4).join(', ') + (used.length > 4 ? ', and more' : '') +
          '). Deleting it will leave those pages without it. Continue?'
        : 'Remove this file? It is not used on any page.';

      const usage = document.getElementById('asset-usage');
      if (usage) {
        usage.textContent = used.length
          ? 'Used on: ' + used.join(', ')
          : 'Not used on any page.';
        usage.className = 'm3-usage' + (used.length ? ' is-used' : '');
      }
      document.getElementById('asset-download').href = '/d/' + asset.id;

      openDialog(assetDialog);
    });
  });
  });

  // Search and tag filtering, both client side over the rendered grid.
  feature('asset filtering', function () {
    const search = document.getElementById('asset-search');
    const grid = document.getElementById('asset-grid');
    if (!search || !grid) return;

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
        // Clicking the chip that is already on clears the filter, rather than
        // leaving no way back to everything.
        const wasOn = chip.classList.contains('is-on');
        tag = wasOn ? '' : chip.dataset.tag;
        document.querySelectorAll('[data-tag]').forEach((other) => {
          other.classList.toggle('is-on', !wasOn && other === chip);
        });
        apply();
      });
    });
  });

  // ------------------------------------------------------------ page filter

  /**
   * Finds a page in the list, by name and by state.
   *
   * Client side, over the rows already rendered, exactly as the asset grid
   * does: there are tens of pages, not thousands, and a round trip to filter
   * them would be slower than not filtering at all. A section with nothing left
   * in it hides itself, so the list does not become a column of empty headings.
   */
  feature('page filter', function () {
    const search = document.getElementById('page-search');
    const scope = document.getElementById('page-sections');
    if (!search || !scope) return;

    const rows = Array.from(scope.querySelectorAll('.m3-list-item[data-page-name]'));
    const sections = Array.from(scope.querySelectorAll('.m3-section'));
    const none = document.getElementById('page-none');
    const count = document.getElementById('page-count');
    let state = '';

    function apply() {
      const term = search.value.trim().toLowerCase();
      let shown = 0;

      rows.forEach((row) => {
        const matchesTerm = !term || row.dataset.pageName.includes(term);
        const matchesState = !state || row.dataset.pageState === state;
        const show = matchesTerm && matchesState;
        row.hidden = !show;
        if (show) shown += 1;
      });

      // A heading with no rows under it is noise, so it goes too.
      sections.forEach((section) => {
        const any = Array.from(section.querySelectorAll('.m3-list-item[data-page-name]'))
          .some((row) => !row.hidden);
        section.hidden = !any;
      });

      if (none) none.hidden = shown > 0;
      if (count) {
        count.textContent = (term || state)
          ? shown + (shown === 1 ? ' page' : ' pages')
          : '';
      }
    }

    search.addEventListener('input', apply);
    // Escape clears, rather than leaving a filtered list with no obvious way out.
    search.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && search.value !== '') {
        event.stopPropagation();
        search.value = '';
        apply();
      }
    });

    document.querySelectorAll('[data-page-state]').forEach((chip) => {
      if (chip.tagName !== 'BUTTON') return;
      chip.addEventListener('click', () => {
        state = chip.dataset.pageState;
        document.querySelectorAll('button[data-page-state]').forEach((other) => {
          other.classList.toggle('is-on', other === chip);
        });
        apply();
      });
    });
  });

  // The file input inside a drop zone should show what was chosen.
  feature('drop zones', function () {
    document.querySelectorAll('.m3-drop input[type="file"]').forEach((input) => {
      input.addEventListener('change', () => {
        const text = input.parentElement.querySelector('.m3-drop__text');
        if (text && input.files.length) text.textContent = input.files[0].name;
      });
    });
  });

  // --------------------------------------------------------- overflow menus

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-more]');
    document.querySelectorAll('.m3-more__menu').forEach((menu) => {
      const owner = menu.parentElement.querySelector('[data-more]');
      const isThis = trigger && owner === trigger;
      const opening = isThis && menu.hasAttribute('hidden');
      if (opening) {
        menu.removeAttribute('hidden');
        UIMotion.animate(menu,
          { opacity: [0, 1], y: [-6, 0], scale: [0.97, 1] },
          { duration: UIMotion.durations.base, ease: UIMotion.easings.expo });
      } else if (!menu.hasAttribute('hidden')) {
        UIMotion.settled(UIMotion.exit(menu, { duration: UIMotion.durations.swift }))
          .then(() => menu.setAttribute('hidden', ''));
      }
      if (owner) owner.setAttribute('aria-expanded', String(opening));

      // The row has to be raised too, not just the menu: each row is its own
      // stacking context, so a later row would otherwise paint over it.
      const row = menu.closest('.m3-list-item, tr');
      if (row) row.classList.toggle('has-menu', opening);
    });
  });

  // One Escape handler. There were two, the first a subset of the second.
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.m3-more__menu').forEach((menu) => menu.setAttribute('hidden', ''));
    document.querySelectorAll('.has-menu').forEach((row) => row.classList.remove('has-menu'));
  });

  // The drawer uses the shared slide, so it matches the rail on the public
  // site rather than keeping its own timing. It reuses the drawer already
  // found above rather than declaring another.
  document.addEventListener('click', (event) => {
    if (!drawer) return;
    if (event.target.closest('[data-open-drawer]')) UIMotion.slideIn(drawer, 'left');
    if (event.target.closest('[data-close-drawer]')) UIMotion.slideOut(drawer, 'left');
  });

  // ------------------------------------------------------------ page covers

  // The shared picker from picker.js, and the choice saves straight away rather
  // than sending the person into the editor to change one image.
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-cover-for]');
    if (!button) return;

    const save = async (chosen, asset) => {
      const body = new FormData();
      body.append('_csrf', window.CSRF);
      body.append('asset', chosen || '0');

      const saved = await fetch('/admin/pages/' + button.dataset.coverFor + '/cover', { method: 'POST', body: body });
      if (!saved.ok) {
        window.alert('That cover could not be saved. Try again.');
        return;
      }
      button.dataset.coverId = chosen || '0';
      button.innerHTML = asset
        ? '<img src="' + asset.url + '" alt="">'
        : '<span class="m3-cover__empty" aria-hidden="true">+</span>';
      UIMotion.enter(button);
    };

    AssetPicker.open({
      accept: 'image',
      current: button.dataset.coverId || '0',
      onPick: (asset) => save(String(asset.id), asset),
      onClear: () => save('', null),
    });
  });

  // ------------------------------------------------------- section headers

  // The save control stays hidden until something has actually changed, so an
  // unchanged section header has nothing to click by accident.
  feature('dirty tracking', function () {
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
  });

  // ------------------------------------------------- appearance controls

  // A number box, a slider and any presets all drive the same value.
  feature('appearance controls', function () {
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
  });

  // ------------------------------------------------------- appearance screen

  /**
   * The live preview, the per-field resets and the contrast readout.
   *
   * Appearance has twenty-six numbers across seven groups and used to give no
   * sign of what any of them did: you saved, opened the public site in another
   * tab, came back and guessed again. The preview is a real page rendered by
   * the real block renderers in an iframe, and the values are posted into it as
   * they change, so nothing here is a second implementation of the stylesheet.
   */
  feature('appearance screen', function () {
    const screen = document.getElementById('styles');
    if (!screen) return;

    const wiring = JSON.parse(screen.dataset.wiring || '{}');
    const frame = document.getElementById('style-preview');
    const state = document.getElementById('preview-state');
    let ready = false;

    const valueOf = (key) => {
      const input = document.getElementById('s-' + key);
      return input ? input.value.trim() : '';
    };

    /**
     * Rebuilds the custom properties exactly as style_css() does on the server,
     * including the heading ladder derived from the body size and the scale.
     * The derivation is duplicated here on purpose: the alternative is a round
     * trip for every keystroke on a slider.
     */
    function currentCss() {
      const css = {};
      Object.keys(wiring).forEach((key) => {
        const field = wiring[key];
        if (!field.css) return;
        const value = valueOf(key);
        if (value === '') return;
        css[field.css] = value + (field.unit || '');
      });

      const base = parseFloat(valueOf('text_size'));
      const scale = parseFloat(valueOf('type_scale'));
      if (base > 0 && scale > 1) {
        [[1, 3], [2, 2], [3, 1]].forEach(([level, steps]) => {
          css['--s-h' + level] = +(base * Math.pow(scale, steps)).toFixed(3) + 'rem';
        });
      }
      return css;
    }

    function currentBody() {
      const body = {};
      Object.keys(wiring).forEach((key) => {
        if (wiring[key].body) body[wiring[key].body] = valueOf(key);
      });
      return body;
    }

    function push() {
      if (!ready || !frame || !frame.contentWindow) return;
      frame.contentWindow.postMessage(
        { from: 'styles', css: currentCss(), body: currentBody() },
        window.location.origin
      );
    }

    window.addEventListener('message', (event) => {
      if (event.origin !== window.location.origin) return;
      if (!event.data || event.data.from !== 'style-preview') return;
      ready = true;
      push();
    });

    /* ------------------------------------------------------------ contrast */

    // Mirrors colour_luminance() and contrast_ratio() in app/lib/blocks.php, so
    // the number here is the number the guidelines will publish.
    function luminance(hex) {
      const clean = String(hex).replace('#', '');
      if (!/^[0-9a-f]{6}$/i.test(clean)) return 0;
      const channel = (pair) => {
        const value = parseInt(pair, 16) / 255;
        return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
      };
      return 0.2126 * channel(clean.slice(0, 2))
           + 0.7152 * channel(clean.slice(2, 4))
           + 0.0722 * channel(clean.slice(4, 6));
    }

    function ratio(foreground, background) {
      const a = luminance(foreground);
      const b = luminance(background);
      return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
    }

    function verdict(value) {
      if (value >= 7) return { level: 'AAA', pass: true };
      if (value >= 4.5) return { level: 'AA', pass: true };
      if (value >= 3) return { level: 'AA large', pass: true };
      return { level: 'Fails', pass: false };
    }

    function checkContrast() {
      document.querySelectorAll('.m3-contrast__row').forEach((row) => {
        const read = (name) => name.charAt(0) === '#' ? name : valueOf(name);
        const value = ratio(read(row.dataset.fg), read(row.dataset.bg));
        const result = verdict(value);
        row.querySelector('.m3-contrast__ratio').textContent = value.toFixed(2) + ':1';
        row.querySelector('.m3-contrast__verdict').textContent = result.level;
        row.classList.toggle('is-failing', !result.pass);
      });
    }

    /* --------------------------------------------------------------- reset */

    function refreshResets() {
      document.querySelectorAll('[data-reset]').forEach((button) => {
        const holder = button.closest('[data-default]');
        if (!holder) return;
        const now = valueOf(button.dataset.reset);
        // Colours compare without case, numbers as numbers, so 6 matches 6.00.
        const target = holder.dataset.default;
        const same = now.toUpperCase() === String(target).toUpperCase()
          || (now !== '' && parseFloat(now) === parseFloat(target));
        button.hidden = same;
      });
    }

    document.querySelectorAll('[data-reset]').forEach((button) => {
      button.addEventListener('click', () => {
        const key = button.dataset.reset;
        const holder = button.closest('[data-default]');
        const input = document.getElementById('s-' + key);
        if (!input || !holder) return;
        input.value = holder.dataset.default;
        // Tells the slider, the swatch and everything below that it changed.
        input.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });

    /* ------------------------------------------------------------ wiring up */

    const onChange = () => { push(); checkContrast(); refreshResets(); };
    screen.addEventListener('input', onChange);
    screen.addEventListener('change', onChange);

    checkContrast();
    refreshResets();
  });

  // A destructive submit button, rather than a whole destructive form.
  feature('confirm on click', function () {
    document.querySelectorAll('[data-confirm-click]').forEach((button) => {
      button.addEventListener('click', (event) => {
        if (!window.confirm(button.dataset.confirmClick)) event.preventDefault();
      });
    });
  });

  // ------------------------------------------------------------ logo picker

  // The shared picker from picker.js, the same dialog the block editor uses.
  // One picker, used by every asset setting. It was written for the logo and
  // then wanted again for the site icon; a second copy is how two pickers end
  // up behaving differently.
  feature('asset settings', function () {
    document.querySelectorAll('[data-asset-setting]').forEach(assetSetting);
  });

  function assetSetting(logoMount) {
    const hidden = document.getElementById(logoMount.dataset.assetSetting);
    if (!hidden) return;

    const paint = async (id) => {
      logoMount.innerHTML = '<span class="field__label"></span>';
      logoMount.querySelector('.field__label').textContent = logoMount.dataset.label || 'Image';
      const row = document.createElement('div');
      row.className = 'assetpick';

      const list = await AssetPicker.load();
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
      choose.addEventListener('click', () => {
        AssetPicker.open({
          accept: 'image',
          current: hidden.value,
          onPick: (asset) => { hidden.value = String(asset.id); paint(String(asset.id)); },
          onClear: () => { hidden.value = '0'; paint('0'); },
        });
      });
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

    paint(logoMount.dataset.cover || '0');
  }

  // ------------------------------------------------------- scroll shadow

  feature('scroll shadow', function () {
    const bar = document.querySelector('.m3-page__bar');
    if (!bar) return;
    const main = document.querySelector('.m3-shell__main');
    const target = main || window;
    const onScroll = () => {
      const top = main ? main.scrollTop : window.scrollY;
      bar.classList.toggle('is-raised', top > 4);
    };
    target.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  });
})();
