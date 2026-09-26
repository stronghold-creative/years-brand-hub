# Years brand hub: handover

Everything needed to pick this up cold, including in a new conversation with no
memory of the previous one. Last updated 26 September 2026.

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
  blocks/                      25 renderers, one per block type
  views/                       15 templates, public and admin
db/                            8 migrations, applied in filename order
public_html/                   the only web-accessible directory
storage/                       uploads and caches, never deployed
tools/                         setup, migrate, create-user, export, checks
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

The 27 block types: rich_text, colour_palette, colour_ramp, colour_story,
type_specimen, do_dont, image, image_grid, comparison_table, download_list,
callout, clearspace, spacing_scale, icon_grid, contrast_pairs, misuse_gallery,
spec_table, link_list, contacts, checklist, video, tabs, accordion, code,
steps, hero, quote. Every one of them now has appearance options; clearspace,
video and accordion had none until 26 September and so could not follow the
site's appearance at all.

**A repeater can hold a repeater.** `field()` in `editor.js` handles a nested
one and `sanitise_fields()` is recursive, which is what `colour_ramp` needs: a
list of families, each with its own list of steps, so the scale comes from the
content rather than being fixed in the definition. The sanitiser understood
exactly one level of nesting before that and would have run a nested array
through `trim()`, destroying it on the first save.

**An asset field says what it accepts.** `'accept' => 'video'` on an asset
field makes the picker offer MP4s instead of images. Only the video block uses
it; everything else gets images. `/admin/assets.json` carries both, tagged with
a `kind`, so anything reading that feed has to filter.

**Text is edited on the canvas, in place.** A renderer marks a piece of text
with `editable('heading')`, or `editable("pairs.$i.rule")` for one inside a
repeater, and `editable('body', true)` where the value holds paragraphs.
`preview-bridge.js` makes those regions contenteditable and reports changes
back; `applyCanvasEdit()` in `editor.js` resolves the path against the block's
data and refuses anything that does not already exist, so a mistyped path
cannot invent a key that `sanitise_blocks()` would then drop. `editable()`
emits nothing on a published page. `check-blocks.php --edit` asserts every path
resolves, because a wrong one loses the typing silently.

Two deliberate exceptions: the hero's headline, whose lines are sized from
widths measured on the server before the page is sent, and any field that is
not visible on the page at all, such as alt text. Both are edited in the
inspector.

**The canvas is not reloaded while it is being typed into.** Saving normally
reloads the iframe; during an inline edit that would take the cursor and the
half-finished sentence with it, so the reload is deferred until the text is
left.

**Empty blocks show a placeholder, but only in the editor.** `render_blocks()`
buffers each block in edit mode and, when the output would show nothing,
substitutes the block's label and hint. Without it a newly added block was
invisible on the canvas and had no height to click. It lives in `render_blocks()`
rather than in 25 renderers so it cannot drift, and never runs on a public page.

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
changed nothing. This has since happened twice more: a whole second copy of the
repeater styles sat further down `admin.css` and overrode every property of the
narrower treatment written for the inspector, and one breakpoint set
`.m3-page` padding in two separate blocks. Run `node tools/check-css.js` after
any CSS work; it finds these.

**`site.js` and `admin.js` are series of immediately-invoked blocks.** One throw
stops every block after it. Each feature is wrapped in `feature(name, fn)`,
which catches and logs. Keep new features inside that wrapper. `admin.js` was
unguarded until 26 September even though the lesson was already written down
here, so check both files when adding one.

**Lifting a block out of the list breaks the canvas.** The preview takes an
opening hero off the array to render it full-bleed, and the remaining blocks were
then re-indexed from zero, so every `data-block` on the canvas was one lower than
the editor's own index and every click selected the neighbouring block.
`render_blocks()` takes an `$indexFrom` for exactly this. Anything that changes
the list before rendering has to pass it.

**A field that displays a default but only writes on input saves nothing.** The
colour field showed `#7257B7` for an empty value, so adding a colour and typing
only its name stored an empty hex, which rendered as `style="--chip: "`. Blank
repeater rows are now built by `blankRow()` in `editor.js`, mirroring
`block_defaults()`. The two have to stay in step.

