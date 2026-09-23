(function () {
  'use strict';

  const root = document.getElementById('editor');
  if (!root) return;

  const schema         = JSON.parse(root.dataset.schema);
  const defaults       = JSON.parse(root.dataset.defaults);
  const settingsSchema = JSON.parse(root.dataset.settings);
  const settingsBlank  = JSON.parse(root.dataset.settingsDefaults);
  const optionSchema   = JSON.parse(root.dataset.options);
  const optionDefaults = JSON.parse(root.dataset.optionsDefaults);
  const pageId         = root.dataset.pageId;

  let blocks = JSON.parse(root.dataset.blocks);

  const outline   = document.getElementById('outline-list');
  const inspector = document.getElementById('inspector');
  const saveState = document.getElementById('save-state');
  const preview   = document.getElementById('preview');

  // Which block the inspector is showing. null means the page itself.
  let selected = null;
  let previewReady = false;

  const clone = (value) => JSON.parse(JSON.stringify(value));


  // ---------------------------------------------------------- image picker

  let imageCache = null;

  async function loadImages() {
    if (imageCache) return imageCache;
    const response = await fetch('/admin/assets.json', { headers: { 'Accept': 'application/json' } });
    const data = await response.json();
    imageCache = data.images || [];
    return imageCache;
  }

  function openPicker(onPick) {
    const overlay = document.createElement('div');
    overlay.className = 'picker';
    overlay.innerHTML =
      '<div class="picker__panel" role="dialog" aria-modal="true" aria-label="Choose an image">' +
        '<header class="picker__head">' +
          '<input class="picker__search" type="search" placeholder="Search by name or tag" aria-label="Search images">' +
          '<button type="button" class="picker__close m3-state" aria-label="Close">Close</button>' +
        '</header>' +
        '<div class="picker__grid" aria-live="polite">Loading</div>' +
        '<p class="picker__hint">Images come from the asset library. Upload new ones under Assets.</p>' +
      '</div>';
    document.body.append(overlay);

    const grid = overlay.querySelector('.picker__grid');
    const search = overlay.querySelector('.picker__search');
    const close = () => { overlay.remove(); document.removeEventListener('keydown', onKey); };
    const onKey = (event) => { if (event.key === 'Escape') close(); };

    overlay.querySelector('.picker__close').addEventListener('click', close);
    overlay.addEventListener('click', (event) => { if (event.target === overlay) close(); });
    document.addEventListener('keydown', onKey);

    function draw(images) {
      grid.innerHTML = '';
      if (!images.length) {
        grid.textContent = 'No images yet. Upload some under Assets first.';
        return;
      }
      images.forEach((image) => {
        const tile = document.createElement('button');
        tile.type = 'button';
        tile.className = 'picker__tile m3-state';
        tile.innerHTML =
          '<span class="picker__thumb"><img src="' + image.url + '" alt="" loading="lazy"></span>' +
          '<span class="picker__label"></span>';
        tile.querySelector('.picker__label').textContent = image.label || image.file;
        tile.addEventListener('click', () => { onPick(image); close(); });
        grid.append(tile);
      });
    }

    loadImages().then((images) => {
      draw(images);
      search.focus();
      search.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        draw(!term ? images : images.filter((image) =>
          (image.label + ' ' + image.file + ' ' + image.tags).toLowerCase().includes(term)
        ));
      });
    }).catch(() => { grid.textContent = 'Could not load the image library.'; });
  }

  function assetField(def, value, onInput) {
    const wrap = document.createElement('div');
    wrap.className = 'field field--asset';
    const label = document.createElement('span');
    label.className = 'field__label';
    label.textContent = def.label;
    wrap.append(label);

    const shown = document.createElement('div');
    shown.className = 'assetpick';
    wrap.append(shown);

    function paint(id) {
      shown.innerHTML = '';
      const choose = button(id ? 'Change' : 'Choose an image', () => {
        openPicker((image) => { onInput(String(image.id)); paint(String(image.id)); });
      });
      choose.className = 'assetpick__choose m3-state';

      if (id) {
        loadImages().then((images) => {
          const match = images.find((image) => String(image.id) === String(id));
          const preview = document.createElement('span');
          preview.className = 'assetpick__preview';
          if (match) {
            preview.innerHTML = '<img src="' + match.url + '" alt="">';
            const name = document.createElement('span');
            name.className = 'assetpick__name';
            name.textContent = match.label || match.file;
            shown.prepend(preview, name);
          } else {
            preview.textContent = 'That image was removed from the library.';
            preview.classList.add('is-missing');
            shown.prepend(preview);
          }
        });
        const clear = button('Remove', () => { onInput(''); paint(''); });
        clear.className = 'assetpick__clear m3-state';
        shown.append(choose, clear);
      } else {
        shown.append(choose);
      }
    }

    paint(value || '');
    return wrap;
  }

  // ------------------------------------------------------------ field markup

  function field(def, value, onInput, dense) {
    if (def.type === 'asset') {
      return assetField(def, value, onInput);
    }

    // Material outlined text field: the control comes first, the label second,
    // so the label can float using the sibling selector in CSS.
    const wrap = document.createElement('div');
    wrap.className = 'm3-field' + (dense ? ' m3-field--dense' : '');
    const id = 'f' + Math.random().toString(36).slice(2, 9);
    let input;

    if (def.type === 'longtext' || def.type === 'textarea') {
      input = document.createElement('textarea');
      input.className = 'm3-field__textarea';
      input.rows = def.type === 'longtext' ? 5 : 2;
      input.placeholder = ' ';
      input.value = value || '';
    } else if (def.type === 'select') {
      wrap.classList.add('m3-field--select');
      input = document.createElement('select');
      input.className = 'm3-field__select';
      Object.entries(def.options).forEach(([key, label]) => {
        input.append(new Option(label, key, false, String(key) === String(value)));
      });
    } else if (def.type === 'colour') {
      input = document.createElement('input');
      input.type = 'text';
      input.className = 'm3-field__input';
      input.placeholder = ' ';
      input.value = value || '#7257B7';

      const chip = document.createElement('input');
      chip.type = 'color';
      chip.className = 'field__chip';
      chip.value = /^#[0-9a-f]{6}$/i.test(input.value) ? input.value : '#7257B7';
      chip.addEventListener('input', () => { input.value = chip.value; onInput(chip.value); });
      input.addEventListener('input', () => {
        if (/^#[0-9a-f]{6}$/i.test(input.value)) chip.value = input.value;
        onInput(input.value);
      });

      input.id = id;
      const label = document.createElement('label');
      label.className = 'm3-field__label';
      label.htmlFor = id;
      label.textContent = def.label + (def.optional ? ' (optional)' : '');
      wrap.classList.add('m3-field--colour');
      wrap.append(input, label, chip);
      return wrap;
    } else {
      input = document.createElement('input');
      input.type = 'text';
      input.className = 'm3-field__input';
      input.placeholder = ' ';
      input.value = value || '';
    }

    input.id = id;
    input.addEventListener('input', () => onInput(input.value));
    input.addEventListener('change', () => onInput(input.value));

    const label = document.createElement('label');
    label.className = 'm3-field__label';
    label.htmlFor = id;
    label.textContent = def.label + (def.optional ? ' (optional)' : '');

    wrap.append(input, label);
    return wrap;
  }

  function repeater(def, rows, onChange) {
    const wrap = document.createElement('div');
    wrap.className = 'repeater';

    const head = document.createElement('div');
    head.className = 'repeater__head';
    head.textContent = def.label;
    wrap.append(head);

    rows.forEach((row, index) => {
      const item = document.createElement('div');
      item.className = 'repeater__item';

      const bar = document.createElement('div');
      bar.className = 'repeater__bar';
      bar.innerHTML = '<span>' + (def.item || 'Item') + ' ' + (index + 1) + '</span>';

      const up = button('Move up', () => { move(rows, index, index - 1); onChange(rows); });
      const down = button('Move down', () => { move(rows, index, index + 1); onChange(rows); });
      const remove = button('Remove', () => { rows.splice(index, 1); onChange(rows); });
      remove.className = 'danger';
      bar.append(up, down, remove);
      item.append(bar);

      Object.entries(def.fields).forEach(([key, fieldDef]) => {
        item.append(field(fieldDef, row[key], (value) => { row[key] = value; onChange(rows, true); }));
      });

      wrap.append(item);
    });

    const add = button('Add ' + (def.item || 'item').toLowerCase(), () => {
      const blank = {};
      Object.keys(def.fields).forEach((key) => { blank[key] = ''; });
      rows.push(blank);
      onChange(rows);
    });
    add.className = 'repeater__add';
    wrap.append(add);
    return wrap;
  }

  function button(label, handler) {
    const el = document.createElement('button');
    el.type = 'button';
    el.textContent = label;
    el.addEventListener('click', handler);
    return el;
  }

  function move(arr, from, to) {
    if (to < 0 || to >= arr.length) return;
    arr.splice(to, 0, arr.splice(from, 1)[0]);
  }

  // ============================================================== helpers

  const copy = (value) => JSON.parse(JSON.stringify(value));

  /** A short description of a block, for the outline. */
  function describe(block) {
    const def = schema[block.type];
    for (const [key, fieldDef] of Object.entries(def.fields)) {
      const value = block.data[key];
      if (fieldDef.type === 'repeater' && Array.isArray(value) && value.length) {
        const noun = (fieldDef.item || 'item').toLowerCase();
        return value.length + ' ' + noun + (value.length === 1 ? '' : 's');
      }
      if (typeof value === 'string' && value.trim() && fieldDef.type !== 'select' && fieldDef.type !== 'asset') {
        return value.length > 42 ? value.slice(0, 42) + '\u2026' : value;
      }
    }
    return '';
  }

  function ensure(block) {
    if (!block.settings) block.settings = copy(settingsBlank);
    if (!block.options)  block.options  = copy(optionDefaults[block.type] || {});
    return block;
  }

  // ============================================================== outline

  function renderOutline() {
    outline.innerHTML = '';

    blocks.forEach((block, index) => {
      const row = document.createElement('li');
      row.className = 'outline__row' + (index === selected ? ' is-selected' : '');
      row.draggable = true;
      row.dataset.index = index;

      const grip = document.createElement('span');
      grip.className = 'outline__grip';
      grip.setAttribute('aria-hidden', 'true');

      const text = document.createElement('button');
      text.type = 'button';
      text.className = 'outline__text';
      text.innerHTML = '<span class="outline__type"></span><span class="outline__desc"></span>';
      text.querySelector('.outline__type').textContent = schema[block.type].label;
      text.querySelector('.outline__desc').textContent = describe(block);
      text.addEventListener('click', () => select(index));

      const acts = document.createElement('span');
      acts.className = 'outline__acts';
      acts.append(
        miniButton('Duplicate', '&#10697;', () => {
          blocks.splice(index + 1, 0, copy(ensure(blocks[index])));
          select(index + 1);
          queueSave();
        }),
        miniButton('Delete', '&times;', () => {
          if (!window.confirm('Delete this ' + schema[block.type].label.toLowerCase() + ' block?')) return;
          blocks.splice(index, 1);
          selected = null;
          renderAll();
          queueSave();
        }, 'is-danger')
      );

      row.append(grip, text, acts);
      outline.append(row);
    });

    const count = document.getElementById('outline-count');
    if (count) count.textContent = blocks.length + (blocks.length === 1 ? ' block' : ' blocks');

    if (!blocks.length) {
      const empty = document.createElement('li');
      empty.className = 'outline__empty';
      empty.textContent = 'No blocks yet.';
      outline.append(empty);
    }
  }

  function miniButton(label, glyph, handler, className) {
    const el = document.createElement('button');
    el.type = 'button';
    el.className = 'outline__act ' + (className || '');
    el.innerHTML = glyph;
    el.title = label;
    el.setAttribute('aria-label', label);
    el.addEventListener('click', (event) => { event.stopPropagation(); handler(); });
    return el;
  }

  // Drag to reorder, from the outline only.
  let dragFrom = null;
  outline.addEventListener('dragstart', (event) => {
    const row = event.target.closest('.outline__row');
    if (!row) return;
    dragFrom = Number(row.dataset.index);
    row.classList.add('is-dragging');
  });
  outline.addEventListener('dragover', (event) => {
    event.preventDefault();
    const row = event.target.closest('.outline__row');
    outline.querySelectorAll('.is-over').forEach((el) => el.classList.remove('is-over'));
    if (row) row.classList.add('is-over');
  });
  outline.addEventListener('drop', (event) => {
    event.preventDefault();
    const row = event.target.closest('.outline__row');
    if (row && dragFrom !== null) {
      const to = Number(row.dataset.index);
      blocks.splice(to, 0, blocks.splice(dragFrom, 1)[0]);
      select(to);
      queueSave();
    }
    dragFrom = null;
  });
  outline.addEventListener('dragend', () => {
    outline.querySelectorAll('.is-dragging, .is-over').forEach((el) => el.classList.remove('is-dragging', 'is-over'));
  });

  // ============================================================ inspector

  function renderInspector() {
    inspector.innerHTML = '';
    // Retriggers the settle animation on the new content. Removing the class
    // and forcing a reflow is the only reliable way to restart a CSS animation.
    inspector.classList.remove('is-swapping');
    void inspector.offsetWidth;
    inspector.classList.add('is-swapping');

    if (selected === null || !blocks[selected]) {
      const template = document.getElementById('page-settings-template');
      inspector.append(template.content.cloneNode(true));
      bindPageFields();
      return;
    }

    const block = ensure(blocks[selected]);
    const def = schema[block.type];

    const head = document.createElement('div');
    head.className = 'insp__head';
    head.innerHTML = '<span class="insp__kind"></span>';
    head.querySelector('.insp__kind').textContent = def.label;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'insp__close m3-state';
    close.innerHTML = '&times;';
    close.title = 'Show page settings';
    close.setAttribute('aria-label', 'Show page settings');
    close.addEventListener('click', () => select(null));
    head.append(close);
    inspector.append(head);

    // Content
    const content = section('Content');
    if (def.hint) {
      const hint = document.createElement('p');
      hint.className = 'insp__hint';
      hint.textContent = def.hint;
      content.append(hint);
    }
    Object.entries(def.fields).forEach(([key, fieldDef]) => {
      if (fieldDef.type === 'repeater') {
        if (!Array.isArray(block.data[key])) block.data[key] = [];
        content.append(repeater(fieldDef, block.data[key], (rows, inPlace) => {
          block.data[key] = rows;
          if (!inPlace) renderInspector();
          renderOutline();
          queueSave();
        }));
      } else {
        content.append(field(fieldDef, block.data[key], (value) => {
          block.data[key] = value;
          renderOutline();
          queueSave();
        }));
      }
    });
    inspector.append(content);

    // Appearance, this block's own options.
    const own = optionSchema[block.type] || {};
    if (Object.keys(own).length) {
      const look = section('Appearance');
      Object.entries(own).forEach(([key, fieldDef]) => {
        look.append(field(
          { type: 'select', label: fieldDef.label, options: fieldDef.options },
          block.options[key],
          (value) => { block.options[key] = value; queueSave(); },
          true
        ));
      });
      inspector.append(look);
    }

    // Placement, shared by every block.
    const place = section('Placement');
    Object.entries(settingsSchema).forEach(([key, fieldDef]) => {
      place.append(field(fieldDef, block.settings[key], (value) => {
        block.settings[key] = value;
        queueSave();
      }, true));
    });
    const note = document.createElement('p');
    note.className = 'insp__note';
    note.textContent = 'Anything set to "Site default" follows Appearance in the admin.';
    place.append(note);
    inspector.append(place);
  }

  function section(title) {
    const el = document.createElement('section');
    el.className = 'insp__section';
    const heading = document.createElement('h2');
    heading.className = 'insp__title';
    heading.textContent = title;
    el.append(heading);
    return el;
  }

  // ============================================================ selection

  function select(index) {
    selected = index;
    renderOutline();
    renderInspector();
    if (index !== null) tell({ action: 'select', index: index });
  }

  function tell(message) {
    if (!preview || !preview.contentWindow) return;
    preview.contentWindow.postMessage(Object.assign({ from: 'editor' }, message), window.location.origin);
  }

  function sendLabels() {
    tell({ action: 'labels', labels: blocks.map((block) => schema[block.type].label) });
  }

  window.addEventListener('message', (event) => {
    if (event.origin !== window.location.origin || !event.data || event.data.from !== 'preview') return;

    if (event.data.action === 'ready') {
      previewReady = true;
      sendLabels();
      if (selected !== null) tell({ action: 'select', index: selected });
    }
    if (event.data.action === 'select') {
      selected = event.data.index;
      renderOutline();
      renderInspector();
    }
    if (event.data.action === 'insert') {
      openBlockMenu(event.data.index);
    }
  });

  // ========================================================== adding blocks

  /**
   * The block chooser. Always a centred dialog rather than a popover, because
   * it is opened from three places, the outline, the canvas and the keyboard,
   * and a popover anchored to any of them either clips or lands off screen.
   */
  function openBlockMenu(index) {
    closeBlockMenu();

    const scrim = document.createElement('div');
    scrim.className = 'blockmenu__scrim';

    const menu = document.createElement('div');
    menu.className = 'blockmenu';
    menu.setAttribute('role', 'dialog');
    menu.setAttribute('aria-modal', 'true');
    menu.setAttribute('aria-label', 'Add a block');
    menu.innerHTML =
      '<header class="blockmenu__head">' +
        '<p class="blockmenu__title">Add a block</p>' +
        '<input class="blockmenu__search" type="search" placeholder="Search blocks" aria-label="Search blocks">' +
      '</header><div class="blockmenu__list"></div>';

    const list = menu.querySelector('.blockmenu__list');

    function draw(term) {
      list.innerHTML = '';
      const entries = Object.entries(schema).filter(([, def]) =>
        !term || (def.label + ' ' + (def.hint || '')).toLowerCase().includes(term));

      if (!entries.length) {
        const none = document.createElement('p');
        none.className = 'blockmenu__none';
        none.textContent = 'Nothing matches that.';
        list.append(none);
        return;
      }

      entries.forEach(([type, def]) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'blockmenu__item';
        item.innerHTML = '<span class="blockmenu__label"></span><span class="blockmenu__hint"></span>';
        item.querySelector('.blockmenu__label').textContent = def.label;
        item.querySelector('.blockmenu__hint').textContent = def.hint || '';
        item.addEventListener('click', () => {
          blocks.splice(index, 0, {
            type: type,
            data: copy(defaults[type]),
            settings: copy(settingsBlank),
            options: copy(optionDefaults[type] || {}),
          });
          closeBlockMenu();
          select(index);
          queueSave();
        });
        list.append(item);
      });
    }

    draw('');
    scrim.append(menu);
    document.body.append(scrim);

    const search = menu.querySelector('.blockmenu__search');
    search.focus();
    search.addEventListener('input', () => draw(search.value.trim().toLowerCase()));

    scrim.addEventListener('click', (event) => { if (event.target === scrim) closeBlockMenu(); });
    document.addEventListener('keydown', onMenuKey);
  }

  function closeBlockMenu() {
    document.querySelectorAll('.blockmenu__scrim').forEach((el) => el.remove());
    document.removeEventListener('keydown', onMenuKey);
  }

  function onMenuKey(event) {
    if (event.key === 'Escape') { event.stopPropagation(); closeBlockMenu(); }
  }

  const outlineAdd = document.getElementById('outline-add');
  if (outlineAdd) {
    outlineAdd.addEventListener('click', () => openBlockMenu(blocks.length));
  }

  function renderAll() {
    renderOutline();
    renderInspector();
  }

  // =============================================================== saving

  let timer = null;
  let saving = false;
  let coverId = '';

  function bindPageFields() {
    const mount = document.getElementById('f-cover');
    if (mount) {
      coverId = mount.dataset.cover && mount.dataset.cover !== '0' ? mount.dataset.cover : coverId;
      mount.append(assetField(
        { label: 'Cover image, shown on the index', type: 'asset' },
        coverId,
        (value) => { coverId = value; queueSave(); }
      ));
    }
    Object.keys(PAGE_FIELDS).forEach((id) => {
      const el = document.getElementById(id);
      if (!el) return;
      const update = () => { readPageFields(); queueSave(); };
      el.addEventListener('input', update);
      el.addEventListener('change', update);
    });
    const title = document.getElementById('f-title');
    const name = document.getElementById('editor-title');
    if (title && name) {
      title.addEventListener('input', () => { name.textContent = title.value || 'Untitled page'; });
    }
  }

  const PAGE_FIELDS = {
    'f-title': 'title', 'f-section': 'section', 'f-summary': 'summary',
    'f-status': 'status', 'f-home': 'home',
  };

  function readPageFields() {
    Object.entries(PAGE_FIELDS).forEach(([id, key]) => {
      const el = document.getElementById(id);
      if (!el) return;
      pageState[key] = el.type === 'checkbox' ? el.checked : el.value;
    });
  }

  // The page fields only exist in the DOM while the inspector shows the page,
  // so their values are held here and updated as they change.
  const pageState = {
    title:   root.dataset.pageTitle || '',
    section: root.dataset.pageSection || '',
    summary: root.dataset.pageSummary || '',
    status:  root.dataset.pageStatus || 'draft',
    home:    root.dataset.pageHome !== '0',
  };

  function queueSave() {
    saveState.textContent = 'Saving';
    saveState.dataset.state = 'pending';
    clearTimeout(timer);
    timer = setTimeout(save, 700);
  }

  async function save() {
    if (saving) { queueSave(); return; }
    saving = true;
    try {
      const payload = blocks.map((block) => ({
        type: block.type,
        data: block.data,
        settings: block.settings || copy(settingsBlank),
        options: block.options || copy(optionDefaults[block.type] || {}),
      }));

      const response = await fetch('/admin/pages/' + pageId + '/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF },
        body: JSON.stringify({
          title:        pageState.title,
          section:      pageState.section,
          summary:      pageState.summary,
          status:       pageState.status,
          cover:        coverId || 0,
          show_on_home: pageState.home,
          blocks:       payload,
        }),
      });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.error || 'Save failed');
      saveState.textContent = 'Saved ' + result.saved_at;
      saveState.dataset.state = 'saved';
      if (preview) {
        previewReady = false;
        preview.contentWindow.location.reload();
      }
    } catch (error) {
      saveState.textContent = 'Not saved';
      saveState.dataset.state = 'error';
    } finally {
      saving = false;
    }
  }

  window.addEventListener('beforeunload', (event) => {
    if (saveState.dataset.state === 'pending') {
      event.preventDefault();
      event.returnValue = '';
    }
  });

  // =============================================================== chrome

  const outlineToggle = document.getElementById('outline-toggle');
  if (outlineToggle) {
    const apply = (hidden) => {
      root.classList.toggle('is-outlinehidden', hidden);
      outlineToggle.setAttribute('aria-pressed', String(!hidden));
    };
    let hidden = false;
    try { hidden = window.localStorage.getItem('brandhub.outline') === 'off'; } catch (e) {}
    apply(hidden);
    outlineToggle.addEventListener('click', () => {
      hidden = !hidden;
      apply(hidden);
      try { window.localStorage.setItem('brandhub.outline', hidden ? 'off' : 'on'); } catch (e) {}
    });
  }

  const pageSettings = document.getElementById('page-settings-toggle');
  if (pageSettings) pageSettings.addEventListener('click', () => select(null));

  document.querySelectorAll('[data-preview-width]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-preview-width]').forEach((o) => o.classList.toggle('is-on', o === btn));
      if (preview) preview.style.maxWidth = btn.dataset.previewWidth;
    });
  });

  // Escape returns to page settings; that is the only shortcut for now.
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && selected !== null && !document.querySelector('.picker, .blockmenu__scrim')) {
      select(null);
    }
  });

  renderAll();
})();
