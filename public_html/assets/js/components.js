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

  const frame = document.getElementById('cmp-frame');
  if (!frame) return;

  const stage = document.querySelector('[data-frame-stage]');
  let dark = false;

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

        // The URL follows, so a reload or a shared link opens the same variant.
        if (window.history && window.history.replaceState) {
          window.history.replaceState(null, '', chip.getAttribute('href'));
        }
      });
    });
  });

  /**
   * Everything that changes what the frame shows goes through here, and the path
   * it is given is always the bare one. Any existing query is dropped rather than
   * added to, or toggling dark twice would ask for ?scheme=dark?scheme=dark.
   */
  function load(src) {
    if (!src) return;
    const bare = String(src).split('?')[0];
    frame.style.height = '';
    frame.src = dark ? bare + '?scheme=dark' : bare;
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
