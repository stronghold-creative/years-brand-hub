/**
 * Runs inside the editor's preview iframe.
 *
 * The page itself is rendered by PHP, exactly as it publishes, so this only
 * adds a thin interaction layer over the top: hover outlines, click to select,
 * and insert points between blocks. Everything it learns is passed to the
 * editor by postMessage; it never edits content itself.
 */
(function () {
  'use strict';

  const blocks = () => Array.from(document.querySelectorAll('[data-block]'));
  let selected = null;

  // ------------------------------------------------------------ chrome

  const style = document.createElement('style');
  style.textContent = `
    [data-block] { position: relative; }
    [data-block]::after {
      content: '';
      position: absolute;
      inset: -10px -14px;
      border: 1.5px solid transparent;
      border-radius: 8px;
      pointer-events: none;
      transition:
        border-color 180ms cubic-bezier(.22, 1, .36, 1),
        background 180ms cubic-bezier(.22, 1, .36, 1);
    }
    [data-block].is-hover::after { border-color: rgba(114, 87, 183, .45); }
    [data-block].is-selected::after { border-color: #7257B7; background: rgba(114, 87, 183, .04); }
    /* The block's own children are lifted above the ::after overlay so the
       tint sits behind the content rather than over it.

       The label must be excluded. It is appended as a direct child, so without
       the :not() this rule — two classes and an element, against the label's
       one class — wins on specificity, turns position: absolute into
       position: relative, and drops the label out of its corner into normal
       flow at the end of the block. It then renders at the bottom left, which
       is exactly where it was appearing. */
    [data-block].is-selected > *:not(.ed-label) { position: relative; }

    .ed-label {
      position: absolute;
      top: -10px;
      left: -14px;
      transform: translateY(-100%);
      background: #7257B7;
      color: #fff;
      font: 500 11px/1 system-ui, sans-serif;
      letter-spacing: .02em;
      padding: 5px 8px;
      border-radius: 5px;
      pointer-events: none;
      white-space: nowrap;
      z-index: 5;
      animation: ed-label 200ms cubic-bezier(.16, 1, .3, 1) both;
    }
    @keyframes ed-label {
      from { opacity: 0; transform: translateY(calc(-100% + 4px)); }
      to   { opacity: 1; transform: translateY(-100%); }
    }

    .ed-insert {
      position: relative;
      height: 26px;
      display: grid;
      place-items: center;
      cursor: pointer;
    }
    .ed-insert::before {
      content: '';
      position: absolute;
      left: 0; right: 0;
      height: 1.5px;
      background: #7257B7;
      opacity: 0;
      transition: opacity .12s ease;
    }
    .ed-insert span {
      position: relative;
      background: #7257B7;
      color: #fff;
      font: 500 11px/1 system-ui, sans-serif;
      padding: 5px 10px;
      border-radius: 999px;
      opacity: 0;
      transform: translateY(2px);
      transition:
        opacity 140ms cubic-bezier(.22, 1, .36, 1),
        transform 180ms cubic-bezier(.22, 1, .36, 1);
    }
    .ed-insert:hover::before { opacity: 1; }
    .ed-insert:hover span { opacity: 1; transform: none; }

    body.is-editing { cursor: default; }

    /* Text that can be edited where it sits. Deliberately quiet: an outline
       around every paragraph would turn the page into a form, and the point of
       editing on the canvas is that it still looks like the page. */
    /* Some blocks put a control under their text and turn pointer events off
       on the text so clicks reach it — the colour panels do. Editable text has
       to take the click back, or it cannot be put a cursor in. */
    [data-field] { cursor: text; border-radius: 3px; pointer-events: auto; }
    [data-field]:hover {
      box-shadow: 0 0 0 1px rgba(114, 87, 183, .25);
    }
    [data-field]:focus {
      outline: 0;
      box-shadow: 0 0 0 2px rgba(114, 87, 183, .55);
    }
    /* An empty field has nothing to click, so it is given something. */
    [data-field]:empty::before {
      content: 'Write here';
      color: rgba(31, 31, 31, .35);
    }

    @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
  `;
  document.head.append(style);
  document.body.classList.add('is-editing');

  const label = document.createElement('span');
  label.className = 'ed-label';

  function send(message) {
    window.parent.postMessage(Object.assign({ from: 'preview' }, message), window.location.origin);
  }

  // ------------------------------------------------------- interaction

  function paint(index) {
    blocks().forEach((node) => {
      const isSelected = Number(node.dataset.block) === index;
      node.classList.toggle('is-selected', isSelected);
      if (isSelected) {
        node.append(label);
        label.textContent = node.dataset.blockLabel || 'Block';
      }
    });
    selected = index;
  }

  document.addEventListener('mouseover', (event) => {
    const node = event.target.closest('[data-block]');
    blocks().forEach((other) => other.classList.toggle('is-hover', other === node));
  });

  document.addEventListener('mouseleave', () => {
    blocks().forEach((node) => node.classList.remove('is-hover'));
  });

  document.addEventListener('click', (event) => {
    const insert = event.target.closest('.ed-insert');
    if (insert) {
      event.preventDefault();
      send({ action: 'insert', index: Number(insert.dataset.at) });
      return;
    }

    const node = event.target.closest('[data-block]');
    if (!node) return;

    // Links inside the preview would navigate away from the editor.
    const link = event.target.closest('a');
    if (link) event.preventDefault();

    const index = Number(node.dataset.block);
    paint(index);
    send({ action: 'select', index: index });
  });

  // The editor drives selection too, when a row in the outline is clicked.
  window.addEventListener('message', (event) => {
    if (event.origin !== window.location.origin || event.data.from !== 'editor') return;

    if (event.data.action === 'select') {
      paint(event.data.index);
      // A reload re-sends the current selection only to restore the outline,
      // and the scroll position has already been put back. Scrolling again
      // there would drag the page away from where the writer left it.
      if (event.data.quiet) return;
      const node = blocks().find((n) => Number(n.dataset.block) === event.data.index);
      if (node) node.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    if (event.data.action === 'labels') {
      blocks().forEach((node) => {
        node.dataset.blockLabel = event.data.labels[Number(node.dataset.block)] || 'Block';
      });
      if (selected !== null) paint(selected);
    }
  });

  // -------------------------------------------------------- insert points

  function addInsertPoints() {
    document.querySelectorAll('.ed-insert').forEach((el) => el.remove());
    const all = blocks();

    const make = (at) => {
      const row = document.createElement('div');
      row.className = 'ed-insert';
      row.dataset.at = String(at);
      row.innerHTML = '<span>Add a block here</span>';
      return row;
    };

    all.forEach((node, i) => node.before(make(i)));
    const last = all[all.length - 1];
    if (last) {
      last.after(make(all.length));
    } else {
      document.querySelector('main, body').append(make(0));
    }
  }

  /* ----------------------------------------------------- editing in place */

  /**
   * Text marked with data-field is edited where it sits.
   *
   * The value is read back out of the DOM rather than tracked as it is typed,
   * so what the editor stores is always exactly what is on screen. A multiline
   * field is rebuilt from its paragraphs: innerText is not dependable across
   * browsers for the blank lines between block elements, and those blank lines
   * are what paragraphs() splits on, so they have to survive the round trip
   * exactly or a body would slowly collapse into one paragraph.
   */
  function readField(node) {
    if (node.dataset.multiline === undefined) {
      // A single line. Any newline pasted in is flattened, because the field
      // it maps to cannot hold one.
      return node.innerText.replace(/\s*\n+\s*/g, ' ').trim();
    }

    const paragraphs = node.querySelectorAll(':scope > p');
    if (!paragraphs.length) {
      return node.innerText.replace(/\n{3,}/g, '\n\n').trim();
    }
    return Array.from(paragraphs)
      .map((p) => {
        // A <br> inside a paragraph is a line break, not a new paragraph.
        const html = p.innerHTML.replace(/<br\s*\/?>/gi, '\n');
        const holder = document.createElement('div');
        holder.innerHTML = html;
        return holder.innerText.replace(/ /g, ' ').trim();
      })
      .filter((text) => text !== '')
      .join('\n\n');
  }

  function editableFields() {
    return Array.from(document.querySelectorAll('[data-field]'));
  }

  function startEditing() {
    editableFields().forEach((node) => {
      node.contentEditable = 'true';
      node.spellcheck = true;

      node.addEventListener('focus', () => {
        const block = node.closest('[data-block]');
        send({ action: 'editing', editing: true, index: block ? Number(block.dataset.block) : null });
      });

      node.addEventListener('blur', () => {
        send({ action: 'editing', editing: false });
      });

      node.addEventListener('input', () => {
        const block = node.closest('[data-block]');
        if (!block) return;
        send({
          action: 'edit',
          index: Number(block.dataset.block),
          field: node.dataset.field,
          value: readField(node),
        });
      });

      // Enter in a single-line field would insert a break the field cannot
      // hold, so it ends the edit instead.
      node.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && node.dataset.multiline === undefined) {
          event.preventDefault();
          node.blur();
        }
        if (event.key === 'Escape') {
          node.blur();
        }
      });

      // Paste as text. Pasting from a document otherwise brings its markup
      // with it, none of which a block field can store.
      node.addEventListener('paste', (event) => {
        event.preventDefault();
        const text = (event.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text);
      });
    });
  }

  addInsertPoints();
  startEditing();

  /**
   * Keeps the scroll position across reloads.
   *
   * Every autosave reloads this iframe, so without this the canvas jumped back
   * to the top of the page roughly every time the writer stopped typing, which
   * made editing anything below the fold impractical.
   *
   * sessionStorage rather than a message to the editor: the value has to be
   * written as this document is going away, when there is no longer a reliable
   * chance to hear a reply.
   */
  const SCROLL_KEY = 'brandhub.canvas.' + window.location.pathname;

  function rememberScroll() {
    try { window.sessionStorage.setItem(SCROLL_KEY, String(window.scrollY)); } catch (error) {}
  }

  // pagehide fires on the reloads beforeunload can miss, and both are cheap.
  window.addEventListener('pagehide', rememberScroll);
  window.addEventListener('beforeunload', rememberScroll);

  let restored = 0;
  try {
    restored = Number(window.sessionStorage.getItem(SCROLL_KEY)) || 0;
  } catch (error) {}

  if (restored > 0) {
    // Instantly, and without smoothing: this is not a movement the writer made,
    // it is the page being put back where it was.
    window.scrollTo({ top: restored, behavior: 'instant' });
    // Images and the hero's web font change the height after first paint, so
    // the position is set again once everything has settled.
    window.addEventListener('load', function () {
      window.scrollTo({ top: restored, behavior: 'instant' });
    });
  }

  send({ action: 'ready', count: blocks().length, scrolled: restored > 0 });
})();
