/**
 * The component preview: frame height, variant switching, viewport and scheme.
 *
 * Shared by the admin screen and, from phase 4, the public component page, so it
 * finds its own elements and does nothing when they are absent — this file is
 * loaded on every admin page.
 *
 * Each piece is wrapped in feature(), as admin.js and site.js are: a series of
 * blocks in one file means one throw would otherwise stop every block after it.
 */
(function () {
  'use strict';

  function feature(name, run) {
    try {
      run();
    } catch (error) {
      console.error('components.js: ' + name + ' failed', error);
    }
  }

  // -------------------------------------------------------- unsaved changes

  /**
   * Warns before leaving a form with unsaved edits in it.
   *
   * This is deliberately not autosave. The page editor autosaves because prose is
   * typed continuously and a lost paragraph is a real loss; a component's fields
   * hold code, which is edited in deliberate passes and is broken for most of the
   * time it is being written. Autosaving that would fill the history with
   * snapshots of half-finished CSS and reload the preview into a broken state
   * every second. What autosave is actually protecting against here is closing
   * the tab with work in it, and this protects against that directly.
   *
   * It runs before the frame check below, because it applies to the whole screen
   * and not only to pages that have a preview on them.
   */
  feature('unsaved changes', function () {
    const forms = document.querySelectorAll('form[data-guard]');
    if (!forms.length) return;

    const initial = new Map();
    forms.forEach(function (form) { initial.set(form, new FormData(form)); });

    function dirty(form) {
      const before = initial.get(form);
      const now = new FormData(form);
      for (const [key, value] of now.entries()) {
        if (key === '_csrf') continue;
        if (String(before.get(key)) !== String(value)) return true;
      }
      return false;
    }

    function anyDirty() {
      let found = false;
      forms.forEach(function (form) {
        const is = dirty(form);
        form.classList.toggle('is-dirty', is);
        if (is) found = true;
      });
      return found;
    }

    forms.forEach(function (form) {
      form.addEventListener('input', anyDirty);
      form.addEventListener('change', anyDirty);
      // Submitting is not leaving with unsaved work, so the guard stands down.
      form.addEventListener('submit', function () { initial.set(form, new FormData(form)); });
    });

    window.addEventListener('beforeunload', function (event) {
      if (!anyDirty()) return;
      event.preventDefault();
      // Browsers show their own wording; returnValue is what makes them ask.
      event.returnValue = '';
    });
  });

  const frame = document.getElementById('cmp-frame');
  if (!frame) return;

  const stage = document.querySelector('[data-frame-stage]');
  let dark = false;
  let inspect = false;

  // ------------------------------------------------------------------ height

  /**
   * The frame reports its own height, because a cross-origin document cannot be
   * measured from here and guessing gives either a scrollbar or a gap.
   *
   * The origin is NOT checked, and that is correct rather than an oversight: the
   * frame is sandboxed without allow-same-origin, so it has an opaque origin and
   * its messages arrive with an origin of "null". There is nothing to compare.
   * The check that does the work is event.source against the frame's own
   * contentWindow, which another frame cannot forge. See the comment in
   * app/views/component-frame.php.
   */
  feature('frame height', function () {
    window.addEventListener('message', function (event) {
      if (event.source !== frame.contentWindow) return;

      const data = event.data;
      if (!data || data.source !== 'component-frame') return;

      // The text styles the component actually renders in, measured inside the
      // frame because only the frame can measure them.
      if (Array.isArray(data.styles)) {
        paintTextStyles(data.styles);
        return;
      }

      const height = Number(data.height);
      if (!isFinite(height) || height <= 0) return;

      // Capped, so a component with a runaway height cannot push the rest of the
      // page off the screen and make the editor unusable.
      frame.style.height = Math.min(Math.ceil(height), 4000) + 'px';
    });
  });

  // --------------------------------------------------------------- switching

  /**
   * Switching variant swaps the frame's src rather than following the link. The
   * links are real and work without this, but a reload would take whatever is
   * half-typed in the textareas below with it.
   */
  feature('variant switch', function () {
    const chips = document.querySelectorAll('[data-frame-src]');
    if (!chips.length) return;

    chips.forEach(function (chip) {
      chip.addEventListener('click', function (event) {
        // A modified click is someone deliberately opening it elsewhere.
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
        event.preventDefault();

        chips.forEach(function (other) {
          other.classList.toggle('is-on', other === chip);
          if (other === chip) {
            other.setAttribute('aria-current', 'true');
          } else {
            other.removeAttribute('aria-current');
          }
        });

        load(chip.getAttribute('data-frame-src'));
        showFor(chip.getAttribute('data-variant-id'));

        // The URL follows, so a reload or a shared link opens the same variant.
        if (window.history && window.history.replaceState) {
          window.history.replaceState(null, '', chip.getAttribute('href'));
        }
      });
    });
  });

  /**
   * Everything else that belongs to the variant on screen: its note, and its
   * code panel. Both are written out for every variant and hidden, so switching
   * costs no request — and both have to move with the frame, or the page shows
   * one variant and describes another.
   */
  function showFor(id) {
    if (!id) return;
    document.querySelectorAll('[data-variant-note], [data-variant-code]').forEach(function (node) {
      const owner = node.getAttribute('data-variant-note') || node.getAttribute('data-variant-code');
      node.hidden = owner !== String(id);
    });
  }

  /**
   * Everything that changes what the frame shows goes through here, and the path
   * it is given is always the bare one. Any existing query is dropped rather than
   * added to, or toggling dark twice would ask for ?scheme=dark?scheme=dark.
   */
  function load(src) {
    if (!src) return;
    const bare = String(src).split('?')[0];
    const query = [];
    if (dark) query.push('scheme=dark');
    if (inspect) query.push('inspect=1');
    frame.style.height = '';
    frame.src = query.length ? bare + '?' + query.join('&') : bare;
  }

  function currentSrc() {
    const on = document.querySelector('[data-frame-src].is-on');
    return on ? on.getAttribute('data-frame-src') : frame.src;
  }

  // ---------------------------------------------------------------- viewport

  /**
   * Narrowing the stage rather than the frame, so the component reflows exactly
   * as it would in a window that size. A media query inside the component reads
   * the frame's own width, which is what makes this worth doing at all.
   */
  feature('viewport toggle', function () {
    const buttons = document.querySelectorAll('[data-frame-width]');
    if (!buttons.length || !stage) return;

    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        const width = button.getAttribute('data-frame-width');
        buttons.forEach(function (other) { other.classList.toggle('is-on', other === button); });
        stage.style.maxWidth = width === 'auto' ? '' : width + 'px';
      });
    });
  });

  // ----------------------------------------------------------------- inspect

  /**
   * Measurements on hover, the way Dev Mode gives them.
   *
   * It reloads the frame rather than toggling something inside it, because the
   * frame is cross-origin and cannot be reached into — and because the inspector
   * should not be shipped to a reader who never asks for it.
   */
  feature('inspect toggle', function () {
    const button = document.querySelector('[data-frame-inspect]');
    if (!button) return;

    button.addEventListener('click', function () {
      inspect = !inspect;
      button.classList.toggle('is-on', inspect);
      button.setAttribute('aria-pressed', inspect ? 'true' : 'false');
      load(currentSrc());
    });
  });

  /**
   * The text styles panel: the distinct type the component is actually set in.
   *
   * Written as text, never as markup — the values come from inside a frame that
   * renders author-supplied HTML, and a sample string is whatever that HTML
   * happened to contain.
   */
  function paintTextStyles(styles) {
    const mount = document.querySelector('[data-text-styles]');
    if (!mount) return;

    const wrap = mount.closest('[data-text-styles-wrap]') || mount;
    mount.innerHTML = '';
    if (!styles.length) {
      wrap.hidden = true;
      return;
    }
    wrap.hidden = false;

    styles.forEach(function (style) {
      const row = document.createElement('div');
      row.className = 'typestyle';

      const sample = document.createElement('p');
      sample.className = 'typestyle__sample';
      sample.textContent = style.sample || 'Aa';
      sample.style.fontFamily = style.family;
      sample.style.fontSize = style.size;
      sample.style.fontWeight = style.weight;
      sample.style.color = style.colour;

      const spec = document.createElement('p');
      spec.className = 'typestyle__spec';
      spec.textContent = [
        style.family,
        style.size + ' / ' + style.weight,
        style.line !== 'normal' ? style.line + ' line' : null,
        style.tracking !== 'normal' ? style.tracking + ' tracking' : null,
        style.colour,
      ].filter(Boolean).join('  ·  ');

      row.append(sample, spec);
      mount.append(row);
    });
  }

  // ------------------------------------------------------------------ scheme

  /**
   * Dark mode is a reload of the frame with ?scheme=dark rather than a class,
   * because the frame is cross-origin and cannot be reached into. The route
   * honours only the two values.
   */
  feature('scheme toggle', function () {
    const button = document.querySelector('[data-frame-scheme]');
    if (!button) return;

    button.addEventListener('click', function () {
      dark = !dark;
      button.classList.toggle('is-on', dark);
      button.setAttribute('aria-pressed', dark ? 'true' : 'false');
      load(currentSrc());
    });
  });
})();
