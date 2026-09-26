# Years brand hub

**Picking this up cold? Read [docs/HANDOVER.md](docs/HANDOVER.md) first.** It
covers the architecture, the decisions and why they were made, and the traps
that have already cost us a day between them.

A small PHP application for publishing brand guidelines internally. No framework, no
Composer, no build step. It runs on the PHP/HTML slot of a Hostinger shared plan.

## Requirements

- PHP 8.1 or higher, with PDO MySQL enabled
- One MySQL database
- Apache with `mod_rewrite` (Hostinger has this on by default)

## Directory layout

```
public_html/      the only directory the web server exposes
app/              code, templates, block definitions. Not web accessible.
storage/          uploaded assets and caches. Not web accessible.
db/               numbered .sql migrations
tools/            one-off CLI scripts
```

On Hostinger, set the domain's document root to `public_html`. If you cannot change
the document root on your plan, upload the contents of `public_html` into the
existing `public_html` folder and put `app`, `storage`, `db` and `tools` one level
above it, then edit the `require` path at the top of `public_html/index.php`.

## First run

1. Create a MySQL database and user in hPanel.
2. Copy `app/config.sample.php` to `app/config.php` and fill in the credentials.
3. Import `db/001_init.sql` through phpMyAdmin, or run `php tools/migrate.php`.
4. Create your account:
   `php tools/create-user.php "Your Name" you@years.com "a-long-password" admin`
5. Visit `/admin/sign-in`.

If your plan has no SSH access, run step 4 by temporarily copying the file into
`public_html`, hitting it once in the browser with the values hardcoded, then
deleting it. Do not leave it there.

## How the block system works

`app/lib/blocks.php` defines every block type once: its label, hint and fields.
That single definition drives three things:

- the admin form, generated in JavaScript from the schema handed to the page
- `sanitise_blocks()`, which strips anything not in the definition before saving
- the front end, which renders `app/blocks/<type>.php` for each block

Block content is stored as JSON on the `pages` row, so adding a block type needs a
new entry in `block_types()` and a matching render file. No database migration.

Field types available: `text`, `textarea`, `longtext`, `colour`, `select`, `repeater`.

## Security posture

- Everything except `public_html` sits outside the web root
- All output escaped through `e()`; blocks never render raw HTML
- CSRF token required on every write, including the autosave endpoint
- Passwords hashed with `password_hash()`, sessions regenerated on sign in
- Prepared statements everywhere, no string-built SQL

## Versioning

Every save writes a snapshot to `page_versions`. Restoring from one is not in the
admin yet, but the data is there from day one.

## Deploying

Push to `main`. GitHub Actions lints every PHP file, then FTPs the repo to
Hostinger. Set these repository secrets: `FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`,
`FTP_REMOTE_DIR`. The workflow excludes `app/config.php` and `storage/`, so a
deploy can never overwrite your credentials or uploaded files.

## What is built

- Block editor: outline, live canvas, contextual inspector
- 27 block types, each with its own options, inheriting from global settings
- Appearance with a live preview, per-field resets and contrast checks
- Appearance: numeric global styling, colours, widths, type scale
- Assets: upload, versioning, replace in place, ZIP bundles, gated downloads,
  and upload from inside the editor's picker
- Pages: sections, ordering, drafts, version history with restore, filtering
- People and roles, settings, site logo, area toggle

## Not built yet

- Search across pages
- The Components area, beyond a placeholder
- `/tokens.css` exports colours only, not type

## Checks

`tools/` holds four checks worth running after any change. They need PHP on the
path and, for the CSS one, Node. See section 10 of
[docs/HANDOVER.md](docs/HANDOVER.md).

```bash
php tools/check-routes.php
php tools/check-blocks.php
node tools/check-css.js public_html/assets/css/site.css public_html/assets/css/admin.css
```
