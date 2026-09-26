/**
 * Motion, in one place.
 *
 * Every animation in this project comes from here, so the public site and the
 * admin move the same way. Durations and curves match the CSS custom
 * properties exactly; where a thing can be animated in CSS it still is, and
 * this layer covers what CSS cannot do:
 *
 *   - elements leaving, which CSS cannot animate because the element is gone
 *   - lists reordering, which needs measuring before and after
 *   - springs, which have no CSS equivalent
 *
 * Requires vendor/motion.js, which exposes window.Motion.
 */
window.UIMotion = (function () {
  'use strict';

  const M = window.Motion;
  const available = !!(M && M.animate);

  // Matches --d-* and --e-* in the stylesheets. Seconds here, milliseconds there.
  const T = {
    swift: 0.12,
    base:  0.18,
    calm:  0.26,
    slow:  0.42,
  };

  const E = {
    out:   [0.22, 1, 0.36, 1],
    expo:  [0.16, 1, 0.3, 1],
    inout: [0.65, 0, 0.35, 1],
  };

  // One spring, used wherever something is dragged or pressed, so physical
  // movement feels consistent rather than tuned per component.
  const SPRING = { type: 'spring', stiffness: 520, damping: 38, mass: 0.7 };

  const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /** Animations collapse to nothing when the person has asked for less motion. */
  function options(extra) {
    const base = Object.assign({ duration: T.base, ease: E.out }, extra || {});
    if (reduced()) {
      return Object.assign({}, base, { duration: 0, delay: 0 });
    }
    return base;
  }

  function animate(target, keyframes, extra) {
    if (!available || !target) {
      return { finished: Promise.resolve() };
    }
    return M.animate(target, keyframes, options(extra));
  }

  /**
   * Waits for an animation, but never indefinitely.
   *
   * An animation only advances while the page is being painted, so in a tab
   * that is hidden or behind another window it never finishes and its promise
   * never resolves. Anything that waits on one before removing an element or
   * closing a panel therefore stops: the menu stays open, the block is not
   * deleted, and nothing says why.
   *
   * Use this wherever a promise gates a change of state, rather than .finished
   * directly. The animation is decoration; the change is the point.
   */
  function settled(controls, after) {
    const finished = (controls && controls.finished) || Promise.resolve();
    const limit = (after || T.slow) * 1000 + 120;
    return Promise.race([
      finished,
      new Promise(function (resolve) { window.setTimeout(resolve, limit); }),
    ]);
  }

  /* ----------------------------------------------------------- appearing */

  /**
   * Something arriving: a short rise, never a plain fade.
   *
   * Movement is expressed with Motion's own x, y and scale properties rather
   * than a transform string. Motion reads the keyword "none" inside a
   * transform as zero, so an animation ending at "none" ends at scale(0) and
   * the element disappears instead of arriving.
   */
  function enter(target, extra) {
    return animate(target, { opacity: [0, 1], y: [8, 0] },
      Object.assign({ duration: T.calm, ease: E.expo }, extra));
  }

  /** A dialog: the same idea with a touch of scale, so it reads as arriving. */
  function enterDialog(panel, scrim) {
    if (scrim) animate(scrim, { opacity: [0, 1] }, { duration: T.base });
    return animate(panel,
      { opacity: [0, 1], y: [10, 0], scale: [0.985, 1] },
      { duration: T.calm, ease: E.expo });
  }

  /**
   * Something leaving. CSS cannot do this at all: once the element is removed
   * there is nothing left to transition. The caller waits, then removes it.
   */
  function exit(target, extra) {
    return animate(target, { opacity: [1, 0], y: [0, 6] },
      Object.assign({ duration: T.swift, ease: E.inout }, extra));
  }

  function exitDialog(panel, scrim) {
    if (scrim) animate(scrim, { opacity: [1, 0] }, { duration: T.swift });
    return animate(panel,
      { opacity: [1, 0], y: [0, 6], scale: [1, 0.99] },
      { duration: T.swift, ease: E.inout });
  }

  /** A list arriving, each item a beat behind the one before it. */
  function enterList(targets, extra) {
    if (!available || !targets || !targets.length) return { finished: Promise.resolve() };
    const stagger = reduced() ? 0 : M.stagger(0.03);
    return M.animate(targets,
      { opacity: [0, 1], y: [6, 0] },
      Object.assign({ duration: T.base, ease: E.out, delay: stagger }, extra));
  }

  /* ---------------------------------------------------------- reordering */

  /**
   * Animates a list settling into a new order.
   *
   * Measure where everything is, let the caller rearrange the DOM, then move
   * each item from where it used to be to where it now is. This is the one
   * thing that genuinely needs a library: CSS cannot animate a layout change,
   * only a property change.
   */
  function reorder(container, selector, rearrange) {
    if (!available || !container || reduced()) {
      rearrange();
      return;
    }

    const before = new Map();
    container.querySelectorAll(selector).forEach(function (node) {
      const key = node.dataset.key || node.dataset.index;
      if (key !== undefined) before.set(key, node.getBoundingClientRect().top);
    });

    rearrange();

    container.querySelectorAll(selector).forEach(function (node) {
      const key = node.dataset.key || node.dataset.index;
      const was = before.get(key);
      const now = node.getBoundingClientRect().top;

      if (was === undefined) {
        // New to the list, so it arrives rather than moves.
        M.animate(node, { opacity: [0, 1], y: [8, 0] }, { duration: T.base, ease: E.out });
        return;
      }
      const delta = was - now;
      if (Math.abs(delta) < 1) return;

      M.animate(node, { y: [delta, 0] }, SPRING);
    });
  }

  /* -------------------------------------------------------------- panels */

  /** A drawer or panel sliding in from an edge. */
  function slideIn(target, from) {
    const axis = from === 'left' ? '-100%' : '100%';
    return animate(target, { x: [axis, '0%'] }, { duration: T.slow, ease: E.inout });
  }

  function slideOut(target, to) {
    const axis = to === 'left' ? '-100%' : '100%';
    return animate(target, { x: ['0%', axis] }, { duration: T.calm, ease: E.inout });
  }

  /** Content swapping inside a fixed frame, as tabs do. */
  function swap(outgoing, incoming) {
    if (outgoing) animate(outgoing, { opacity: [1, 0] }, { duration: 0.08 });
    return animate(incoming, { opacity: [0, 1], y: [6, 0] },
      { duration: T.base, ease: E.out });
  }

  /** A press, given weight rather than a linear scale. */
  function press(target, down) {
    return animate(target, { scale: down ? 0.97 : 1 }, SPRING);
  }

  // Lets the stylesheets fall back to their own keyframes if the library
  // failed to load for any reason.
  if (!available) {
    document.documentElement.classList.add('no-motion');
  }

  return {
    ok: available,
    durations: T,
    easings: E,
    spring: SPRING,
    reduced: reduced,
    animate: animate,
    settled: settled,
    enter: enter,
    enterDialog: enterDialog,
    enterList: enterList,
    exit: exit,
    exitDialog: exitDialog,
    reorder: reorder,
    slideIn: slideIn,
    slideOut: slideOut,
    swap: swap,
    press: press,
  };
})();
