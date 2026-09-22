(function () {
  'use strict';

  const root = document.getElementById('editor');
  if (!root) return;

  const schema   = JSON.parse(root.dataset.schema);
  const defaults = JSON.parse(root.dataset.defaults);
  const pageId   = root.dataset.pageId;

  let blocks = JSON.parse(root.dataset.blocks);

  const list      = document.getElementById('blocks');
  const addBtn    = document.getElementById('add-block');
  const addMenu   = document.getElementById('add-menu');
  const saveState = document.getElementById('save-state');
  const preview   = document.getElementById('preview');

  const clone = (value) => JSON.parse(JSON.stringify(value));

  // ------------------------------------------------------------ field markup

  function field(def, value, onInput) {
    const wrap = document.createElement('label');
    wrap.className = 'field';
    wrap.append(def.label + (def.optional ? ' (optional)' : ''));

    let input;
    if (def.type === 'longtext' || def.type === 'textarea') {
      input = document.createElement('textarea');
      input.rows = def.type === 'longtext' ? 6 : 2;
      input.value = value || '';
    } else if (def.type === 'select') {
      input = document.createElement('select');
      Object.entries(def.options).forEach(([key, label]) => {
        const opt = new Option(label, key, false, key === value);
        input.append(opt);
      });
    } else if (def.type === 'colour') {
      input = document.createElement('input');
      input.type = 'text';
      input.className = 'field__colour';
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
      wrap.classList.add('field--colour');
      wrap.append(input, chip);
      return wrap;
    } else {
      input = document.createElement('input');
      input.type = 'text';
      input.value = value || '';
    }

    input.addEventListener('input', () => onInput(input.value));
    input.addEventListener('change', () => onInput(input.value));
    wrap.append(input);
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

  // ------------------------------------------------------------ block markup

  function renderBlock(block, index) {
    const def = schema[block.type];
    const card = document.createElement('article');
    card.className = 'block-card';
    card.draggable = true;
    card.dataset.index = index;

    const head = document.createElement('header');
    head.className = 'block-card__head';
    head.innerHTML = '<span class="block-card__grip" aria-hidden="true"></span>' +
                     '<span class="block-card__label">' + def.label + '</span>';

    head.append(
      button('Move up', () => { move(blocks, index, index - 1); render(); queueSave(); }),
      button('Move down', () => { move(blocks, index, index + 1); render(); queueSave(); })
    );
    const remove = button('Remove', () => { blocks.splice(index, 1); render(); queueSave(); });
    remove.className = 'danger';
    head.append(remove);
    card.append(head);

    if (def.hint) {
      const hint = document.createElement('p');
      hint.className = 'block-card__hint';
      hint.textContent = def.hint;
      card.append(hint);
    }

    Object.entries(def.fields).forEach(([key, fieldDef]) => {
      if (fieldDef.type === 'repeater') {
        if (!Array.isArray(block.data[key])) block.data[key] = [];
        card.append(repeater(fieldDef, block.data[key], (rows, inPlace) => {
          block.data[key] = rows;
          if (!inPlace) render();
          queueSave();
        }));
      } else {
        card.append(field(fieldDef, block.data[key], (value) => {
          block.data[key] = value;
          queueSave();
        }));
      }
    });

    return card;
  }

  function render() {
    list.innerHTML = '';
    if (!blocks.length) {
      const empty = document.createElement('p');
      empty.className = 'empty';
      empty.textContent = 'This page is empty. Add a block to start.';
      list.append(empty);
      return;
    }
    blocks.forEach((block, index) => list.append(renderBlock(block, index)));
  }

  // ------------------------------------------------------------ drag to sort

  let dragFrom = null;

  list.addEventListener('dragstart', (event) => {
    const card = event.target.closest('.block-card');
    if (!card) return;
    dragFrom = Number(card.dataset.index);
    card.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
  });

  list.addEventListener('dragover', (event) => {
    event.preventDefault();
    const card = event.target.closest('.block-card');
    list.querySelectorAll('.is-over').forEach((el) => el.classList.remove('is-over'));
    if (card) card.classList.add('is-over');
  });

  list.addEventListener('drop', (event) => {
    event.preventDefault();
    const card = event.target.closest('.block-card');
    if (card && dragFrom !== null) {
      move(blocks, dragFrom, Number(card.dataset.index));
      render();
      queueSave();
    }
    dragFrom = null;
  });

  list.addEventListener('dragend', () => {
    list.querySelectorAll('.is-dragging, .is-over').forEach((el) => {
      el.classList.remove('is-dragging', 'is-over');
    });
  });

  // ------------------------------------------------------------- add a block

  Object.entries(schema).forEach(([type, def]) => {
    addMenu.append(button(def.label, () => {
      blocks.push({ type: type, data: clone(defaults[type]) });
      addMenu.hidden = true;
      render();
      queueSave();
      list.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }));
  });

  addBtn.addEventListener('click', () => { addMenu.hidden = !addMenu.hidden; });

  // ----------------------------------------------------------------- saving

  let timer = null;
  let saving = false;

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
      const response = await fetch('/admin/pages/' + pageId + '/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF },
        body: JSON.stringify({
          title:   document.getElementById('f-title').value,
          section: document.getElementById('f-section').value,
          summary: document.getElementById('f-summary').value,
          status:  document.getElementById('f-status').value,
          blocks:  blocks
        })
      });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.error || 'Save failed');
      saveState.textContent = 'Saved at ' + result.saved_at;
      saveState.dataset.state = 'saved';
      preview.contentWindow.location.reload();
    } catch (error) {
      saveState.textContent = 'Not saved. Check your connection and try again.';
      saveState.dataset.state = 'error';
    } finally {
      saving = false;
    }
  }

  ['f-title', 'f-section', 'f-summary', 'f-status'].forEach((id) => {
    document.getElementById(id).addEventListener('input', queueSave);
    document.getElementById(id).addEventListener('change', queueSave);
  });

  window.addEventListener('beforeunload', (event) => {
    if (saveState.dataset.state === 'pending') {
      event.preventDefault();
      event.returnValue = '';
    }
  });

  render();
})();
