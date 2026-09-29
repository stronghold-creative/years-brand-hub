# The component library: scope

A Storybook for Years, inside the brand hub. **All eight phases are built**: the
tables, the admin list, the live sandboxed preview, the details editor with
version history, the public index and component pages, the spec table derived
from each component's own CSS, the Figma import, the written guidance in the
block editor, and components in search.

This was the agreed shape, written down before any code, so the build could be
picked up one phase at a time. Decisions were taken on 27 September 2026 and the
build finished on 28 September; where one went against the cheaper option, the
reason is recorded. It is kept as the record of why this is the way it is —
section 9 lists what each phase shipped, and the traps found along the way.

**What is left is content, not build.** No components have been written yet, and
the area is still behind the `components_enabled` switch in Settings, so nobody
but an editor can reach it.

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
| **2. Sandbox** ✔ **built** | The frame route, its CSP, the height bridge, the preview with variant and viewport switchers, and — moved forward from phase 3 — the variants and base CSS that feed it | 1 |
| **3. Editor** ✔ **built** | Details (name, slug, group, status, summary, Figma link, JS), version history with restore, an unsaved-changes guard in place of autosave. `notes_override` moved to phase 5, where the spec it overrides exists | 1, 2 |
| **4. Public** ✔ **built** | `/components` index with live thumbnails, `/components/{slug}`, the code panel and copy button. The `docs` blocks render here too; phase 7 is now only the editing side | 2, 3 |
| **5. Derived spec** ✔ **built** | The CSS pass, token matching, the flag, `notes_override`, the states it defines | 3 |
| **6. Import** ✔ **built** | The Import panel, detection, the Dev Mode split, the `figmeta` refusal, and a review step before anything is written | 3 |
| **7. Docs blocks** ✔ **built** | The `docs` column wired to the existing block editor; the renderer landed in phase 4 | 3, 4 |
| **8. Search** ✔ **built** | Components in the index, with the cache key gaining the components table | 4 |

All eight are in. A usable library existed at the end of phase 4; phases 5 to 8 each made it
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

### What phase 2 actually shipped

**The phase boundary moved, on purpose.** A sandbox with nothing in it cannot be
judged, so the render inputs — `base_css` and the variants — came forward from
phase 3 rather than building a throwaway stub now and the real thing later.
Phase 3 is now metadata, autosave, versions and the docs blocks.

- `app/views/component-frame.php`, the frame document, and the route
  `/components/{slug}/frame/{variant}`.
- `component_frame_csp()` and `component_frame_headers()` in `lib/components.php`.
- `app/views/admin/component.php`, the component screen: variants on the left as
  `<details>` cards with label, markup, variant CSS and a note; base CSS below
  them; the live preview on the right, sticky.
- `public_html/assets/js/components.js`: the height bridge, variant switching
  without a reload, the viewport toggle, the dark toggle. Loaded on every admin
  page and returns immediately when there is no frame.
- `.cmp` in `admin.css`, including the chequerboard stage so a transparent
  component reads as transparent rather than as white.
- **`tools/check-components.php`**, new and committed. See below.

Six decisions worth knowing before phase 3:

- **The variant selection is server-rendered from `?v=`, and the chips are real
  links.** The screen works with no JavaScript. The JS then swaps the frame's
  `src` instead of following the link, because a reload would take whatever is
  half-typed in the textareas with it.
- **The parent does not check the message origin, and that is correct here.** The
  frame is sandboxed without `allow-same-origin`, so it has an opaque origin and
  its messages arrive with an origin of `"null"` — there is nothing to compare.
  The check that does the work is `event.source === frame.contentWindow`, which
  another frame cannot forge. This contradicts the rule in section 4 of the
  handover ("both sides validate origin"), which is right for the page preview and
  impossible for this one. Nothing sensitive is in the message, and
  `frame-ancestors 'self'` means no other site can be showing the frame anyway.
- **The reported height is capped at 4000px.** A component with a runaway height
  would otherwise push the rest of the editor off the screen.
