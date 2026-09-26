/**
 * The asset picker, in one place.
 *
 * There were two of these, one in editor.js for block fields and one in
 * admin.js for the site logo and page covers. They had already drifted: only
 * the editor's could upload, only the admin's marked which image was currently
 * chosen, and the admin's leaked a keydown listener on every close that was not
 * an Escape. Two copies of a dialog will always end up unequal, so there is now
 * one, and both callers get everything.
 *
 * Requires UIMotion. Exposes window.AssetPicker.
 */
window.AssetPicker = (function () {
  'use strict';

  // The library, fetched once and shared by every caller on the page.
  let cache = null;

  function load(refresh) {
    if (cache && !refresh) {
      return Promise.resolve(cache);
    }
    return fetch('/admin/assets.json', { headers: { Accept: 'application/json' } })
      .then((response) => response.json())
      .then((data) => {
        cache = data.images || [];
        return cache;
      });
  }

  /** Adds one just-uploaded asset without re-reading the whole library. */
  function remember(asset) {
    cache = cache ? [asset].concat(cache) : [asset];
  }

  function matches(item, term) {
    return (item.label + ' ' + item.file + ' ' + (item.tags || '')).toLowerCase().includes(term);
  }

  /**
   * Opens the picker.
   *
   *   accept    'image' (default) or 'video'
   *   current   the id already chosen, marked in the grid
   *   onPick    given the chosen asset
   *   onClear   when present, a control to choose nothing is offered
   */
  function open(options) {
    const settings = options || {};
    const kind = settings.accept === 'video' ? 'video' : 'image';
    const noun = kind === 'video' ? 'video' : 'image';
    const current = settings.current == null ? '' : String(settings.current);

    const overlay = document.createElement('div');
    overlay.className = 'picker';
    overlay.innerHTML =
      '<div class="picker__panel" role="dialog" aria-modal="true" aria-label="Choose a file">' +
        '<header class="picker__head">' +
          '<input class="picker__search" type="search" placeholder="Search by name or tag" aria-label="Search the library">' +
          '<button type="button" class="picker__close m3-state" aria-label="Close">Close</button>' +
        '</header>' +
        '<div class="picker__grid" aria-live="polite">Loading</div>' +
        '<footer class="picker__foot">' +
          '<label class="picker__upload m3-state">' +
            '<input type="file" hidden accept="' + (kind === 'video' ? 'video/mp4' : 'image/*') + '">' +
            '<span>Upload a new ' + noun + '</span>' +
          '</label>' +
          (settings.onClear ? '<button type="button" class="picker__clear m3-state">Remove the ' + noun + '</button>' : '') +
          '<span class="picker__hint">It is added to the asset library and chosen here.</span>' +
        '</footer>' +
      '</div>';
    document.body.append(overlay);

    const panel = overlay.querySelector('.picker__panel');
    const grid = overlay.querySelector('.picker__grid');
    const search = overlay.querySelector('.picker__search');
    const hint = overlay.querySelector('.picker__hint');
    const file = overlay.querySelector('.picker__upload input');

    UIMotion.enterDialog(panel, overlay);

    // One close path, and it always removes the listener. The old admin copy
    // only removed it when Escape was what closed the dialog, so every close by
    // any other means left a handler behind acting on a detached panel.
    let closing = false;
    function close() {
      if (closing) return;
      closing = true;
      document.removeEventListener('keydown', onKey);
      return UIMotion.settled(UIMotion.exitDialog(panel, overlay)).then(() => overlay.remove());
    }

    function onKey(event) {
      if (event.key === 'Escape') {
        event.stopPropagation();
        close();
      }
    }

    overlay.querySelector('.picker__close').addEventListener('click', close);
    overlay.addEventListener('click', (event) => { if (event.target === overlay) close(); });
    document.addEventListener('keydown', onKey);

    const clear = overlay.querySelector('.picker__clear');
    if (clear) {
      clear.addEventListener('click', () => { settings.onClear(); close(); });
    }

    function draw(items) {
      grid.innerHTML = '';
      if (!items.length) {
        grid.textContent = 'No ' + noun + 's in the library yet. Upload one below.';
        return;
      }
      items.forEach((item) => {
        const tile = document.createElement('button');
        tile.type = 'button';
        tile.className = 'picker__tile m3-state' + (String(item.id) === current ? ' is-on' : '');
        tile.innerHTML = item.kind === 'video'
          ? '<span class="picker__thumb picker__thumb--file">MP4</span><span class="picker__label"></span>'
          : '<span class="picker__thumb"><img src="' + item.url + '" alt="" loading="lazy"></span>' +
            '<span class="picker__label"></span>';
        tile.querySelector('.picker__label').textContent = item.label || item.file;
        tile.addEventListener('click', () => { settings.onPick(item); close(); });
        grid.append(tile);
      });
      UIMotion.enterList(grid.querySelectorAll('.picker__tile'));
    }

    // An image field must not offer an MP4, and the video block must not offer
    // a PNG, so the field says what it wants and the library is filtered.
    const wanted = (list) => list.filter((item) => (item.kind || 'image') === kind);

    load().then((all) => {
      const items = wanted(all);
      draw(items);
      search.focus();
      search.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        draw(!term ? items : items.filter((item) => matches(item, term)));
      });
    }).catch(() => { grid.textContent = 'Could not load the asset library.'; });

    file.addEventListener('change', async () => {
      if (!file.files.length) return;
      const chosen = file.files[0];
      hint.textContent = 'Uploading ' + chosen.name + '…';

      const body = new FormData();
      body.append('_csrf', window.CSRF);
      body.append('file', chosen);
      try {
        const response = await fetch('/admin/assets/add', { method: 'POST', body: body });
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.error || 'Upload failed');
        // Into the cache before the caller is told, because choosing it reads
        // the library again to draw its thumbnail.
        remember(result.asset);
        settings.onPick(result.asset);
        close();
      } catch (error) {
        hint.textContent = error.message;
        file.value = '';
      }
    });

    return { close: close };
  }

  return { open: open, load: load, remember: remember };
})();
