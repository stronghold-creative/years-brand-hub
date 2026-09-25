# Years brand hub: handover

Everything needed to pick this up cold, including in a new conversation with no
memory of the previous one. Last updated 25 September 2026.

---

## 1. What it is

An internal brand guidelines site for Years (a fresh dog food brand), plus the
CMS that edits it. Custom PHP, no framework, no Composer, no build step, on
Hostinger shared hosting.

Live at `https://lightyellow-woodcock-213680.hostingersite.com`, still on
Hostinger's temporary preview domain.

---

## 2. Where things are

```
.github/workflows/deploy.yml   FTPS deploy on push to main
app/
  bootstrap.php                loads every file in app/lib, then config
  config.php                   SERVER ONLY, gitignored, database credentials
  routes.php                   the whole router, top to bottom, first match wins
  lib/                         11 files, all auto-loaded
    assets.php                 uploads, image variants, bundles, serving
    auth.php                   sessions, roles, sign-in throttling
    blocks.php                 block definitions, sanitising, rendering, contrast
    db.php                     PDO helpers
    http.php                   escaping, CSRF, redirects, asset_url()
    icons.php                  inline SVG icon sets
    migrate.php                pending and applied migrations
    settings.php               settings, sections, logo, areas, export
    styles.php                 global appearance tokens
    versions.php               version recording, collapsing and pruning
    view.php                   template rendering
  blocks/                      24 renderers, one per block type
  views/                       16 templates, public and admin
db/                            8 migrations, applied in filename order
public_html/                   the only web-accessible directory
storage/                       uploads and caches, never deployed
tools/                         setup, migrate, create-user, export
docs/HANDOVER.md               this file
```

The document root is the **parent** of `public_html`, so `app/` and `storage/`
are unreachable over HTTP. `FTP_REMOTE_DIR` is `/`, not `/public_html`.

---

## 3. The content model

A page row holds its blocks as JSON. Each block is:

```json
{ "type": "rich_text", "data": {}, "settings": {}, "options": {} }
```

- **data** is content, shaped by `block_types()` in `lib/blocks.php`
- **settings** is placement, shared by all blocks: width, background, spacing,
  rule, anchor
- **options** are per-type appearance, from `block_options($type)`

`sanitise_blocks()` strips anything not in those definitions before saving, so
stored JSON can only contain known keys. A new block type needs an entry in
`block_types()` plus a renderer in `app/blocks/`. **No migration.**

**Inheritance:** an option set to `inherit` falls back to the matching global
setting through `block_option($options, $key, $fallback, $siteKey)`. That is
what makes Appearance in the admin move every block not deliberately overridden.

The 24 block types: rich_text, colour_palette, type_specimen, do_dont, image,
image_grid, comparison_table, download_list, callout, clearspace,
spacing_scale, icon_grid, contrast_pairs, misuse_gallery, spec_table,
link_list, contacts, checklist, video, tabs, accordion, code, steps, quote.

---

## 4. The editor

Three columns: an **outline** (navigation only, drag to reorder), a **canvas**
(the real page, rendered by PHP, clickable), and an **inspector** (Content,
Appearance, Placement for the selection, or page settings when nothing is
selected).

The canvas is an iframe at `/admin/pages/{id}/preview?edit=1`. It loads
`preview-bridge.js`, which reports clicks to the editor by postMessage.
`render_blocks($blocks, true)` tags each block with its index; this happens only
in edit mode, never on the public site. Both sides validate origin.

Saving reloads the iframe. Autosave fires 700ms after typing stops.

---

## 5. Decisions, and why

| Decision | Reason |
|---|---|
| Custom PHP | Hostinger shared plan has no Node; Statamic charges per editor |
| Blocks as JSON on the page row | New block types need no migration |
| Material 3 for the admin only | Back office is a work surface; the public site is hand-crafted |
| M3 written in CSS, not the Material library | No build step, no CDN dependency |
| Plus Jakarta Sans for the interface | Years' own faces are documented as content, not used as chrome |
| Server-rendered editor canvas | One implementation, so the preview cannot drift |
| Two width settings | 72rem container for grids and tables, 70ch measure for prose |
| Sections render as one scrolling page | Guidelines are read in sequence more than looked up |
| Shopify storefront | Components, when built, are Liquid sections, not React |