- **The frame does not load `site.css`.** A component should look like itself,
  styled by its own CSS over the brand tokens — not like a block on a guidelines
  page, which would flatter it with styling the real component will not have.
- **The viewport toggle narrows the stage, not the frame.** A media query inside
  the component reads the frame's own width, which is the entire reason for
  having the toggle.
- **`component_touch()` exists** because `updated_at` is
  `ON UPDATE CURRENT_TIMESTAMP` on the `components` row only. A variant is a
  different table, so without it the list would show a component as untouched for
  weeks while its variants were being rewritten daily.

### tools/check-components.php

The safety of this whole area rests on a handful of exact strings — a sandbox
attribute without one particular token, a policy that allows inline script but no
network, and an admin screen that never prints a variant's markup as markup. Each
is one word away from being wrong, and **none of them fails visibly when it is**:
the page still renders and still looks right. So they are asserted, and the check
exits non-zero so it can gate a deploy. It needs no database.

It reads the sandbox attribute off the *rendered* page and compares it exactly,
rather than searching the template for a known-bad value. Two reasons, and the
first was found by the check failing on its own first run: the template's comment
explains why `allow-same-origin` is forbidden, so a substring search over the
source reports the explanation as the offence. An exact comparison also catches a
token nobody has thought of yet, which a search for one bad value never would.

It was verified by sabotage — `allow-same-origin` added to the iframe, the check
run, both assertions failed and the exit code was 1 — because a check that has
never failed has not been shown to work.

Still true after phase 2: **nothing outside the frame renders raw HTML.** The
frame is the documented exception; section 4 is its specification and the check
above is what holds it to it.

### What phase 3 actually shipped

- **A Details card** on the component screen: name, group, status, summary, slug,
  Figma link, and the JavaScript field behind a `<details>`. Route
  `/admin/components/{id}/meta`.
- **`component_figma_parse()`**, which reads the file key and node id out of a
  pasted link. Nothing is fetched.
- **Version history**: `component_snapshot()`, `component_version_record()`,
  `component_restore()`, the screen at `/admin/components/{id}/versions`, and
  `component_after_write()` which every write now ends with.
- **An unsaved-changes guard** rather than autosave, in `components.js`.
- Two more sections in `check-components.php`, and a **warning handler** that
  makes any PHP notice a failure.
- **The list's name is a link.** It was not, from phase 1, and stayed unlinked
  through phase 2 after the screen it should point at existed — so a component
  could be added and not opened. Reported, and fixed here.

Decisions worth knowing before phase 4:

- **No autosave, deliberately.** The page editor autosaves because prose is typed
  continuously and a lost paragraph is a real loss. A component's fields hold
  code, which is edited in deliberate passes and is *broken for most of the time
  it is being written*: autosaving it would fill the history with snapshots of
  half-finished CSS and reload the preview into a broken state every second. What
  autosave actually protects against here is closing the tab with work in it, so
  that is what the guard does, directly. Revisit only if it proves annoying in
  use.
- **`component_after_write()` lives in the library, not the routes.** Six write
  routes each remembering to touch the row and record a version is six chances to
  miss one, and a missing version is invisible until someone needs it.
- **The slug does not follow a rename.** A component's URL gets pasted into Slack,
  so renaming Button to Primary button keeps `/components/button`. It is editable,
  with a warning that says what changing it breaks.
- **Restoring records the current state first**, so a restore chosen by mistake is
  itself undoable.
- **A restored variant is a new row.** Variant ids are deliberately not in the
  snapshot: an id that no longer exists cannot be restored to, and matching on
  label would silently merge two variants that had been renamed. So the variants
  are replaced wholesale, and `component_settle_default()` then guarantees exactly
  one default — a hand-edited or pre-rule snapshot could have none or several.
- **`updated_at` and `updated_by` are not in the snapshot.** They change on every
  save, so a snapshot carrying them would never equal the last one and the
  no-change rule would never fire.
- **`version_prune_history()` is now shared** by pages and components rather than
  copied, so the thinning policy cannot drift between the two. The table and column
  are interpolated, not bound, so they are whitelisted against a hardcoded map.

