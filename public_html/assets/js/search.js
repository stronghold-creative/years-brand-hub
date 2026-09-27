/**
 * Search.
 *
 * Guidelines are opened mid-task by someone who half-remembers a rule, so the
 * fastest path from "where is the clear space rule" to reading it is the whole
 * point. The index arrives once and is filtered here, which is why typing
 * shows results immediately rather than a tenth of a second later.
 *
 * Requires UIMotion. Enhances a plain link: without JavaScript the trigger is
 * still a link to the index, which is the nearest honest thing to search.
 */
(function () {
  'use strict';

  const triggers = document.querySelectorAll('[data-search]');
  if (!triggers.length) return;

  let index = null;      // the pages, once fetched
  let loading = null;    // the in-flight fetch, so two opens share one
  let panel = null;      // the open dialog, or null

  function load() {
    if (index) return Promise.resolve(index);
    if (loading) return loading;
    loading = fetch('/search.json', { headers: { Accept: 'application/json' } })
      .then((response) => response.json())
      .then((data) => {
        index = data.pages || [];
        return index;
      })
      .catch(() => {
        loading = null;
        return null;
      });
    return loading;
  }

  /* ------------------------------------------------------------- matching */

  /**
   * Ranked matches for a term.
   *
   * A title is worth more than a heading and a heading more than body text,
   * because someone typing "colour" wants the Colour page before they want
   * the paragraph on some other page that mentions colour in passing.
   */
  function search(term) {
    const q = term.trim().toLowerCase();
    if (q.length < 2 || !index) return [];

    const words = q.split(/\s+/).filter(Boolean);
    const hits = [];

    index.forEach((page) => {
      const title = page.title.toLowerCase();
      const section = page.section.toLowerCase();
      const summary = (page.summary || '').toLowerCase();

      // Every word has to appear somewhere, so "clear space" does not match a
      // page that only says "clear".
      const haystack = title + ' ' + section + ' ' + summary + ' ' + page.text;
      if (!words.every((word) => haystack.includes(word))) return;

      let score = 0;
      if (title === q) score += 100;
      if (title.startsWith(q)) score += 50;
      if (title.includes(q)) score += 30;
      if (section.includes(q)) score += 10;
      if (summary.includes(q)) score += 5;

      hits.push({
        kind: 'page',
        title: page.title,
        where: page.section,
        href: '/s/' + slug(page.section) + '#' + page.slug,
        draft: page.draft,
        score: score + 1,
      });

      // Headings inside the page are their own results: landing on the page
      // is not the same as landing on the rule.
      (page.marks || []).forEach((mark) => {
        const heading = mark.title.toLowerCase();
        if (!words.every((word) => heading.includes(word))) return;
        hits.push({
          kind: 'mark',
          title: mark.title,
          where: page.title,
          href: '/s/' + slug(page.section) + '#' + (mark.anchor || page.slug),
          draft: page.draft,
          score: (heading.startsWith(q) ? 40 : 20) + 1,
        });
      });
    });

    return hits.sort((a, b) => b.score - a.score).slice(0, 12);
  }

  // Matches slugify() in app/lib/http.php, so the links it builds are the same
  // links the rail builds.
  function slug(text) {
    return String(text).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'page';
  }

  /* -------------------------------------------------------------- the box */

  function open() {
    if (panel) return;

    const scrim = document.createElement('div');
    scrim.className = 'search';
    scrim.innerHTML =
      '<div class="search__panel" role="dialog" aria-modal="true" aria-label="Search the guidelines">' +
        '<div class="search__field">' +
          '<input class="search__input" type="search" autocomplete="off" spellcheck="false"' +
                ' placeholder="Search the guidelines" aria-label="Search the guidelines"' +
                ' role="combobox" aria-expanded="false" aria-controls="search-results" aria-autocomplete="list">' +
          '<kbd class="search__esc">Esc</kbd>' +
        '</div>' +
        '<div class="search__results" id="search-results" role="listbox"></div>' +
        '<p class="search__hint"><kbd>↑</kbd><kbd>↓</kbd> to move, <kbd>Enter</kbd> to open</p>' +
      '</div>';

    document.body.append(scrim);
    document.body.classList.add('search-open');
    panel = scrim;

    const box = scrim.querySelector('.search__panel');
    const input = scrim.querySelector('.search__input');
    const list = scrim.querySelector('.search__results');
    UIMotion.enterDialog(box, scrim);

    let results = [];
    let at = 0;

    function draw() {
      list.innerHTML = '';
      input.setAttribute('aria-expanded', String(results.length > 0));

      if (!input.value.trim()) {
        list.innerHTML = '<p class="search__empty">Type to search every page.</p>';
        return;
      }
      if (!results.length) {
        list.innerHTML = '<p class="search__empty">Nothing matches that.</p>';
        return;
      }

      results.forEach((hit, i) => {
        const item = document.createElement('a');
        item.className = 'search__hit' + (i === at ? ' is-on' : '');
        item.href = hit.href;
        item.setAttribute('role', 'option');
        item.setAttribute('aria-selected', String(i === at));
        item.innerHTML =
          '<span class="search__what"></span>' +
          '<span class="search__where"></span>' +
          (hit.draft ? '<span class="search__draft">Draft</span>' : '');
        item.querySelector('.search__what').textContent = hit.title;
        item.querySelector('.search__where').textContent =
          hit.kind === 'mark' ? 'in ' + hit.where : hit.where;
        item.addEventListener('mouseenter', () => { at = i; mark(); });
        list.append(item);
      });
    }

    // Only the highlight changes as the arrows move, so the list is not
    // rebuilt on every keypress.
    function mark() {
      Array.from(list.children).forEach((item, i) => {
        item.classList.toggle('is-on', i === at);
        if (item.setAttribute) item.setAttribute('aria-selected', String(i === at));
      });
      const current = list.children[at];
      if (current && current.scrollIntoView) {
        current.scrollIntoView({ block: 'nearest' });
      }
    }

    function run() {
      results = search(input.value);
      at = 0;
      draw();
    }

    input.addEventListener('input', run);

    input.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        if (!results.length) return;
        event.preventDefault();
        at = (at + (event.key === 'ArrowDown' ? 1 : -1) + results.length) % results.length;
        mark();
        return;
      }
      if (event.key === 'Enter' && results[at]) {
        event.preventDefault();
        // Same document: close first, or the scroll lock swallows the jump.
        const href = results[at].href;
        close().then(() => { window.location.href = href; });
      }
    });

    scrim.addEventListener('click', (event) => { if (event.target === scrim) close(); });
    document.addEventListener('keydown', onKey);

    draw();
    input.focus();

    // Fetched on first open rather than on every page load: it is only needed
    // by someone who actually searches.
    load().then((pages) => {
      if (!panel) return;
      if (!pages) {
        list.innerHTML = '<p class="search__empty">Search is not available right now.</p>';
        return;
      }
      if (input.value.trim()) run();
    });
  }

  function close() {
    if (!panel) return Promise.resolve();
    const going = panel;
    panel = null;
    document.removeEventListener('keydown', onKey);
    document.body.classList.remove('search-open');
    return UIMotion.settled(UIMotion.exitDialog(going.querySelector('.search__panel'), going))
      .then(() => going.remove());
  }

  function onKey(event) {
    if (event.key === 'Escape') { event.stopPropagation(); close(); }
  }

  /* ------------------------------------------------------------- openers */

  triggers.forEach((trigger) => {
    trigger.addEventListener('click', (event) => {
      event.preventDefault();
      open();
    });
  });

  document.addEventListener('keydown', (event) => {
    if (panel) return;

    const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(event.target.tagName)
      || event.target.isContentEditable;

    // The shortcut everyone already has in their fingers, and a bare slash for
    // anyone who does not. Neither fires while something is being typed into.
    const shortcut = (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k';
    const slash = event.key === '/' && !event.metaKey && !event.ctrlKey && !typing;

    if (shortcut || slash) {
      event.preventDefault();
      open();
    }
  });

  // Warmed on the first hint of intent, so the index is usually there before
  // the box is.
  triggers.forEach((trigger) => {
    trigger.addEventListener('pointerenter', load, { once: true });
  });
})();
