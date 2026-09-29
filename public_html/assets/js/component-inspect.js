/**
 * Inspecting a component in its preview frame.
 *
 * Figma's Dev Mode gives a developer the measurements by hovering: the size of a
 * thing, its padding, the gap between it and the next thing, the type it is set
 * in. A component library that shows the component and then makes someone read
 * CSS to find those numbers is asking them to do by hand what they had for free.
 *
 * This runs INSIDE the sandboxed frame, because that is the only place the real
 * DOM exists — the parent is a different origin and cannot measure anything in
 * here. It reads; it never changes the component. Everything it draws is in a
 * container of its own, outside the component's tree, so no measurement can be
 * of the measuring apparatus.
 *
 * Loaded only when the frame is asked for with ?inspect=1.
 */
(function () {
  'use strict';

  const UI = document.createElement('div');
  UI.className = 'ci';
  UI.setAttribute('aria-hidden', 'true');

  const style = document.createElement('style');
  style.textContent = `
    .ci { position: fixed; inset: 0; pointer-events: none; z-index: 2147483647; font: 500 11px/1.35 system-ui, sans-serif; }
    .ci__box { position: absolute; outline: 1px solid #7257B7; }
    /* Padding is drawn inside the element and margin outside it, the way every
       developer tool draws a box model — the convention is worth more than any
       colour we could invent. */
    .ci__pad { position: absolute; background: rgba(114, 87, 183, .18); }
    .ci__mar { position: absolute; background: rgba(240, 150, 30, .16); }
    .ci__tag {
      position: absolute;
      background: #7257B7; color: #fff;
      padding: 3px 6px; border-radius: 4px;
      white-space: nowrap;
    }
    .ci__gap {
      position: absolute;
      display: grid; place-items: center;
      background: rgba(114, 87, 183, .14);
    }
    .ci__gap span {
      background: #7257B7; color: #fff;
      padding: 1px 5px; border-radius: 3px; font-size: 10px;
    }
    .ci__panel {
      position: fixed; right: 8px; bottom: 8px;
      max-width: 17rem;
      background: rgba(20, 18, 24, .92); color: #fff;
      padding: 8px 10px; border-radius: 6px;
      display: grid; gap: 2px;
    }
    .ci__panel b { color: #C7B6F0; font-weight: 600; }
    .ci__panel div { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  `;

  document.head.append(style);
  document.body.append(UI);

  /** Everything drawn is torn down and redrawn; nothing accumulates. */
  function clear() { UI.innerHTML = ''; }

  function add(className, rect, extra) {
    const node = document.createElement('div');
    node.className = className;
    node.style.left = rect.left + 'px';
    node.style.top = rect.top + 'px';
    node.style.width = Math.max(0, rect.width) + 'px';
    node.style.height = Math.max(0, rect.height) + 'px';
    if (extra) Object.assign(node.style, extra);
    UI.append(node);
    return node;
  }

  const px = (v) => Math.round(parseFloat(v) || 0);

  /** A length as a developer would write it down. */
  function size(n) { return Math.round(n * 10) / 10; }

  function describe(el, cs) {
    const out = [];
    const rect = el.getBoundingClientRect();

    out.push(['Size', size(rect.width) + ' × ' + size(rect.height)]);

    const pad = [cs.paddingTop, cs.paddingRight, cs.paddingBottom, cs.paddingLeft].map(px);
    if (pad.some((n) => n > 0)) {
      out.push(['Padding', pad.every((n) => n === pad[0]) ? pad[0] + 'px' : pad.join(' ') + 'px']);
    }

    const gap = px(cs.rowGap) || px(cs.columnGap);
    if (gap > 0 && (cs.display.includes('flex') || cs.display.includes('grid'))) {
      out.push(['Gap', (px(cs.rowGap) === px(cs.columnGap))
        ? gap + 'px'
        : px(cs.rowGap) + ' / ' + px(cs.columnGap) + 'px']);
    }

    const radius = px(cs.borderTopLeftRadius);
    if (radius > 0) out.push(['Radius', radius + 'px']);
    if (px(cs.borderTopWidth) > 0) out.push(['Border', cs.borderTopWidth + ' ' + cs.borderTopStyle]);

    // Type, but only where the element actually holds text of its own. A wrapper
    // inheriting a font is not a type decision anyone made.
    const ownText = Array.from(el.childNodes)
      .some((n) => n.nodeType === Node.TEXT_NODE && n.nodeValue.trim() !== '');
    if (ownText) {
      out.push(['Font', cs.fontFamily.split(',')[0].replace(/["']/g, '')]);
      out.push(['Size', px(cs.fontSize) + 'px / ' + cs.fontWeight]);
      out.push(['Line', cs.lineHeight === 'normal' ? 'normal' : px(cs.lineHeight) + 'px']);
      out.push(['Colour', hex(cs.color)]);
    }

    const bg = cs.backgroundColor;
    if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') {
      out.push(['Background', hex(bg)]);
    }

    return out;
  }

  /** rgb() to hex, because a spec is written in hex and read back in hex. */
  function hex(colour) {
    const m = String(colour).match(/rgba?\(([^)]+)\)/);
    if (!m) return String(colour);
    const parts = m[1].split(',').map((n) => parseFloat(n));
    const to = (n) => Math.max(0, Math.min(255, Math.round(n))).toString(16).padStart(2, '0');
    const out = '#' + to(parts[0]) + to(parts[1]) + to(parts[2]);
    return parts.length > 3 && parts[3] < 1 ? out + ' at ' + Math.round(parts[3] * 100) + '%' : out;
  }

  function paint(el) {
    clear();
    if (!el || el === document.body || el === document.documentElement) return;

    const cs = getComputedStyle(el);
    const r = el.getBoundingClientRect();

    // Margin, outside.
    const m = [cs.marginTop, cs.marginRight, cs.marginBottom, cs.marginLeft].map(px);
    if (m.some((n) => n > 0)) {
      add('ci__mar', { left: r.left - m[3], top: r.top - m[0], width: r.width + m[1] + m[3], height: m[0] });
      add('ci__mar', { left: r.left - m[3], top: r.bottom, width: r.width + m[1] + m[3], height: m[2] });
      add('ci__mar', { left: r.left - m[3], top: r.top, width: m[3], height: r.height });
      add('ci__mar', { left: r.right, top: r.top, width: m[1], height: r.height });
    }

    // Padding, inside.
    const p = [cs.paddingTop, cs.paddingRight, cs.paddingBottom, cs.paddingLeft].map(px);
    if (p.some((n) => n > 0)) {
      add('ci__pad', { left: r.left, top: r.top, width: r.width, height: p[0] });
      add('ci__pad', { left: r.left, top: r.bottom - p[2], width: r.width, height: p[2] });
      add('ci__pad', { left: r.left, top: r.top + p[0], width: p[3], height: r.height - p[0] - p[2] });
      add('ci__pad', { left: r.right - p[1], top: r.top + p[0], width: p[1], height: r.height - p[0] - p[2] });
    }

    add('ci__box', r);

    // The gap to the next sibling, drawn where it is. This is the measurement
    // Figma shows between every pair of elements and the one most often wanted.
    const next = el.nextElementSibling;
    if (next) {
      const n = next.getBoundingClientRect();
      const vertical = n.top >= r.bottom - 1;
      const distance = vertical ? n.top - r.bottom : n.left - r.right;
      if (distance > 0.5) {
        const band = vertical
          ? { left: r.left, top: r.bottom, width: r.width, height: distance }
          : { left: r.right, top: r.top, width: distance, height: r.height };
        add('ci__gap', band).innerHTML = '<span>' + Math.round(distance) + '</span>';
      }
    }

    // The label, above the element unless there is no room, in which case below.
    const tag = document.createElement('div');
    tag.className = 'ci__tag';
    tag.textContent = el.tagName.toLowerCase()
      + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/)[0] : '')
      + '  ' + size(r.width) + ' × ' + size(r.height);
    tag.style.left = r.left + 'px';
    tag.style.top = (r.top > 22 ? r.top - 20 : r.bottom + 4) + 'px';
    UI.append(tag);

    const panel = document.createElement('div');
    panel.className = 'ci__panel';
    describe(el, cs).forEach(([label, value]) => {
      const row = document.createElement('div');
      row.innerHTML = '<b></b> <span></span>';
      row.querySelector('b').textContent = label;
      row.querySelector('span').textContent = value;
      panel.append(row);
    });
    UI.append(panel);
  }

  document.addEventListener('mouseover', function (event) {
    if (UI.contains(event.target)) return;
    paint(event.target);
  });
  document.addEventListener('mouseleave', clear);
  window.addEventListener('blur', clear);
  // Anything that moves the page invalidates every rectangle drawn.
  window.addEventListener('scroll', clear, true);
  window.addEventListener('resize', clear);

  /**
   * The text styles actually used, sent to the page around the frame.
   *
   * The same list Figma shows: every distinct combination of family, size,
   * weight, line height and colour, read off the rendered component rather than
   * off the CSS — so it reports what the component *is*, cascade and all, not
   * what its stylesheet asks for.
   */
  function textStyles() {
    const seen = new Map();

    document.querySelectorAll('body *').forEach(function (el) {
      if (UI.contains(el)) return;
      const hasText = Array.from(el.childNodes)
        .some((n) => n.nodeType === Node.TEXT_NODE && n.nodeValue.trim() !== '');
      if (!hasText) return;

      const cs = getComputedStyle(el);
      const key = [cs.fontFamily, cs.fontSize, cs.fontWeight, cs.lineHeight, cs.letterSpacing, cs.color].join('|');
      if (seen.has(key)) return;

      seen.set(key, {
        sample: el.textContent.trim().slice(0, 40),
        family: cs.fontFamily.split(',')[0].replace(/["']/g, ''),
        size: px(cs.fontSize) + 'px',
        weight: cs.fontWeight,
        line: cs.lineHeight === 'normal' ? 'normal' : px(cs.lineHeight) + 'px',
        tracking: cs.letterSpacing === 'normal' ? 'normal' : cs.letterSpacing,
        colour: hex(cs.color),
      });
    });

    return Array.from(seen.values());
  }

  function report() {
    // To '*' with no origin check, for the reason set out in the frame: a
    // sandboxed frame has an opaque origin, so there is nothing to compare. The
    // parent checks event.source instead, and nothing here is sensitive.
    parent.postMessage({ source: 'component-frame', styles: textStyles() }, '*');
  }

  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(report);
  } else {
    window.addEventListener('load', report);
  }
})();