### What phase 4 actually shipped

**The library is usable from here.** Someone who cannot edit can now find a
component, see it, switch its states and copy its code.

- `app/views/components.php` — the index, replacing the placeholder. Grouped,
  each card carrying the component rendered in its own sandboxed frame.
- `app/views/component.php` — the component page: the preview with its switchers,
  a code panel per variant plus the CSS and any JS, the variant notes, the `docs`
  blocks, and an Open in Figma link.
- Routes `/components` (rewritten) and `/components/{slug}`, with `?v=` to open a
  named state.
- `components_visible()`, `components_with_variants()`, `components_by_group()`,
  `component_is_visible()`.
- The public side of `components.js`: the code panel and the note follow the
  variant chips.
- The component styles at the end of `site.css`.

Decisions worth knowing:

- **The thumbnail is the component, not a picture of it.** A screenshot would
  have to be generated, stored and regenerated whenever the CSS moved — and the
  one time it was not, the index would be advertising something that no longer
  exists. This cannot go out of date. The cost is an iframe per card, kept
  reasonable by showing only the default variant, `loading="lazy"`, and no height
  bridge on thumbnails: they are a fixed cropped window, because twenty frames
  each reporting and resizing would reflow the index continuously as it loaded.
  If it ever gets slow at a hundred components, page the index rather than going
  back to pictures.
- **`pointer-events: none` on the thumbnail frame.** Without it the component
  inside catches the click and the card, which is one link, does nothing.
- **An editor sees drafts on the public index**, as the search index already
  gives an editor a copy with drafts in it. Otherwise you build a component, open
  the public page to check it, and find nothing there.
- **Deprecated components stay listed, for everyone.** That is the point of the
  status — it says do not use this for anything new, which is exactly what a
  reader needs to know. Delisting it only means someone copies it out of the
  theme instead.
- **`component_is_visible()` is one function, not a condition repeated at three
  routes.** The day one of them is written slightly differently is the day a
  draft component is public.
- **Every variant's code panel is on the page, hidden.** Switching costs no
  request, and the panel and the note move with the frame — a page showing one
  variant and describing another is worse than one that reloads.
- **The CSS panel is base plus variant, concatenated.** The variant's CSS alone
  is missing the half that makes it look like anything, and someone copying it
  needs both.
- **`components_with_variants()` loads them in one query.** The index needs each
  component's variants for the thumbnail and the count, and asking per card is a
  query per card — fine at five, not at fifty, and never noticed until the index
  is slow and nobody remembers why.
- **`components.js` loads on the component page only**, behind
  `$needsComponentJs`. The index's thumbnails need no script.

### What phase 5 actually shipped

The table that makes this an audit as well as a catalogue.

- `app/lib/spec.php` — the whole engine: `component_spec()`, `spec_rules()`,
  `spec_tokens()`, `spec_match()`, the two normalisers, `spec_states()`, and the
  read and write of `notes_override`.
- `app/views/component-spec.php` — one template, rendered by both the public page
  and the admin screen, so the two cannot describe a component differently. It
  takes `$specEditable` to turn each row's note into a field.
- `design_colour_tokens()` in `styles.php`, now the single source for both
  `/tokens.css` and the spec's matching.
- Route `/admin/components/{id}/notes`.
- `.devspec` in both stylesheets.

How it decides:

- **A value that *is* `var(--x)` is the answer.** There is nothing to match.
- **A `var()` inside a larger value is reported as partly tokenised, and not
  flagged.** `border: 1px solid var(--rule)` has a hardcoded width in it; calling
  that declaration on-system because it mentions a token would hide precisely
  what the table is for. Shorthand is not taken apart here, so it is not flagged
  either.
- **A literal is matched against the appearance settings and the published
  palette**, like for like. `6px` is never matched against a token held in `rem`,
  because converting would mean assuming a root font size — and the body size is
  itself a setting someone can move.
- **Colours normalise**, so `#FFF`, `#ffffff` and `#FFFFFFFF` all match. An alpha
  channel is dropped for matching.
