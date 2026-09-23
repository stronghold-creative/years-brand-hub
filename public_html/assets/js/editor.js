(function () {
  'use strict';

  const root = document.getElementById('editor');
  if (!root) return;

  const schema         = JSON.parse(root.dataset.schema);
  const defaults       = JSON.parse(root.dataset.defaults);
  const settingsSchema = JSON.parse(root.dataset.settings);
  const settingsBlank  = JSON.parse(root.dataset.settingsDefaults);
  const pageId         = root.dataset.pageId;

  let blocks = JSON.parse(root.dataset.blocks);

  const list      = document.getElementById('blocks');
  const saveState = document.getElementById('save-state');
  const preview   = document.getElementById('preview');

  // Cards are collapsed by default so a long page stays navigable. This holds
  // the indexes that are currently expanded.
  const open = new Set();

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

  // =============================================================== blocks

  const GLYPH = {
    up: '&uarr;', down: '&darr;', duplicate: '&#10697;',
    remove: '&times;', settings: '&#9881;',
  };

  function iconButton(label, glyph, handler, className) {
    const el = document.createElement('button');
    el.type = 'button';
    el.className = 'blk__act ' + (className || '');
    el.innerHTML = glyph;
    el.setAttribute('aria-label', label);
    el.title = label;
    el.addEventListener('click', (event) => { event.stopPropagation(); handler(); });
    return el;
  }

  /** A short line describing a block, shown while its card is collapsed. */
  function describe(block) {
    const def = schema[block.type];
    for (const [key, fieldDef] of Object.entries(def.fields)) {
      const value = block.data[key];
      if (fieldDef.type === 'repeater' && Array.isArray(value) && value.length) {
        const noun = (fieldDef.item || 'item').toLowerCase();
        return value.length + ' ' + noun + (value.length === 1 ? '' : 's');
      }
      if (typeof value === 'string' && value.trim() && fieldDef.type !== 'select' && fieldDef.type !== 'asset') {
        return value.length > 70 ? value.slice(0, 70) + '\u2026' : value;
      }
    }
    return 'Empty';
  }

  /** Keeps the expanded set pointing at the same blocks after a reorder. */
  function shiftOpen(from, to) {
    if (to < 0 || to >= blocks.length) return;
    const a = open.has(from);
    const b = open.has(to);
    open.delete(from); open.delete(to);
    if (a) open.add(to);
    if (b) open.add(from);
  }

  function renderBlock(block, index) {
    const def = schema[block.type];
    const card = document.createElement('article');
    card.className = 'blk' + (open.has(index) ? ' is-open' : '');
    card.draggable = true;
    card.dataset.index = index;

    const head = document.createElement('header');
    head.className = 'blk__head';
    head.innerHTML =
      '<span class="blk__grip" aria-hidden="true"></span>' +
      '<span class="blk__type"></span>' +
      '<span class="blk__summary"></span>';
    head.querySelector('.blk__type').textContent = def.label;
    head.querySelector('.blk__summary').textContent = describe(block);

    const acts = document.createElement('span');
    acts.className = 'blk__acts';
    acts.append(
      iconButton('Appearance', GLYPH.settings, () => {
        block.settingsOpen = !block.settingsOpen;
        open.add(index);
        render();
      }),
      iconButton('Move up', GLYPH.up, () => {
        move(blocks, index, index - 1); shiftOpen(index, index - 1); render(); queueSave();
      }),
      iconButton('Move down', GLYPH.down, () => {
        move(blocks, index, index + 1); shiftOpen(index, index + 1); render(); queueSave();
      }),
      iconButton('Duplicate', GLYPH.duplicate, () => {
        const copy = JSON.parse(JSON.stringify(blocks[index]));
        delete copy.settingsOpen;
        blocks.splice(index + 1, 0, copy);
        open.add(index + 1);
        render(); queueSave();
      }),
      iconButton('Delete', GLYPH.remove, () => {
        if (!window.confirm('Delete this ' + def.label.toLowerCase() + ' block?')) return;
        blocks.splice(index, 1);
        open.delete(index);
        render(); queueSave();
      }, 'blk__act--danger')
    );
    head.append(acts);

    head.addEventListener('click', (event) => {
      if (event.target.closest('.blk__acts')) return;
      if (open.has(index)) { open.delete(index); } else { open.add(index); }
      render();
    });
    card.append(head);

    if (!open.has(index)) return card;

    const body = document.createElement('div');
    body.className = 'blk__body';

    if (def.hint) {
      const hint = document.createElement('p');
      hint.className = 'blk__hint';
      hint.textContent = def.hint;
      body.append(hint);
    }

    Object.entries(def.fields).forEach(([key, fieldDef]) => {
      if (fieldDef.type === 'repeater') {
        if (!Array.isArray(block.data[key])) block.data[key] = [];
        body.append(repeater(fieldDef, block.data[key], (rows, inPlace) => {
          block.data[key] = rows;
          if (!inPlace) render();
          queueSave();
        }));
      } else {
        body.append(field(fieldDef, block.data[key], (value) => {
          block.data[key] = value;
          head.querySelector('.blk__summary').textContent = describe(block);
          queueSave();
        }));
      }
    });

    if (block.settingsOpen) {
      if (!block.settings) block.settings = JSON.parse(JSON.stringify(settingsBlank));
      const panel = document.createElement('div');
      panel.className = 'blk__settings';
      const title = document.createElement('p');
      title.className = 'blk__settingstitle';
      title.textContent = 'How this block looks';
      panel.append(title);

      const grid = document.createElement('div');
      grid.className = 'blk__settingsgrid';
      Object.entries(settingsSchema).forEach(([key, fieldDef]) => {
        grid.append(field(fieldDef, block.settings[key], (value) => {
          block.settings[key] = value;
          queueSave();
        }, true));
      });
      panel.append(grid);
      body.append(panel);
    }

    card.append(body);
    return card;
  }

  // A block can be inserted anywhere, not only at the end.
  function inserter(index) {
    const row = document.createElement('div');
    row.className = 'blk-insert';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'blk-insert__btn';
    btn.innerHTML = '<span>Add a block</span>';
    btn.addEventListener('click', () => openBlockMenu(index, row));
    row.append(btn);
    return row;
  }

  function openBlockMenu(index, anchor) {
    document.querySelectorAll('.blk-menu').forEach((el) => el.remove());

    const menu = document.createElement('div');
    menu.className = 'blk-menu';
    Object.entries(schema).forEach(([type, def]) => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'blk-menu__item';
      item.innerHTML = '<span class="blk-menu__label"></span><span class="blk-menu__hint"></span>';
      item.querySelector('.blk-menu__label').textContent = def.label;
      item.querySelector('.blk-menu__hint').textContent = def.hint || '';
      item.addEventListener('click', () => {
        blocks.splice(index, 0, {
          type: type,
          data: JSON.parse(JSON.stringify(defaults[type])),
          settings: JSON.parse(JSON.stringify(settingsBlank)),
        });
        open.clear();
        open.add(index);
        menu.remove();
        render();
        queueSave();
      });
      menu.append(item);
    });

    anchor.append(menu);
    const close = (event) => {
      if (!anchor.contains(event.target)) { menu.remove(); document.removeEventListener('click', close); }
    };
    setTimeout(() => document.addEventListener('click', close), 0);
    document.addEventListener('keydown', function esc(event) {
      if (event.key === 'Escape') { menu.remove(); document.removeEventListener('keydown', esc); }
    });
  }

  function render() {
    list.innerHTML = '';
    if (!blocks.length) {
      const empty = document.createElement('p');
      empty.className = 'm3-empty';
      empty.textContent = 'This page is empty. Add a block to start.';
      list.append(empty);
    }
    blocks.forEach((block, index) => {
      list.append(inserter(index));
      list.append(renderBlock(block, index));
    });
    list.append(inserter(blocks.length));
  }

  // ------------------------------------------------------------ drag to sort

  let dragFrom = null;

  list.addEventListener('dragstart', (event) => {
    const card = event.target.closest('.blk');
    if (!card) return;
    dragFrom = Number(card.dataset.index);
    card.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
  });

  list.addEventListener('dragover', (event) => {
    event.preventDefault();
    const card = event.target.closest('.blk');
    list.querySelectorAll('.is-over').forEach((el) => el.classList.remove('is-over'));
    if (card) card.classList.add('is-over');
  });

  list.addEventListener('drop', (event) => {
    event.preventDefault();
    const card = event.target.closest('.blk');
    if (card && dragFrom !== null) {
      move(blocks, dragFrom, Number(card.dataset.index));
      open.clear();
      render();
      queueSave();
    }
    dragFrom = null;
  });

  list.addEventListener('dragend', () => {
    list.querySelectorAll('.is-dragging, .is-over').forEach((el) => el.classList.remove('is-dragging', 'is-over'));
  });

  // =================================================================== saving

  let timer = null;
  let saving = false;

  let coverId = '';
  const coverMount = document.getElementById('f-cover');
  if (coverMount) {
    coverId = coverMount.dataset.cover && coverMount.dataset.cover !== '0' ? coverMount.dataset.cover : '';
    coverMount.append(assetField(
      { label: 'Cover image, shown on the index', type: 'asset' },
      coverId,
      (value) => { coverId = value; queueSave(); }
    ));
  }

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
      // Editor-only flags never reach the database.
      const payload = blocks.map((block) => ({
        type: block.type,
        data: block.data,
        settings: block.settings || JSON.parse(JSON.stringify(settingsBlank)),
      }));

      const response = await fetch('/admin/pages/' + pageId + '/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF },
        body: JSON.stringify({
          title:   document.getElementById('f-title').value,
          section: document.getElementById('f-section').value,
          summary: document.getElementById('f-summary').value,
          status:  document.getElementById('f-status').value,
          cover:   coverId || 0,
          blocks:  payload,
        }),
      });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.error || 'Save failed');
      saveState.textContent = 'Saved ' + result.saved_at;
      saveState.dataset.state = 'saved';
      if (preview) preview.contentWindow.location.reload();
    } catch (error) {
      saveState.textContent = 'Not saved';
      saveState.dataset.state = 'error';
    } finally {
      saving = false;
    }
  }

  const editorTitle = document.getElementById('editor-title');
  const titleField  = document.getElementById('f-title');
  if (editorTitle && titleField) {
    titleField.addEventListener('input', () => {
      editorTitle.textContent = titleField.value || 'Untitled page';
    });
  }

  ['f-title', 'f-section', 'f-summary', 'f-status'].forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', queueSave);
    el.addEventListener('change', queueSave);
  });

  window.addEventListener('beforeunload', (event) => {
    if (saveState.dataset.state === 'pending') {
      event.preventDefault();
      event.returnValue = '';
    }
  });

  // ============================================================== chrome

  // Page settings, collapsed by default so the blocks are the focus.
  const meta = document.getElementById('page-meta');
  const metaToggle = document.getElementById('page-meta-toggle');
  if (meta && metaToggle) {
    metaToggle.addEventListener('click', () => {
      const opening = meta.hasAttribute('hidden');
      if (opening) { meta.removeAttribute('hidden'); } else { meta.setAttribute('hidden', ''); }
      metaToggle.setAttribute('aria-expanded', String(opening));
    });
  }

  // Preview pane can be hidden for full width editing; the choice sticks.
  const previewToggle = document.getElementById('preview-toggle');
  if (previewToggle) {
    const apply = (hidden) => {
      root.classList.toggle('is-previewhidden', hidden);
      previewToggle.setAttribute('aria-pressed', String(!hidden));
      previewToggle.textContent = hidden ? 'Show preview' : 'Hide preview';
    };
    let hidden = false;
    try { hidden = window.localStorage.getItem('brandhub.preview') === 'off'; } catch (e) { hidden = false; }
    apply(hidden);
    previewToggle.addEventListener('click', () => {
      hidden = !hidden;
      apply(hidden);
      try { window.localStorage.setItem('brandhub.preview', hidden ? 'off' : 'on'); } catch (e) {}
    });
  }

  // Preview width, for checking a layout on a narrow screen.
  document.querySelectorAll('[data-preview-width]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-preview-width]').forEach((o) => o.classList.toggle('is-on', o === btn));
      if (preview) preview.style.maxWidth = btn.dataset.previewWidth;
    });
  });

  render();
})();
