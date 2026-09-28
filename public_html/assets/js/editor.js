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
  // The block chooser's icons and the order it groups them in. Both come from
  // the server so a new block type needs no change here.
  const icons          = JSON.parse(root.dataset.blockIcons || '{}');
  const groupOrder     = JSON.parse(root.dataset.blockGroups || '[]');

  /**
   * Where a save goes, and what it carries.
   *
   * The editor was written for pages and is now also the editor for a
   * component's written guidance, which is the same blocks on a different row.
   * Rather than a second copy of 1200 lines, the endpoint is a data attribute and
   * the payload drops the page fields when there is no page — everything else,
   * the outline, the inspector, the canvas, editing in place, is identical
   * because the thing being edited is identical.
   */
  const saveUrl = root.dataset.saveUrl || '/admin/pages/' + pageId + '/save';
  const savesPage = !root.dataset.saveUrl;

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

  // ---------------------------------------------------------- asset fields

  /**
   * The picker itself lives in picker.js, shared with the site logo and page
   * covers in admin.js. It used to be implemented twice and the two copies had
   * already drifted apart.
   */
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
        AssetPicker.open({
          accept: accept,
          current: id,
          onPick: (image) => { onInput(String(image.id)); paint(String(image.id)); },
        });
      });
      choose.className = 'assetpick__choose m3-state';

      if (id) {
        AssetPicker.load().then((images) => {
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

    /**
     * A repeater inside a repeater, which is what a colour ramp needs: a list
     * of families, each holding its own list of steps. field() is what a
     * repeater row calls for each of its fields, so handling it here is what
     * makes nesting work at any depth rather than only at the top level.
     *
     * The value is the array itself, so onInput is handed the same array back
     * after it has been changed in place.
     */
    if (def.type === 'repeater') {
      const rows = Array.isArray(value) ? value : [];
      return repeater(def, rows, function (changed, inPlace) {
        onInput(changed, inPlace);
      }, dense);
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
    } else if (def.type === 'number') {
      /**
       * A number with a unit, and blank allowed.
       *
       * Blank is the point of it: an empty radius means "follow the site
       * setting", so the field must not helpfully fill itself in with the
       * current global — that would pin the block to today's number and the
       * global would stop reaching it. The same trap as the colour field that
       * displayed a default and only wrote on input.
       */
      input = document.createElement('input');
      input.type = 'number';
      input.className = 'm3-field__input';
      input.placeholder = ' ';
      input.value = value === 0 || value ? value : '';
      if (def.min !== undefined) input.min = def.min;
      if (def.max !== undefined) input.max = def.max;
      if (def.unit) {
        wrap.classList.add('m3-field--unit');
        wrap.dataset.unit = def.unit;
      }
    } else if (def.type === 'colour') {
      input = document.createElement('input');
      input.type = 'text';
      input.className = 'm3-field__input';
      input.placeholder = ' ';
      // An optional colour stays empty. Prefilling one would store a colour
      // nobody chose the first time the panel was opened.
      input.value = value || (def.optional ? '' : COLOUR_DEFAULT);

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

  function repeater(def, rows, onChange, dense) {
    const wrap = document.createElement('div');
    wrap.className = 'repeater' + (dense ? ' repeater--nested' : '');

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
      item.dataset.row = index;

      const bar = document.createElement('div');
      bar.className = 'repeater__bar';

      /**
       * Dragging is switched on by the grip, not left on the row.
       *
       * A permanently draggable row swallows text selection in the fields
       * inside it, so the browser starts a drag when you try to select a word.
       * The grip turns it on while it is held and dragend turns it off again.
       */
      const grip = document.createElement('span');
      grip.className = 'repeater__grip';
      grip.title = 'Drag to reorder';
      grip.setAttribute('aria-hidden', 'true');
      grip.addEventListener('mousedown', () => { item.draggable = true; });
      grip.addEventListener('touchstart', () => { item.draggable = true; }, { passive: true });
      bar.append(grip);

      const name = document.createElement('span');
      name.className = 'repeater__name';
      name.textContent = rowTitle(def, row, index);
      // The number stays available as the position, since the name replaced it.
      name.title = (def.item || 'Item') + ' ' + (index + 1);
      bar.append(name);

      // Compact controls, and disabled at the ends rather than doing nothing
      // when pressed, which gave no clue that the row was already first.
      //
      // Each of these changes the shape of the list, so each is remembered for
      // undo. Typing inside a row is not: the field still holds what was typed
      // and the browser's own undo works there.
      const up = miniButton('Move up', '&uarr;', () => {
        remember(); move(rows, index, index - 1); onChange(rows);
      });
      const down = miniButton('Move down', '&darr;', () => {
        remember(); move(rows, index, index + 1); onChange(rows);
      });
      up.disabled = index === 0;
      down.disabled = index === rows.length - 1;

      const remove = miniButton('Remove', '&times;', () => {
        remember();
        rows.splice(index, 1);
        onChange(rows);
      }, 'is-danger');

      bar.append(up, down, remove);
      item.append(bar);

      Object.entries(def.fields).forEach(([key, fieldDef]) => {
        // inPlace comes back from a nested repeater: adding or removing one of
        // its rows changes the shape of the panel and has to rebuild it, where
        // typing in one of its fields does not.
        item.append(field(fieldDef, row[key], (value, inPlace) => {
          row[key] = value;
          // Keep the row's own label in step as it is typed.
          name.textContent = rowTitle(def, row, index);
          onChange(rows, fieldDef.type === 'repeater' ? inPlace : true);
        }, dense));
      });

      wrap.append(item);
    });

    // Dragging a row to reorder it. Up and down remain, because they are the
    // ones a keyboard can reach; this is for a palette of eleven steps where
    // moving one to the other end is nine clicks.
    let dragging = null;

    wrap.addEventListener('dragstart', (event) => {
      const item = event.target.closest('.repeater__item');
      if (!item || item.parentElement !== wrap) return;
      dragging = Number(item.dataset.row);
      item.classList.add('is-dragging');
      if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
    });

    wrap.addEventListener('dragover', (event) => {
      if (dragging === null) return;
      event.preventDefault();
      const item = event.target.closest('.repeater__item');
      wrap.querySelectorAll('.is-over').forEach((el) => el.classList.remove('is-over'));
      if (item && item.parentElement === wrap) item.classList.add('is-over');
    });

    wrap.addEventListener('drop', (event) => {
      if (dragging === null) return;
      event.preventDefault();
      const item = event.target.closest('.repeater__item');
      if (item && item.parentElement === wrap) {
        const to = Number(item.dataset.row);
        if (to !== dragging) {
          remember();
          rows.splice(to, 0, rows.splice(dragging, 1)[0]);
          // Not in place: the rows have swapped, so the panel is rebuilt.
          onChange(rows);
        }
      }
      dragging = null;
    });

    wrap.addEventListener('dragend', () => {
      dragging = null;
      wrap.querySelectorAll('.repeater__item').forEach((el) => {
        el.classList.remove('is-dragging', 'is-over');
        el.draggable = false;
      });
    });

    const add = button('Add ' + (def.item || 'item').toLowerCase(), () => {
      remember();
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
  function touchOutline(index) {
    // Defaults to the selection, because that is what the inspector is editing.
    // The canvas passes its own index, since text can be edited there without
    // the block ever being selected.
    const at = index === undefined ? selected : index;
    if (at === null || !blocks[at]) return;
    const row = outline.querySelector('.outline__row[data-index="' + at + '"]');
    const desc = row && row.querySelector('.outline__desc');
    if (desc) desc.textContent = describe(blocks[at]);
  }

  /* ------------------------------------------------- undo, copy, reordering */

  /**
   * Undo, for the things that are hard to put back by hand.
   *
   * Version history already covers a whole page, but restoring a version to
   * recover one deleted block is a sledgehammer. This holds the last few
   * arrangements of the blocks so a delete, a duplicate or a reorder can be
   * taken back immediately. Typing is not recorded: the field still holds what
   * was typed, and the browser's own undo works inside it.
   */
  const history = [];
  const HISTORY_DEPTH = 40;

  function remember() {
    history.push({ blocks: copy(blocks), selected: selected });
    if (history.length > HISTORY_DEPTH) history.shift();
  }

  function undo() {
    const previous = history.pop();
    if (!previous) {
      say('Nothing to undo.');
      return;
    }
    blocks = previous.blocks;
    selected = previous.selected !== null && previous.selected < blocks.length
      ? previous.selected
      : null;
    renderAll();
    queueSave();
    say('Undone.');
  }

  /** Says something to the person, and to a screen reader, without a dialog. */
  function say(message) {
    if (!saveState) return;
    saveState.textContent = message;
    saveState.dataset.state = 'note';
    // The save state takes the strip back over as soon as anything is saved.
  }

  function modifierName() {
    return /Mac|iPhone|iPad/.test(window.navigator.platform || '') ? 'Cmd' : 'Ctrl';
  }

  function moveBlock(from, to) {
    if (to < 0 || to >= blocks.length || from === to) return;
    remember();
    blocks.splice(to, 0, blocks.splice(from, 1)[0]);
    select(to);
    queueSave();
  }

  /**
   * A block copied here can be pasted into any page.
   *
   * It goes through localStorage rather than the system clipboard, because the
   * system clipboard cannot be read without a permission prompt and this has to
   * survive navigating to the other page. The same download list or contacts
   * block is wanted on several pages, and rebuilding it each time is the sort
   * of work this editor exists to remove.
   */
  const CLIPBOARD = 'brandhub.block';

  function copyBlock(index) {
    const block = copy(ensure(blocks[index]));
    delete block.__key;
    try {
      window.localStorage.setItem(CLIPBOARD, JSON.stringify(block));
      say(schema[block.type].label + ' copied. Paste it on any page.');
      paintOutline();
    } catch (error) {
      say('Could not copy that block.');
    }
  }

  /** What is on the clipboard, if it is still a block this site understands. */
  function clipboardBlock() {
    try {
      const raw = window.localStorage.getItem(CLIPBOARD);
      if (!raw) return null;
      const block = JSON.parse(raw);
      // A block type can be removed between copying and pasting.
      return (block && schema[block.type]) ? block : null;
    } catch (error) {
      return null;
    }
  }

  function pasteBlock(at) {
    const block = clipboardBlock();
    if (!block) return;
    remember();
    blocks.splice(at, 0, ensure(copy(block)));
    select(at);
    queueSave();
    say(schema[block.type].label + ' pasted.');
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

      // Reordering was drag-only, which no keyboard can do and a touch screen
      // does badly. These are the same movement, reachable by tab.
      const up = miniButton('Move up', '&uarr;', () => moveBlock(index, index - 1));
      const down = miniButton('Move down', '&darr;', () => moveBlock(index, index + 1));
      up.disabled = index === 0;
      down.disabled = index === blocks.length - 1;

      acts.append(
        up,
        down,
        miniButton('Copy', '&#9112;', () => {
          copyBlock(index);
        }),
        miniButton('Duplicate', '&#10697;', () => {
          remember();
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
          remember();
          // The row leaves before it is removed; once it is gone there is
          // nothing left to animate.
          await UIMotion.settled(UIMotion.exit(row));
          blocks.splice(index, 1);
          selected = null;
          renderAll();
          queueSave();
          say('Block deleted. Undo with ' + modifierName() + ' Z.');
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

    paintPaste();
  }

  /**
   * The paste control, shown only when there is something to paste. A button
   * that does nothing most of the time is worse than no button.
   */
  function paintPaste() {
    const holder = document.getElementById('outline-paste');
    if (!holder) return;

    const block = clipboardBlock();
    holder.innerHTML = '';
    holder.hidden = !block;
    if (!block) return;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'outline__paste m3-state';
    button.textContent = 'Paste ' + schema[block.type].label.toLowerCase();
    button.title = 'Copied from this or another page';
    button.addEventListener('click', () => pasteBlock(blocks.length));
    holder.append(button);
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
      remember();
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
    if (event.data.action === 'edit') {
      applyCanvasEdit(event.data.index, event.data.field, event.data.value);
    }
    if (event.data.action === 'editing') {
      canvasEditing = !!event.data.editing;
      if (!canvasEditing) {
        // The inspector's fields were left behind while the canvas was being
        // typed into, so they are rebuilt once it is finished with rather than
        // on every keystroke.
        renderInspector();
        if (reloadOwed) {
          reloadOwed = false;
          reloadPreview();
        }
      }
    }
  });

  /* ------------------------------------------------------ editing in place */

  // True while the cursor is in text on the canvas. Saving must not reload the
  // iframe during that: it would take the cursor, and the words being typed,
  // out from under the person typing them.
  let canvasEditing = false;
  let reloadOwed = false;

  /**
   * Writes a value from the canvas into the block it belongs to.
   *
   * The path is the route to the value inside the block's data, as
   * editable() wrote it: 'heading', or 'pairs.0.rule' for one inside a
   * repeater. Anything that does not resolve is ignored rather than guessed at,
   * because the alternative is writing a key the block definition does not have
   * and having sanitise_blocks quietly drop it on the next save.
   */
  function applyCanvasEdit(index, path, value) {
    const block = blocks[index];
    if (!block || typeof path !== 'string' || !path) return;

    const steps = path.split('.');
    const last = steps.pop();
    let target = block.data;

    for (const step of steps) {
      if (target === null || typeof target !== 'object' || !(step in target)) return;
      target = target[step];
    }
    if (target === null || typeof target !== 'object' || !(last in target)) return;
    if (target[last] === value) return;

    target[last] = value;
    touchOutline(index);
    queueSave();
  }

  function reloadPreview() {
    if (!preview || !preview.contentWindow) return;
    previewReady = false;
    preview.contentWindow.location.reload();
  }

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
        !term || (def.label + ' ' + (def.hint || '') + ' ' + (def.group || '')).toLowerCase().includes(term));

      if (!entries.length) {
        const none = document.createElement('p');
        none.className = 'blockmenu__none';
        none.textContent = 'Nothing matches that.';
        list.append(none);
        return;
      }

      /**
       * Grouped, in the order the server gives, with anything whose group is not
       * in that list falling to the end rather than disappearing. Twenty-eight
       * choices in one undivided grid is a wall: the groups are what make it
       * scannable by someone who knows roughly what they want but not its name.
       */
      const byGroup = new Map();
      groupOrder.forEach((name) => byGroup.set(name, []));
      entries.forEach((entry) => {
        const name = entry[1].group || 'Other';
        if (!byGroup.has(name)) byGroup.set(name, []);
        byGroup.get(name).push(entry);
      });

      byGroup.forEach((group, name) => {
        if (!group.length) return;
        const heading = document.createElement('p');
        heading.className = 'blockmenu__group';
        heading.textContent = name;
        list.append(heading);
        group.forEach(([type, def]) => list.append(entry(type, def)));
      });
    }

    function entry(type, def) {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'blockmenu__item';
      item.innerHTML =
        '<span class="blockmenu__icon"></span>' +
        '<span class="blockmenu__text">' +
          '<span class="blockmenu__label"></span><span class="blockmenu__hint"></span>' +
        '</span>';

      // The icon markup comes from the server's own icon set, never from
      // anything typed, so it is set as markup; the label and hint are text.
      item.querySelector('.blockmenu__icon').innerHTML = icons[def.icon]
        ? '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"' +
          ' stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
          icons[def.icon] + '</svg>'
        : '';
      item.querySelector('.blockmenu__label').textContent = def.label;
      item.querySelector('.blockmenu__hint').textContent = def.hint || '';

      item.addEventListener('click', () => {
        remember();
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

      return item;
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
      return UIMotion.settled(UIMotion.exitDialog(panel, scrim)).then(() => scrim.remove());
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

      const body = savesPage
        ? {
            title:        pageState.title,
            section:      pageState.section,
            summary:      pageState.summary,
            status:       pageState.status,
            cover:        coverId || 0,
            show_on_home: pageState.home,
            blocks:       payload,
          }
        : { blocks: payload };

      const response = await fetch(saveUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF },
        body: JSON.stringify(body),
      });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.error || 'Save failed');
      saveState.textContent = 'Saved ' + result.saved_at;
      saveState.dataset.state = 'saved';

      // Not while the canvas is being typed into. Reloading there would take
      // the cursor and the half-finished sentence with it. The canvas already
      // shows what was typed, so it is only owed a reload to pick up whatever
      // the server made of it, and that can wait until the typing stops.
      if (canvasEditing) {
        reloadOwed = true;
      } else {
        reloadPreview();
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

  /**
   * Keyboard.
   *
   * Everything here is deliberately inert while a field has focus, so typing
   * into a block is never interrupted: the browser's own undo belongs to the
   * field, and a bare arrow key belongs to the text. Only the modifier
   * combinations reach across from a field, and only undo does that.
   */
  function isTyping(target) {
    if (!target) return false;
    const tag = target.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable;
  }

  document.addEventListener('keydown', (event) => {
    const overlay = document.querySelector('.picker, .blockmenu__scrim');

    if (event.key === 'Escape' && selected !== null && !overlay) {
      select(null);
      return;
    }
    if (overlay) return;

    const modifier = event.metaKey || event.ctrlKey;

    // Undo works from anywhere except inside a field, where the field's own
    // undo is the one that is wanted.
    if (modifier && !event.shiftKey && event.key.toLowerCase() === 'z' && !isTyping(event.target)) {
      event.preventDefault();
      undo();
      return;
    }

    if (selected === null || isTyping(event.target)) return;

    // Moving the selected block, with the modifier so a bare arrow key still
    // scrolls the page.
    if (modifier && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
      event.preventDefault();
      moveBlock(selected, selected + (event.key === 'ArrowUp' ? -1 : 1));
      return;
    }
    if (modifier && event.key.toLowerCase() === 'c') {
      event.preventDefault();
      copyBlock(selected);
      return;
    }
    if (modifier && event.key.toLowerCase() === 'v') {
      event.preventDefault();
      pasteBlock(selected + 1);
    }
  });

  renderAll();
})();