- **Shorthand that is not a single value is never flagged.** `0 auto` and
  `1px solid red` are beyond what a text pass can judge, and flagging them would
  be reporting the tool's limits as findings.
- **The states are read from selectors and at-rules**, and an *absence* is named:
  a component with no keyboard focus style gets a line saying so, because a list
  of what is present cannot show what is missing.
- **A note is filed under a key that includes the value.** A note explaining why
  a radius is 6px should not silently follow it to 28px. Changing the value
  detaches the note — visibly, and at the moment the thing it described stopped
  being true.

**The honesty line on the page is not decoration.** This reads declarations. It
does not resolve the cascade, follow a `var()` to a value, or know which rule
wins. A table that looked authoritative and was wrong in ways nobody could see
would be worse than no table; the page says what it is.

### What phase 6 actually shipped

One box on the component screen. What was pasted is worked out rather than chosen
from a menu — a menu asks someone to classify their own clipboard before they
have looked at it, and they will get it wrong in the direction that loses work.

- `app/lib/import.php` — `component_import_detect()` and
  `component_import_apply()`.
- `app/views/admin/component-import.php` — the review screen.
- The Import card on the component screen, and routes
  `/admin/components/{id}/import` and `.../import/apply`.

**Two steps, always.** The first only looks and shows what it would do; the
second writes. Dev Mode's output is close but rarely shippable — absolute
positioning, generated class names, hardcoded hex where a token exists — so it is
never saved on the strength of a paste. The proposal arrives in editable fields,
because the usual thing to want is *most* of what Dev Mode produced.

**The spec runs on the incoming CSS before it is accepted**, so a hardcoded colour
is flagged on the review screen rather than discovered afterwards. That is the
import and the audit meeting in the one place they are both useful.

What the detector recognises, in this order and for these reasons:

| Pasted | How | What happens |
|---|---|---|
| Figma's binary clipboard | `figmeta`, `(figma)` or `data-buffer=` | **Refused, with an explanation.** First, because it arrives as an HTML fragment and would otherwise be taken for markup |
| A link on its own | a single-line http(s) URL | Kept; file key and node id read out. Before markup, because a URL is not HTML but is easily taken for nothing in particular |
| An SVG | `<svg` | Becomes the variant's markup as it is |
| Markup with a `<style>` | a tag and a style element | Split into markup and CSS |
| Markup then CSS, no `<style>` | a tag, then rules | Split at the last closing tag, and the screen says where it split |
| Markup alone | a tag | Markup only |
| CSS alone | rules and no tag | Appended to the base CSS |
| Anything else | — | Says so, and says what to paste instead |

**Refusing the binary clipboard is a feature, not a limitation.** It is the
obvious thing to try, and a blank result or a shrug would send someone away
thinking the import is broken. It says what it is — an encoded copy of the Figma
document, not code — and what to do instead.

**Applying is additive.** CSS is appended rather than replacing what is there,
because an import is usually one more state rather than a fresh start; someone
who wants a replacement clears the field first, and the screen says so. Markup
goes to a variant chosen on the review screen, existing or new, never guessed at.

### What phases 7 and 8 shipped

**Phase 7 — the written guidance, in the block editor.** The renderer went in with
phase 4, so this was only the editing side.

- `app/views/admin/component-docs.php` and `app/views/component-docs-preview.php`.
- Routes `/admin/components/{id}/docs`, `.../docs/save`, `.../docs/preview`.
- Two data attributes in `editor.js`: `data-save-url`, and the payload dropping
  the page fields when there is no page.

**It is the page editor, not a copy of it.** Twelve hundred lines of outline,
inspector, canvas, drag-to-reorder and editing in place, reused whole — because
the thing being edited is identical: blocks on a row. What differs is where a
save goes, which is now a data attribute. The blocks go through the same
`sanitise_blocks()`, so stored JSON here can only contain known keys.

