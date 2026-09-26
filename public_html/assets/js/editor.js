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

  // Matches the colour fallback in block_defaults() in app/lib/blocks.php. The
  // two have to agree: a value the editor shows but never writes is saved as
  // empty, and an empty colour renders as a broken swatch.
  const COLOUR_DEFAULT = '#7257B7';

  /**
   * A blank row for a repeater, built from the field definitions rather than
   * filled with empty strings.
   *
   * Mirrors block_defaults() on the server. Setting everything to '' looked
   * harmless but was not: the colour field displays COLOUR_DEFAULT when its
   * value is empty and only writes on input, so adding a colour and typing
   * only its name saved a swatch with no hex at all.
   */
  function blankRow(fields) {
    const row = {};
    Object.entries(fields).forEach(([key, def]) => {
      if (def.type === 'select') {
        row[key] = String(Object.keys(def.options)[0]);
      } else if (def.type === 'colour') {
        row[key] = COLOUR_DEFAULT;
      } else if (def.type === 'repeater') {
        row[key] = [];
      } else {
        row[key] = '';
      }
    });
    return row;
  }

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

  async function loadImages(refresh) {
    if (imageCache && !refresh) return imageCache;
    const response = await fetch('/admin/assets.json', { headers: { 'Accept': 'application/json' } });
    const data = await response.json();
    imageCache = data.images || [];
    return imageCache;
  }

  /**
   * The asset picker.
   *
   * $accept is 'image' or 'video'. An image field must not offer an MP4 and the
   * video block must not offer a PNG, so the field says what it wants and the
   * library is filtered rather than everything being shown to everyone.
   *
   * It uploads too. Leaving the editor, going to Assets, uploading, coming back
   * and finding the block again is a lot of steps to put between a writer and
   * one picture, and it is the step writing these pages needs most often.
   */
  function openPicker(onPick, accept) {
    const kind = accept === 'video' ? 'video' : 'image';
    const noun = kind === 'video' ? 'video' : 'image';

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
          '<span class="picker__hint">It is added to the asset library and selected here.</span>' +
        '</footer>' +
      '</div>';
    document.body.append(overlay);

    const grid = overlay.querySelector('.picker__grid');
    const search = overlay.querySelector('.picker__search');
    const hint = overlay.querySelector('.picker__hint');
    const file = overlay.querySelector('.picker__upload input');
    const close = () => { overlay.remove(); document.removeEventListener('keydown', onKey); };
    const onKey = (event) => { if (event.key === 'Escape') close(); };

    overlay.querySelector('.picker__close').addEventListener('click', close);
    overlay.addEventListener('click', (event) => { if (event.target === overlay) close(); });
    document.addEventListener('keydown', onKey);

    const matching = (list) => list.filter((item) => (item.kind || 'image') === kind);

    function draw(items) {
      grid.innerHTML = '';
      if (!items.length) {
        grid.textContent = 'No ' + noun + 's in the library yet. Upload one below.';
        return;
      }
      items.forEach((item) => {
        const tile = document.createElement('button');
        tile.type = 'button';
        tile.className = 'picker__tile m3-state';
        tile.innerHTML = item.kind === 'video'
          ? '<span class="picker__thumb picker__thumb--file">MP4</span><span class="picker__label"></span>'
          : '<span class="picker__thumb"><img src="' + item.url + '" alt="" loading="lazy"></span>' +
            '<span class="picker__label"></span>';
        tile.querySelector('.picker__label').textContent = item.label || item.file;
        tile.addEventListener('click', () => { onPick(item); close(); });
        grid.append(tile);
      });
    }

    function load(refresh) {
      return loadImages(refresh).then((all) => {
        const items = matching(all);
        draw(items);
        return items;
      });
    }

    load().then((items) => {
      search.focus();
      search.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        draw(!term ? items : items.filter((item) =>
          (item.label + ' ' + item.file + ' ' + item.tags).toLowerCase().includes(term)
        ));
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
        // The cache is dropped and the new file added to it before the block is
        // told, because selecting it immediately re-reads the library to draw
        // its thumbnail. Clearing the cache afterwards instead left that read
        // looking at a stale list and reporting the file as missing.
        imageCache = imageCache ? [result.asset].concat(imageCache) : [result.asset];
        // Straight into the block, which is the point of uploading from here.
        onPick(result.asset);
        close();
      } catch (error) {
        hint.textContent = error.message;
        file.value = '';
      }
    });
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

    const accept = def.accept === 'video' ? 'video' : 'image';

    function paint(id) {
      shown.innerHTML = '';
      const choose = button(id ? 'Change' : 'Choose ' + (accept === 'video' ? 'a video' : 'an image'), () => {
        openPicker((image) => { onInput(String(image.id)); paint(String(image.id)); }, accept);
      });
      choose.className = 'assetpick__choose m3-state';

      if (id) {
        loadImages().then((images) => {
          const match = images.find((image) => String(image.id) === String(id));
          const preview = document.createElement('span');
          preview.className = 'assetpick__preview';
          if (match) {
            preview.innerHTML = match.kind === 'video'
              ? '<span class="assetpick__file">MP4</span>'
              : '<img src="' + match.url + '" alt="">';
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
      input.value = value || COLOUR_DEFAULT;

      const chip = document.createElement('input');
      chip.type = 'color';
      chip.className = 'field__chip';
      chip.value = /^#[0-9a-f]{6}$/i.test(input.value) ? input.value : COLOUR_DEFAULT;
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

  /**
   * The first bit of text in a row, for labelling it.
   *
   * A list of eight rows all headed "Colour 3" tells the writer nothing about
   * which one they are looking at. Its own name does.
   */
  function rowTitle(def, row, index) {
    const fallback = (def.item || 'Item') + ' ' + (index + 1);
    for (const [key, fieldDef] of Object.entries(def.fields)) {
      if (fieldDef.type !== 'text' && fieldDef.type !== 'textarea') continue;
      const value = (row[key] || '').trim();
      if (value) return value.length > 34 ? value.slice(0, 34) + '…' : value;
    }
    return fallback;
  }

  function repeater(def, rows, onChange) {
    const wrap = document.createElement('div');
    wrap.className = 'repeater';

    const head = document.createElement('div');
    head.className = 'repeater__head';
    head.textContent = def.label;
    if (rows.length) {
      const count = document.createElement('span');
      count.className = 'repeater__count';
      count.textContent = String(rows.length);
      head.append(count);
    }
    wrap.append(head);

    rows.forEach((row, index) => {
      const item = document.createElement('div');
      item.className = 'repeater__item';

      const bar = document.createElement('div');
      bar.className = 'repeater__bar';

      const name = document.createElement('span');
      name.className = 'repeater__name';
      name.textContent = rowTitle(def, row, index);
      // The number stays available as the position, since the name replaced it.
      name.title = (def.item || 'Item') + ' ' + (index + 1);
      bar.append(name);

      // Compact controls, and disabled at the ends rather than doing nothing
      // when pressed, which gave no clue that the row was already first.
      const up = miniButton('Move up', '&uarr;', () => { move(rows, index, index - 1); onChange(rows); });
      const down = miniButton('Move down', '&darr;', () => { move(rows, index, index + 1); onChange(rows); });
      up.disabled = index === 0;
      down.disabled = index === rows.length - 1;

      const remove = miniButton('Remove', '&times;', () => {
        rows.splice(index, 1);
        onChange(rows);
      }, 'is-danger');

      bar.append(up, down, remove);
      item.append(bar);

      Object.entries(def.fields).forEach(([key, fieldDef]) => {
        item.append(field(fieldDef, row[key], (value) => {
          row[key] = value;
          // Keep the row's own label in step as it is typed.
          name.textContent = rowTitle(def, row, index);
          onChange(rows, true);
        }));
      });

      wrap.append(item);
    });

    const add = button('Add ' + (def.item || 'item').toLowerCase(), () => {
      rows.push(blankRow(def.fields));
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
    // Rows carry a stable key so a reorder can be matched up and animated,
    // rather than the list simply snapping into its new arrangement.
    UIMotion.reorder(outline, '.outline__row', function () {
      paintOutline();
    });
  }

  /**
   * Updates one row's description without rebuilding the list.
   *
   * Typing in the inspector used to call renderOutline() on every character,
   * which rebuilt every row and measured each one's position for a reorder
   * animation that was never going to happen. On a long page that is a forced
   * layout per keystroke, and the typing stuttered. The only thing that can
   * actually change while typing is the description of the selected block.
   */
  function touchOutline() {
    if (selected === null || !blocks[selected]) return;
    const row = outline.querySelector('.outline__row[data-index="' + selected + '"]');
    const desc = row && row.querySelector('.outline__desc');
    if (desc) desc.textContent = describe(blocks[selected]);
  }

  function paintOutline() {
    outline.innerHTML = '';

    blocks.forEach((block, index) => {
      const row = document.createElement('li');
      row.className = 'outline__row' + (index === selected ? ' is-selected' : '');
      row.draggable = true;
      row.dataset.index = index;
      // Identity that survives reordering, so the animation follows the block
      // rather than the position.
      if (!block.__key) block.__key = 'b' + Math.random().toString(36).slice(2, 9);
      row.dataset.key = block.__key;

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
          const twin = copy(ensure(blocks[index]));
          // The copy needs its own identity. Sharing the original's key made two
          // rows indistinguishable to the reorder animation, which then measured
          // one and moved the other.
          delete twin.__key;
          blocks.splice(index + 1, 0, twin);
          select(index + 1);
          queueSave();
        }),
        miniButton('Delete', '&times;', async () => {
          if (!window.confirm('Delete this ' + schema[block.type].label.toLowerCase() + ' block?')) return;
          // The row leaves before it is removed; once it is gone there is
          // nothing left to animate.
          await UIMotion.exit(row).finished;
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

  /**
   * A small square icon button. Used by the outline rows and by repeater rows,
   * which both want the same thing, so both get it from here.
   */
  function miniButton(label, glyph, handler, className) {
    const el = document.createElement('button');
    el.type = 'button';
    el.className = 'miniact ' + (className || '');
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
    UIMotion.press(row, true);
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
          // Typing inside a row changes only the text; adding or removing one
          // changes the shape of the list and needs the panel rebuilt.
          if (inPlace) {
            touchOutline();
          } else {
            renderInspector();
            renderOutline();
          }
          queueSave();
        }));
      } else {
        content.append(field(fieldDef, block.data[key], (value) => {
          block.data[key] = value;
          touchOutline();
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
    // Filled below, then the whole panel settles in together at the end.
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
    UIMotion.enterList(inspector.querySelectorAll('.insp__section, .insp__head'));
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
      // Quiet: the canvas has already restored its own scroll position, so the
      // selection is only being re-marked, not navigated to.
      if (selected !== null) tell({ action: 'select', index: selected, quiet: true });
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
    UIMotion.enterDialog(menu, scrim);
    UIMotion.enterList(menu.querySelectorAll('.blockmenu__item'));

    const search = menu.querySelector('.blockmenu__search');
    search.focus();
    search.addEventListener('input', () => draw(search.value.trim().toLowerCase()));

    scrim.addEventListener('click', (event) => { if (event.target === scrim) closeBlockMenu(); });
    document.addEventListener('keydown', onMenuKey);
  }

  async function closeBlockMenu() {
    const open = Array.from(document.querySelectorAll('.blockmenu__scrim'));
    document.removeEventListener('keydown', onMenuKey);
    await Promise.all(open.map((scrim) => {
      const panel = scrim.querySelector('.blockmenu');
      return UIMotion.exitDialog(panel, scrim).finished.then(() => scrim.remove());
    }));
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
