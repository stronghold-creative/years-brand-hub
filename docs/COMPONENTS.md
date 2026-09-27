# The component library: scope

A Storybook for Years, inside the brand hub. **Phase 1 of 8 is built** — the
tables, the library, the admin list. This is the agreed shape, written down
before any code, so the build can be picked up one phase at a time. Decisions
here were taken 27 September 2026; where one went against the cheaper option,
the reason is recorded.

Section 9 is the phase table and says where the build has got to.

Read `HANDOVER.md` first. This document assumes it.

---

## 1. What it is for

A designer or developer arrives wanting to use a component. They need, in this
order: to see it, to see it in the state they care about, to copy the code, and
to know the rules — the radius, which colour token, the padding, what not to do.
Today all of that lives in Figma, where a developer cannot copy it and a
non-Figma reader cannot see it at all.

So: one page per component, showing the real thing rendered, its states
switchable, its code copyable, and a spec table that is mostly derived rather
than retyped.

It is **not** a package. Nothing here is imported into the Shopify theme; the
storefront components are Liquid, and this documents them rather than serving
them.

---

## 2. Decisions

| Decision | Reason |
|---|---|
| Its own table and a bespoke editor | A component is not a page. It has a spine — variants, props, code, status — that wants real columns and a purpose-built screen, not a stack of blocks. The cost is knowingly accepted: versions, search and autosave get built again for it |
| Live rendering in a sandboxed iframe | The point of a component library is the component. A picture of a button is a picture |
| Imported from Figma Dev Mode, pasted | Figma's clipboard is an encoded binary of its internal document format; decoding it needs a kiwi decoder in PHP against a schema Figma changes without notice. Dev Mode's generated code is a text paste, which is one keystroke more and cannot break silently |
| A Figma link is a stored reference, not a fetch | No token to manage, no dependency on a file staying shared, nothing to fail at read time. The file key and node id are parsed out and kept, so the REST API can be added later without touching the data model |
| Dev notes derived, with a hand-written override | Derived notes cannot go stale; written notes can say why. Both, with the written one winning until cleared |
| Hardcoded values flagged against the site's tokens | The library is also an audit. A radius that is not the shape token is either a deliberate exception worth a note or a mistake worth catching |
| Components area stays behind its existing switch | `components_enabled` already gates `/components`. It stays off until there is something in it |

### Why not blocks

Worth stating, because the handover's own rule says a new content type should be
block types and no migration. A component was tried against that rule and does
not fit: variants are not a repeater of prose, the live preview needs its own
route and CSP, the spec table is generated rather than entered, and a component
has a lifecycle — draft, ready, deprecated — that a page's `status` does not
express. Blocks would have modelled it, badly, and the editor would have been a
worse tool for the job than the one this describes.

---

## 3. Data model

One migration, `009_components.sql`. Three tables.

### `components`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `slug` | VARCHAR(190) UNIQUE | `/components/{slug}` |
| `name` | VARCHAR(190) | "Button", "Product card" |
| `group_name` | VARCHAR(120) | Groups the index: Actions, Forms, Navigation, Commerce. Free text, same pattern as a page's `section` |
| `status` | ENUM | `draft`, `ready`, `deprecated`. Shown as a chip; a reader sees only `ready` unless they can edit |
| `summary` | VARCHAR(400) | One line, for the index card and search |
| `figma_url` | VARCHAR(500) NULL | As pasted |
| `figma_file_key` | VARCHAR(80) NULL | Parsed from the URL |
| `figma_node_id` | VARCHAR(80) NULL | Parsed from the URL |
| `base_css` | LONGTEXT | Shared by every variant. Injected once into the preview |
| `base_js` | LONGTEXT | Optional. Rarely used; present because a disclosure or a carousel needs it |
| `docs` | LONGTEXT | JSON array of blocks, run through the existing block engine. The prose below the preview — usage, do and do not, accessibility — reusing 27 renderers rather than writing a second prose system |
| `notes_override` | LONGTEXT | JSON. Keyed by spec row; a value here beats the derived one |
| `sort_order` | INT | |
| `updated_at` / `updated_by` | | As `pages` |

### `component_variants`

A variant is one rendering. "Primary", "Primary, disabled", "Small".

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `component_id` | FK CASCADE | |
| `label` | VARCHAR(190) | |
| `markup` | LONGTEXT | HTML. Rendered inside the sandbox |
| `variant_css` | LONGTEXT | Optional, on top of `base_css` |
| `notes` | VARCHAR(400) | "Only one per screen" |
| `is_default` | TINYINT | The one the page opens on |
| `sort_order` | INT | |

### `component_versions`

Same shape and purpose as `page_versions`: a snapshot of the whole component,
including its variants, as JSON, one per save. It reuses `versions.php`'s
collapsing and pruning if that can be generalised, and copies it if it cannot.