**`asset_image_url()` only works for what `/i/` will serve.** The video block
pointed a `<video>` at `/i/{id}`, which rejected anything that was not an inline
image, so an uploaded video always 404d. `/i/` now serves inline *media*, and
video goes out with byte range support, without which Safari will not play a
video at all and nothing can seek.

**The preview iframe reloads on every autosave.** It reloads roughly every time
typing stops, so anything that should survive editing has to be restored
afterwards. `preview-bridge.js` puts the scroll position back from
`sessionStorage`; before that the canvas jumped to the top of the page
constantly and editing below the fold was impractical.

**`.m3-state` sets `isolation: isolate`,** so every row is its own stacking
context and a menu inside one cannot paint above later rows. Raise the row.

**`[hidden]` loses to any class that sets `display`.** Anything hidden from
JavaScript needs its own `[hidden] { display: none }` rule. `.m3-asset`,
`.m3-list-item` and `.m3-section` all have one.

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

**Working:** deploy pipeline, three-column editor, 25 block types, 26 numeric
appearance settings, asset library with generated image variants and gated
downloads, upload from inside the editor's picker, sections as scrolling pages
with a hero opener, version history with collapsing and pruning, people and
roles, settings, logo, area toggle, backup export, sign-in throttling, compact
sticky header, mobile slide-out rail, filtering on the admin pages list.

**Content:** 30 pages scaffolded as drafts. One written: Tone of voice.

**Not built:** search on the public site, the Components area beyond a
placeholder, motion page content, type
exports in `/tokens.css`, password reset, pagination (deliberately deferred).

**Known duplication:** the asset picker exists twice, as `openPicker()` in
`editor.js` and `openImagePicker()` in `admin.js`. They have already diverged:
only the editor's can upload, only the admin's marks the current selection.
Worth merging into one file that both load before either grows again.

---

## 9. Outstanding jobs

1. **Write the pages.** The only thing on the critical path; the build is well
   ahead of the content.
2. Re-upload or Replace existing images so variants are generated; variants are
   only made on upload.
3. Self-host Plus Jakarta Sans: four woff2 files (400, 500, 600, 700) into
   `public_html/assets/fonts/`, named `plus-jakarta-sans-400.woff2` and so on.
   The site then stops loading Google Fonts automatically. Deliberately not done
   yet.
4. Turn `debug` off in `app/config.php` on the server.
5. Decide the real domain.
6. Merge the two asset pickers into one, before they diverge further.
7. Deferred from the audit: section page caching, password reset, `/tokens.css`
   type exports, search.

`MoreYears-Regular.woff2` is in `public_html/assets/fonts/`, so the hero and the
Typography page can show real specimens.

---

## 10. Checks worth running after any change

```bash
find app public_html tools -name '*.php' -print0 | xargs -0 -n1 php -l
for f in public_html/assets/js/*.js; do node --check "$f"; done
php tools/check-routes.php
php tools/check-blocks.php
php tools/check-blocks.php --edit
node tools/check-css.js public_html/assets/css/site.css public_html/assets/css/admin.css
```

The last four were ad-hoc scripts and are now committed, because each of them
has caught something real:

- `check-routes.php` renders no pages; it asserts every URL the interface calls
  matches a route. It found a route that was never inserted and two patterns
  with one backslash too many.
- `check-blocks.php` renders all 25 block types twice, empty and full, through
  the sanitiser first, and reports anything that throws, shows nothing, or puts
  an empty value into an attribute that will be read as a colour or a link. It
  needs no database. `--edit` additionally requires every empty block to be
  visible, which is what the editor canvas depends on.
- `check-css.js` checks braces balance and finds any property set twice on the
  same selector in the same at-rule context. Read its output rather than
  trusting it: the appearance tokens re-declare base rules with `var(--s-*)` on
  purpose, and those hits are correct.

Still worth doing by hand: render every view; confirm every CSS class used in
markup or JS has a rule; confirm every block option value has a rule.

Several bugs here rendered correct HTML and still looked wrong, so markup tests
alone are not enough.

PHP is not needed to run the site locally, but it is needed for the checks
above. On Windows, `winget install PHP.PHP.8.3`.

---

## 11. Access

- Hostinger hPanel: database and user are both `u675995716_brandhub`
- Deploy secrets in GitHub: `FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`,
  `FTP_REMOTE_DIR`
- Admin accounts at `/admin/users`; `tools/setup.php` only runs when no admin
  exists
