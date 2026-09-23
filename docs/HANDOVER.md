# Years brand hub: handover

Everything needed to pick this up cold. Written for whoever works on it next,
including a future conversation with no memory of this one.

---

## 1. What it is

An internal brand guidelines site for Years, plus the CMS that edits it.
Custom PHP, no framework, no Composer, no build step. It runs on Hostinger
shared hosting on the PHP/HTML plan.

Live at `https://lightyellow-woodcock-213680.hostingersite.com` (Hostinger's
temporary preview domain, not yet moved to a real one).

---

## 2. Where things are

```
.github/workflows/deploy.yml   FTPS deploy on push to main
app/
  bootstrap.php                loads every file in app/lib, then config
  config.php                   SERVER ONLY, gitignored, holds db credentials
  config.sample.php            template for the above
  routes.php                   the whole router, top to bottom, first match wins
  lib/                         every file here is auto-loaded by bootstrap
    assets.php                 uploads, validation, bundles, serving
    auth.php                   sessions, roles
    blocks.php                 block definitions, sanitising, rendering
    db.php                     PDO helpers
    http.php                   escaping, CSRF, redirects, asset_url()
    icons.php                  inline SVG icon sets
    migrate.php                pending/apply migrations
    settings.php               settings table, sections, logo, areas
    styles.php                 global appearance tokens
    view.php                   template rendering
  blocks/<type>.php            one renderer per block type
  views/                       public and admin templates
db/00N_*.sql                   migrations, applied in filename order
public_html/                   the only web-accessible directory
storage/                       uploads and caches, never deployed
tools/                         one-off scripts (setup, migrate, create-user)
```

The document root is the **parent** of `public_html`, so `app/` and `storage/`
are not reachable over HTTP. `FTP_REMOTE_DIR` is `/`, not `/public_html`.

---

## 3. How the content model works

A page row holds its blocks as JSON. A block is:

```json
{ "type": "rich_text", "data": {...}, "settings": {...}, "options": {...} }
```

- **data** is the block's content, shaped by `block_types()` in `lib/blocks.php`
- **settings** is placement, shared by every block: width, background, spacing,
  rule above, anchor. Defined once in `block_settings_fields()`
- **options** are per-type appearance, from `block_options($type)`

`sanitise_blocks()` strips anything not in those definitions before saving, so
stored JSON can only ever contain known keys. Adding a block type means an
entry in `block_types()` plus a renderer in `app/blocks/`. No migration.

**Inheritance:** any option whose value is `inherit` falls back to the matching
global setting via `block_option($options, $key, $fallback, $siteKey)`. That is
what makes Appearance in the admin move every block that has not been
deliberately overridden.

---

## 4. Decisions, and why

| Decision | Reason |
|---|---|
| Custom PHP, no framework | Hostinger shared plan has no Node; Statamic charges per editor; WordPress fights you |
| Blocks as JSON on the page row | New block types need no migration |
| Material 3 for the admin only | A back office is a work surface; the public site is hand-crafted |
| M3 implemented in CSS, not the Material Web library | No build step, no CDN dependency |
| Plus Jakarta Sans for the interface | Years' own faces are documented as content, not used as chrome |
| Server-side rendering in the editor canvas | One implementation, so the preview cannot drift from what publishes |
| Two width settings, not one | 72rem container for grids and tables, 70ch measure for prose |
| Sections render as one scrolling page | People read guidelines in sequence more than they look one up |

---

## 5. Traps, all of which have bitten once

**MySQL escapes backslashes in string literals.** Writing JSON into a
migration needs both `'` doubled *and* `\` doubled, or `\n` becomes a real
newline and the JSON is invalid. Cost us a whole broken page. If a migration
writes JSON, test it by simulating MySQL's unescaping, not by running it in
SQLite, which does not escape backslashes and hides the bug.

**`[hidden]` loses to any class that sets `display`.** Anything toggled with
the hidden attribute needs an explicit `.thing[hidden] { display: none }` if
its class sets a display. Caught twice: dialogs and the block menu.

**`.m3-state` sets `isolation: isolate`,** so every row is its own stacking
context. A menu inside a row cannot paint above later rows however high its
z-index. Raise the row instead.

**mbstring is not guaranteed** on the host. Use the guarded helpers in
`http.php` (`str_trim_to`, `str_initial`) rather than `mb_*` directly.

**Deploy order matters.** If a change needs a migration, apply the migration
first or the site shows the update-waiting page in between. Push, then
Settings, Apply updates.

**Testing markup is not testing CSS.** Several bugs rendered correct HTML and
still looked wrong. There is a sweep in section 8 that catches some of it.

---

## 6. Conventions

- Every write route calls `csrf_check()`; every admin route calls
  `require_editor()` or `require_admin()`
- Every query uses bound parameters; no SQL is built from variables
- All output goes through `e()`. Blocks never render raw HTML
- Destructive actions use `data-confirm` on the form, handled centrally in
  `admin.js`, so a new screen cannot forget it
- Stylesheet and script URLs go through `asset_url()`, which appends the file's
  modification time, so a deploy always beats the browser cache
- British English throughout, in the interface and in content

---

## 7. State of the build

**Working:** deploy pipeline, three-column editor (outline, live canvas,
inspector), nine block types with per-type options, numeric global appearance
settings, asset library with versioning and gated downloads, sections as
scrolling pages, page versioning with restore, people and roles, settings,
logo upload, Brand/Components area toggle.

**Content:** 30 pages scaffolded as drafts. One written: Tone of voice.

**Not built:** search, the Components area beyond a placeholder, inline text
editing on the canvas, motion, type exports in `/tokens.css`.

**Block types the page inventory will still need:** clearspace diagram,
spacing scale, icon grid, contrast pairs, before and after, video embed, spec
table, link list, contacts, checklist.

---

## 8. Checks worth running after any change

```bash
# Syntax
find app public_html tools -name '*.php' -print0 | xargs -0 -n1 php -l
for f in public_html/assets/js/*.js; do node --check "$f"; done

# Every class used in markup or JS has a CSS rule
# Every block type has a renderer and vice versa
# Every option value has a CSS rule behind it
# Every element toggled with [hidden] has a matching [hidden] rule
```

The last one is the cheap catch for a class of bug that server-side render
tests cannot see.

---

## 9. Immediate next steps

1. **Write one page end to end** (Colour or Logo). That tells us which of the
   ten missing block types are actually needed, rather than guessing.
2. Build that shortlist of block types.
3. Search, once there is enough content to need it.
4. Turn `debug` off in `app/config.php`.
5. Decide the real domain before there is content to move.
6. Get the More Years woff2 into `public_html/assets/fonts/` so the Typography
   page can show real specimens.

---

## 10. Access

- Hostinger hPanel: database `u675995716_brandhub`, user of the same name
- Deploy secrets live in GitHub: `FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`,
  `FTP_REMOTE_DIR`
- Admin accounts are managed at `/admin/users`; `tools/setup.php` only runs
  when no admin exists, for standing up a fresh install