---

## 6. Traps, every one of which has bitten

**MySQL escapes backslashes in string literals.** JSON written into a migration
needs `'` doubled *and* `\` doubled, or `\n` becomes a real newline and the JSON
is invalid. This silently broke a whole page. Test by simulating MySQL's
unescaping, not by running the SQL in SQLite, which does not escape backslashes
and hides the bug entirely.

**A later CSS rule with the same specificity silently wins.** A stray
`.masthead { position: relative }` cancelled a `position: sticky` declared
earlier, so the header was never sticky and two rounds of JavaScript fixes
changed nothing. Check for duplicate properties on a selector after CSS work.

**`site.js` is a series of immediately-invoked blocks.** One throw stops every
block after it. Each feature is now wrapped in `feature(name, fn)`, which
catches and logs. Keep new features inside that wrapper.

**`.m3-state` sets `isolation: isolate`,** so every row is its own stacking
context and a menu inside one cannot paint above later rows. Raise the row.

**`[hidden]` loses to any class that sets `display`.**

**A state expressed as different markup will drift.** The rail styled the
current section differently because it rendered a `<span>` where the others
rendered an `<a>`. Express state as a class on identical markup.

**mbstring is not guaranteed** on the host. Use `str_trim_to` and `str_initial`
from `http.php`.

**Dot-files are invisible in zips and in Finder.** `public_html/.htaccess` was
handed over three times without ever being seen. Call it out explicitly.

**Deploy order matters.** If a change needs a migration: push, wait for the
green run, then Settings, Apply updates.

---

## 7. Conventions

- Every write route calls `csrf_check()`; every admin route calls
  `require_editor()` or `require_admin()`
- Every query uses bound parameters
- All output goes through `e()`; blocks never render raw HTML
- Only `http` and `https` URLs become links; embed URLs are constructed, never
  passed through
- Destructive actions use `data-confirm`, handled centrally in `admin.js`
- Asset URLs go through `asset_url()`, which appends the file's modification
  time, so a deploy always beats the cache
- Motion uses four durations and three curves defined in both stylesheets;
  never write a literal duration
- British English throughout

---

## 8. State of the build

**Working:** deploy pipeline, three-column editor, 24 block types, 26 numeric
appearance settings, asset library with generated image variants and gated
downloads, sections as scrolling pages, version history with collapsing and
pruning, people and roles, settings, logo, area toggle, backup export, sign-in
throttling, compact sticky header, mobile slide-out rail.

**Content:** 30 pages scaffolded as drafts. One written: Tone of voice.

**Not built:** search, the Components area beyond a placeholder, inline text
editing on the canvas, motion page content, type exports in `/tokens.css`,
password reset, pagination (deliberately deferred).

---

## 9. Outstanding jobs

1. **Write the pages.** The only thing on the critical path; the build is well
   ahead of the content.
2. Re-upload or Replace existing images so variants are generated; variants are
   only made on upload.
3. Self-host Plus Jakarta Sans: four woff2 files (400, 500, 600, 700) into
   `public_html/assets/fonts/`, named `plus-jakarta-sans-400.woff2` and so on.
   The site then stops loading Google Fonts automatically.
4. Add `MoreYears-Regular.woff2` so the Typography page can show real specimens.
5. Turn `debug` off in `app/config.php` on the server.
6. Decide the real domain.
7. Deferred from the audit: section page caching, password reset, `/tokens.css`
   type exports, search.

---

## 10. Checks worth running after any change

```bash
find app public_html tools -name '*.php' -print0 | xargs -0 -n1 php -l
for f in public_html/assets/js/*.js; do node --check "$f"; done
```

Plus, as ad-hoc scripts: render every block type with empty data; render every
view; confirm every CSS class used in markup or JS has a rule; confirm every
block option value has a rule; check no property is declared twice on the same
selector; check braces balance after any bulk CSS edit.

Several bugs here rendered correct HTML and still looked wrong, so markup tests
alone are not enough.

---

## 11. Access

- Hostinger hPanel: database and user are both `u675995716_brandhub`
- Deploy secrets in GitHub: `FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`,
  `FTP_REMOTE_DIR`
- Admin accounts at `/admin/users`; `tools/setup.php` only runs when no admin
  exists