**Nothing in `props` gets its own table.** Props are a repeater inside `docs`,
because they are documentation — name, type, default, description — and never
drive the render.

---

## 4. The sandbox

The one genuinely risky part, so it is specified tightly.

A variant renders at `/components/{slug}/frame/{variant}`, its own route,
serving a minimal document: a reset, `/tokens.css`, the component's `base_css`,
the variant's CSS, then the markup.

- The iframe carries `sandbox="allow-scripts"` and **no** `allow-same-origin`.
  Those two together are equivalent to no sandbox at all. Without
  `allow-same-origin` the frame is a unique opaque origin, so pasted JS cannot
  reach the parent, cookies, or the session.
- The frame route sends its own `Content-Security-Policy`, and
  `X-Frame-Options` must not be set on it — the whole point is that it is
  framed. It is framed only from the same site, which `frame-ancestors 'self'`
  enforces.
- Markup is **stored raw and rendered raw**, which is the first place in this
  codebase that happens. The handover's rule is that blocks never render raw
  HTML; this is the documented exception, and it holds only because the output
  is confined to an opaque-origin frame and only an editor can write it. It must
  never be echoed into an admin or reader page directly.
- Height is reported to the parent by `postMessage` from a small script the
  frame route adds itself, with both sides validating origin, exactly as
  `preview-bridge.js` already does.

Around the frame, on the component page: a variant switcher, a viewport toggle
(narrow / wide, by setting the iframe width), a light/dark toggle where the
component responds to one, and a code panel with the existing copy button.

---

## 5. Derived dev notes

The spec table under the preview. Each row is a label, a value, and where the
value came from.

Derived by parsing the component's own CSS, in PHP, with no dependency:

- **Corner radius** — every `border-radius`, per corner where they differ
- **Colour** — every colour value, matched against the palette and the
  appearance tokens. A match reports the token name; a miss reports the hex and
  is flagged
- **Spacing** — `padding`, `gap`, `margin`, matched against the spacing scale
- **Type** — `font-family`, `font-size`, `font-weight`, `line-height`, matched
  against the type specimens
- **Border** — `border-width` and `border-style`
- **States** — which of `:hover`, `:focus-visible`, `:active`, `:disabled`,
  `[aria-*]` the CSS actually defines, so a missing focus style is visible as an
  absence

A flagged value is not an error. It renders as "6px — not a token" with room for
the note that says why, which is what `notes_override` is for.

Matching reads the same source `/tokens.css` and `style_fields()` already use,
so it cannot drift from the site's own appearance. It is a text pass over the
CSS, not a parser: it reads declarations and does not resolve the cascade, and
the spec says so on the page, because a table that claims more than it knows is
worse than one that is modest.

---

## 6. Importing from Figma

A single **Import** panel in the editor with one textarea and a paste. What was
pasted is detected rather than chosen from a menu:

| Pasted | Detected by | What happens |
|---|---|---|
| Figma node URL | `figma.com/design/` or `/file/` with a `node-id` | Stored, with file key and node id parsed out. No fetch |
| Dev Mode code | An HTML tag plus a `<style>` or CSS block | Split into `markup` and `base_css` and loaded into the editor for review, never saved silently |
| CSS alone | Declarations with no tag | Appended to `base_css` |
| SVG | `<svg` | Becomes the variant's markup as-is. The honest path for an icon or an illustration |
| Figma's binary clipboard | The `figmeta` comment | Recognised and **refused with an explanation** — that it is an encoded internal format, and what to paste instead. It will get pasted; failing silently on it would be the worst outcome |

Dev Mode output needs cleaning — absolute positioning, generated class names,
hardcoded hex where a token exists. So the import lands in the editor as a
proposal, side by side with what is already there, and is accepted or discarded.
The derived spec runs on the result immediately, so the hardcoded values Dev
Mode emits are flagged in the same motion.

---

## 7. The admin

A new destination in the drawer, `/admin/components`, between Assets and People.
It needs an icon in `ui_icon_set()` — the set is small and hand-written, so one
more gets added there.

**The list** mirrors `/admin/pages`: grouped, filterable, status chips, drag to
reorder, New and Delete.

**The editor** is its own screen and deliberately not the three-column page
editor. Two columns:

- **Left, the work**: name, slug, group, status, summary, the Figma link, the
  Import panel, `base_css`, and the variants — each one a collapsible card with
  its label, markup, CSS and notes, reorderable, with one marked default.
- **Right, the live preview**: the same sandboxed frame the public page uses,
  with the variant switcher, updating on save. The derived spec sits under it,
  so an off-token value is seen while it is being written rather than after.

Below both, the `docs` blocks in the existing block editor, for the prose.

Autosave as the page editor has it, on the same 700ms. Versions on every save.

---

## 8. The public page

`/components` becomes a real index — cards grouped by `group_name`, each with
the component's default variant rendered small as its own thumbnail, which is
better than a screenshot because it cannot go out of date.