The inspector needs a `#page-settings-template` to show when nothing is selected —
without it the first render throws and the screen is unusable. A component has
nothing to configure there, so it holds a note instead: a blank panel reads as
broken. Every field `bindPageFields()` then looks for is already optional, so
nothing else was needed.

**Phase 8 — components in search.** Someone half-remembering a rule does not know
whether it was written up as guidance or built as a component, and should not
have to.

- `search_component_entries()` in `search.php`, and the cache key.
- `search.js` sends a component result to `/components/{slug}` and labels it.

- **The cache key now includes the components table**, which is the whole risk of
  this phase. Keyed on pages alone, editing a component would serve a stale index
  for as long as nobody touched a page — and nothing would look wrong.
  `component_after_write()` moves a component's `updated_at` even when only a
  variant changed, so counting rows and taking the latest is enough.
- **The name, summary, group, variant labels and the guidance text are indexed.
  The markup and the CSS are not** — searching "button" should not match every
  component whose stylesheet happens to mention one.
- **A heading in the guidance is its own result**, with its anchor, exactly as a
  heading on a page is.

### Getting a component to match the design exactly

The frame deliberately does not load `site.css`, which is where the public site
declares its typefaces — so a component whose CSS asked for More Years got the
fallback, and **type imported from Figma never matched the design however
carefully the CSS was pasted.** It was the one thing that could not be fixed by
editing the component, because nothing about the component was wrong.

`brand_font_faces()` now declares every face on the server into the frame, from
the same `brand_fonts()` the type specimen reads, so the two can never disagree
about which typefaces exist.

**And the spec table says when a face is missing.** A `font-family` whose first
name is not on the server is flagged as "not on the server" rather than as an
off-token value, because it is a different problem with a different fix: upload
the face. Only the first name in a stack is judged — the rest is by definition
the fallback, and a component rendering in its fallback is not the component that
was designed.

So when an import is *nearly* right, the spec table is where to look. Type is
the usual answer, and it now says so.

### Two more traps, found in phase 5

1. **`.spec` was already taken.** The `spec_table` block owns `.spec`,
   `.spec__row` and `.spec__note` in `site.css`, and `.spec { display: grid }`
   took the new table apart. `check-css.js` caught the duplicate declaration. The
   new namespace is `.devspec`. Worth checking a class name against the
   stylesheet before using it — 27 block types have had most of the obvious ones.
2. **Tidying a selector threw away the at-rule.** `spec_rules()` cut
   `@media (max-width: 30rem) { .btn` down to `.btn`, which is right for the
   selector and lost the only place `@media` and `prefers-reduced-motion` appear
   — so "responds to width" was reported as absent on CSS that plainly had it.
   The prelude is kept on the rule now. An assertion caught it.

### Three bugs the check found in phase 3

Worth recording, because each was invisible and each was found by an assertion
rather than by looking:

1. **`'#[?&]node-id=([^&#]+)#i'`** — the `#` inside the character class ends the
   `#` delimiter. PHP reports "Unknown modifier ']'", which points nowhere near
   the cause. The pattern is delimited with `~` now.
2. **The component screen emitted "Undefined variable $groups"** when the Details
   card gained its datalist. The page rendered, the assertions passed, and the
   datalist was silently empty. That is why warnings now fail the check.
3. **Those warnings did not print.** They fire while a view is captured with
   `ob_start()`, so the handler's output went into the buffer and was discarded
   with it — `$failed` went up and nothing said why. The handler writes to STDERR
   now.

And one in phase 4, of the same family: the check renders the admin list, which
loops `as $component`, and a template's variables land in the calling scope — so
the public component page below it was rendered for the wrong component, and
reported as a failing assertion rather than as the muddle it actually was. The
check re-establishes `$component` before that section. Worth knowing generally:
`view()` uses `extract()`, so every template shares one scope with its caller.

**It then happened twice more**, in phases 5 and 7, each time costing a confusing
failing assertion before the cause was seen. Every section of
`check-components.php` now states the variables its view needs immediately before
rendering it, rather than relying on what is already in scope. If you add a
section, do the same — it is not defensiveness, it is the third time.

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