`/components/{slug}` is the component: name, summary, status, the preview with
its switchers, the code panel, the derived spec, then the `docs` blocks. An
"Open in Figma" link where there is one.

Gated exactly as the guidelines are, through `site_is_private()` and
`components_enabled()`.

Search covers components once the pages exist: name, summary, variant labels and
the `docs` text, added to `search.php`'s index with its own result type. The
index key must gain the components table's own count and latest `updated_at`, or
an edit to a component will not invalidate it.

---

## 9. Phases

Each phase is a commit and a working state. Split this way deliberately: a
session is one phase, so context stays small and a stall costs one phase rather
than the build.

| Phase | What lands | Depends on |
|---|---|---|
| **1. Spine** ✔ **built** | `009_components.sql`, `lib/components.php`, the admin list, Add, Delete, reorder, the drawer item and its icon. No preview, no import | — |
| **2. Sandbox** | The frame route, its CSP, the height bridge, the preview with variant switcher and viewport toggle, in the admin editor | 1 |
| **3. Editor** | The two-column editor proper: variants as reorderable cards, autosave, versions | 1, 2 |
| **4. Public** | `/components` index with live thumbnails, `/components/{slug}`, the code panel and copy button | 2, 3 |
| **5. Derived spec** | The CSS pass, token matching, the flag, `notes_override` | 3 |
| **6. Import** | The Import panel, detection, the Dev Mode split, the `figmeta` refusal | 3 |
| **7. Docs blocks** | The `docs` column wired to the existing block editor and renderer | 3, 4 |
| **8. Search** | Components in the index, with the key fixed | 4 |

A usable library exists at the end of phase 4. Phases 5 to 8 each make it
better and none of them blocks the others.

### What phase 1 actually shipped

- `db/009_components.sql`, the three tables. **Needs applying**: push, wait for
  the green run, then Settings, Apply updates.
- `app/lib/components.php`. Reads, `component_create`, `component_delete`,
  `component_move`, `component_slug`, `component_link`, `components_installed`.
- `app/views/admin/components.php`, the list: grouped, filterable past eight,
  status chips, reorder within a group, delete behind the admin role and a
  confirm.
- `/admin/components` and its three POST routes, in `routes.php` between sections
  and people. All four added to `check-routes.php`.
- A `components` icon in `ui_icon_set()` and the drawer item, between Assets and
  People.
- `.m3-list--components` in `admin.css`: the page row's five columns without the
  cover, the template declared once and shared by the head and the rows.

Three things decided while building, each worth knowing before phase 2:

- **The admin ignores `components_enabled`.** That switch exists so readers
  cannot find an empty area; having it also lock out the person filling the area
  would be perverse. The public routes still honour it.
- **`components_installed()` exists** because the migration ships with the code
  and is applied by hand, so there is always a window where one is live and the
  other is not. `bootstrap.php`'s exception handler already catches that with a
  500 page saying an update is waiting; this keeps the drawer usable and points
  at Settings instead. It is a cached one-row select, so it costs one query.
- **`component_link()` is applied at render, not only on save.** The Figma URL is
  the first field in this project to become an `href`, and a `javascript:` value
  in it would run on whoever clicked. There is no field to type it into until
  phase 3; the guard is in from the start so that field cannot arrive without it.

Still true after phase 1: **nothing renders raw HTML yet.** The variant markup
column exists and is unused. Phase 2 is where the risk arrives, and section 4 is
the specification to build it against.

**Deferred, on purpose:** the Figma REST API sync; a props table; per-component
theming beyond the site tokens; versioning of variants independently of their
component; anything resembling a build step.

---

## 10. What to watch

Every one of these is a trap already recorded in `HANDOVER.md`, arriving here in
a new form.

- **`allow-scripts` with `allow-same-origin` is not a sandbox.** Both together
  let the frame remove its own sandbox attribute. Never both.
- **Raw markup must stay inside the frame.** The moment a variant's HTML is
  echoed into the admin list or a reader page, this becomes stored XSS. Only an
  editor can write it, which limits the blast radius and does not close it.
- **The search index key must include the components table.** Keyed on pages
  alone, a component edit serves a stale index for as long as no page is
  touched.
- **`check-routes.php` and the new routes.** Every URL the new screens call has
  to be in it, including the frame route with its variant parameter.
- **`admin.js` features go inside `feature()`.** One throw stops every block
  after it.
- **New CSS goes through `check-css.js`**, and narrow-screen rules go after the
  rules they override, not in a block further up.
- **A field that shows a default but only writes on input saves nothing.** The
  variant cards will have several.
- **`.m3-state` sets `isolation: isolate`**, so a menu on a variant card cannot
  paint over the card below it without the row being raised.
- **Deploy order.** This is the first migration in a while: push, wait for the
  green run, then Settings, Apply updates.
