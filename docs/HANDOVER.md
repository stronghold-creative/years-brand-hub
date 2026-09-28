# Years brand hub: handover

Everything needed to pick this up cold, including in a new conversation with no
memory of the previous one. Last updated 27 September 2026.

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
  lib/                         16 files, all auto-loaded
    assets.php                 uploads, image variants, bundles, serving
    auth.php                   sessions, roles, sign-in throttling
    blocks.php                 block definitions, sanitising, rendering, contrast
    components.php             the component library: rows, slugs, ordering
    import.php                 reading a Figma paste into a component
    spec.php                   the spec derived from a component's own CSS
    db.php                     PDO helpers
    fontmetrics.php            character widths, so a hero line can be fitted
    http.php                   escaping, CSRF, redirects, asset_url()
    icons.php                  inline SVG icon sets
    migrate.php                pending and applied migrations
    search.php                 the search index, built and cached
    settings.php               settings, sections, logo, areas, export
    styles.php                 global appearance tokens
    versions.php               version recording, collapsing and pruning
    view.php                   template rendering
  blocks/                      25 renderers, one per block type
  views/                       25 templates, public and admin
db/                            9 migrations, applied in filename order
public_html/                   the only web-accessible directory
storage/                       uploads and caches, never deployed
tools/                         setup, migrate, create-user, export, checks
docs/HANDOVER.md               this file
docs/COMPONENTS.md             the component library: agreed scope, not built
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
- **settings** is placement, shared by all blocks: width, background (including
  a colour of your own), spacing, rule, anchor, and a per-block corner radius and
  padding in pixels
- **options** are per-type appearance, from `block_options($type)`

`sanitise_blocks()` strips anything not in those definitions before saving, so
stored JSON can only contain known keys. A new block type needs an entry in
`block_types()` plus a renderer in `app/blocks/`. **No migration.**

**A change of block shape is code, not SQL, and must run before the editor sees
it.** Blocks are JSON on a row, which is what makes a new block type need no
migration; the price is that a *renamed or restructured* field has to be brought
forward by `block_upgrade()` in `blocks.php`. It runs on every path that reads
blocks — rendering, the editor, both previews — because `sanitise_blocks()` drops
anything the definitions do not name: open a page, save it, and whatever was not
migrated is gone. It is idempotent and never runs on save. `do_dont`'s paired
rows becoming two independent lists is the worked example.

**Body text carries bold, italic, code and links — as markers, never as HTML.**
`rich_inline()` in `blocks.php` turns `**bold**`, `*italic*`, `` `code` `` and
`[text](https://…)` into tags at render. **It runs on already-escaped text and
must continue to**: that is the whole safety argument, and it is why "blocks
never render raw HTML" is still exactly true. By the time it sees a value, `e()`
has turned every `<` into `&lt;`, so nothing anyone typed can become a tag, and
the only tags in the output are the four written there. A malformed marker
renders as literal asterisks — visible and harmless.

It also means a pasted Word document arrives as text and stays text: there is no
sanitiser to keep in step with a browser's idea of what it may produce.

Underscores are deliberately *not* italic markers. A brand hub writes down
`--s-radius` and `figma_file_key` constantly.

Only `longtext` fields have it, because only they render through `paragraphs()`.
A single-line label renders through `e()` and would show the markers as
asterisks.

**`serialise()` in `preview-bridge.js` is the exact inverse, and the two have to
stay that way.** Editing in place used to read a field with `innerText`, which
strips tags — so a paragraph containing `<strong>` came back as bare text and the
markers were gone for good, silently, on a page the writer was looking at. It now
walks the DOM back to markers, which is also what makes ⌘B and ⌘I work in the
canvas: whatever `execCommand` leaves behind, `<b>` or `<strong>`, serialises the
same. Anything else a browser produces contributes its text and nothing more.

`check-blocks.php` asserts both directions, including that a typed `<script>`, a
`javascript:` link and a `data:` link never become markup. Verified by removing
the `e()` and watching it fail.

**Radius and padding are per block, in pixels, and blank means follow the site.**
`render_blocks()` emits `--zone-radius` and `--zone-pad` *only* when a block has
a value of its own, so an untouched block gets no inline style at all and the
stylesheet's fallback to the global Appearance setting is what runs. Blank is
therefore stored as blank — never as the global's current number, or moving the
global would stop reaching any block whose Placement panel had ever been opened.
A non-numeric value is treated as blank rather than cast, because `(int) "abc"`
is 0, which is a real setting meaning square corners.

**The zone's padding, radius and `--s-radius-inner` are declared at the very end
of `site.css`**, after the appearance-token layer, because at equal specificity
the later rule wins. The earlier copies were removed rather than left as dead
rules that read as authoritative.

**A background can be a colour of your own.** It is a separate `background_hex`
field rather than a value typed into the select, so switching to a named
background and back does not lose it. Whether the text on it goes white is
decided by `contrast_ratio()` in `render_blocks()`, not by eye: dark text on a
dark colour someone has just chosen is the obvious way for this to go wrong.

**Inheritance:** an option set to `inherit` falls back to the matching global
setting through `block_option($options, $key, $fallback, $siteKey)`. That is
what makes Appearance in the admin move every block not deliberately overridden.

**`do_dont` is two independent lists, not paired rows.** Each column has its own
heading, so it covers Do/Do not, "we say"/"we do not say" and Use/Avoid
without three block types, and the two sides can be different lengths because
nothing on the left is the counterpart of anything on the right. A column with
nothing in it is not drawn, so a one-sided block shows one column rather than
one column and a gap. Old paired content is folded in by `block_upgrade()`.

The 28 block types: heading, rich_text, colour_palette, colour_ramp,
colour_story, type_specimen, do_dont, image, image_grid, comparison_table,
download_list, callout, clearspace, spacing_scale, icon_grid, contrast_pairs,
misuse_gallery, spec_table, link_list, contacts, checklist, video, tabs,
accordion, code, steps, hero, quote. Every one of them has appearance options;
clearspace, video and accordion had none until 26 September and so could not
follow the site's appearance at all.

**Every type also carries a `group` and an `icon`,** which is how the chooser
sorts and illustrates it. `block_groups()` in `icons.php` gives the order and
`block_icon_set()` the shapes. Neither fails loudly — a missing group drops a
block into "Other" at the end, a mistyped icon renders a blank square, and both
look like a decision rather than a mistake — so `check-blocks.php` asserts them.

**`heading` is a block in its own right.** Every other block can carry a heading,
but a title over *several* blocks had nowhere to live except attached to
whichever block happened to come first, and it then moved with that block when
the order changed. Its level and its size are separate settings on purpose: the
level is what the heading means in the outline, which is what a screen reader
follows and what search ranks by, and the size is only emphasis. Tying them
together is the usual reason someone picks the wrong level to get the right size.
It offers h2, h3 and h4 — never h1, because the page already has one and a second
leaves the outline with two roots.

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

**An `editable()` path must be the key in the stored data, never the position
in the drawn list.** Several renderers filter before drawing — a colour with no
hex, an image whose asset has gone, a family with no steps — and
`array_values()` over the result renumbers what is left. A path built from that
renumbering resolves perfectly and edits the wrong row, which is the worst kind
of wrong: nothing errors, the typing just lands somewhere else. Keep the keys
(`foreach ($families as $f => $family)` over a filtered array without
`array_values()`), or carry the original index with each item, as `image_grid`
and `colour_story` do. `check-blocks.php --edit` has a "gappy" pass that leaves
the first row of every repeater blank and compares the text on the page with the
value at the path, which catches exactly this.

**Empty blocks show a placeholder, but only in the editor.** `render_blocks()`
buffers each block in edit mode and, when the output would show nothing,
substitutes the block's label and hint. Without it a newly added block was
invisible on the canvas and had no height to click. It lives in `render_blocks()`
rather than in 25 renderers so it cannot drift, and never runs on a public page.

---

## 3b. Search

`⌘K`, `Ctrl-K` or a bare `/` opens it; so does the trigger, which is a real
link so it still goes somewhere without JavaScript.

**The index is built on the server and filtered in the browser.** Thirty pages
of guidelines is a few hundred kilobytes of text, and a round trip per
keystroke on shared hosting is a tenth of a second each time — the difference
between search that feels instant and search that feels like a form.
`/search.json` serves it, gated exactly as the pages are, and an editor's copy
carries drafts where a reader's does not.

**It is cached to `storage/cache`, keyed on the number of pages and the latest
`updated_at`.** Any edit changes the key, so the old file is simply never read
again; `search_forget_old_indexes()` deletes the ones left behind. Same
approach as the asset bundles.

**Components are in the index too**, at their own URLs, labelled as components —
someone half-remembering a rule does not know whether it was written up as
guidance or built as a component. Their name, summary, group, variant labels and
guidance text are indexed; their markup and CSS are not, or searching "button"
would match every component whose stylesheet mentions one. **The cache key counts
the components table as well as pages**, or a component edit serves a stale index
until someone touches a page.

**Which fields are searchable comes from the block definitions,** so a new
block type is searchable the moment it exists. Only `text`, `textarea` and
`longtext` are indexed — a select holds a keyword the reader never sees and an
asset holds an id — and repeaters are walked, so a colour ramp's family names
and step notes are all in there.

**A heading is its own result.** "Clear space" is a heading halfway down the
logo page, and landing on the page is not the same as landing on the rule, so
headings are indexed separately and link to the block's anchor where it has
one. Ranking puts a title above a heading above body text.

---

## 3a. The navigation

**Two kinds of chrome, decided by whether the page has a rail.** `layout.php`
takes a `chrome` value: `index` (the default — the grid, components, 404) or
`reading`, which `/s/*` passes. It sets a class on `<body>`.

- **index**: the bar is the only navigation, so it carries the mark, the areas
  and the tools, as it always did.
- **reading**: the rail *is* the navigation, so on a wide screen the bar is not
  drawn at all. The rail holds the mark at its head and the account links at
  its foot. Below 56rem the rail becomes a drawer, which cannot hold a mark
  that has to stay visible, so a slim bar returns with the mark, the menu
  button and the name of the section being read.

**The mark is written by `site_mark()`,** because reading pages put it in the
rail and narrow ones put it in the bar. That is the same markup in two places,
which is normally how markup drifts, so it has one source.

**A hero on a section page is bounded by the reading column, not the viewport.**
A full-bleed hero is a landing-page device: it can own the first screen because
nothing on that kind of page competes with it. The heroes here came from a
landing page that had no rail, and putting one above the reading grid pushed
every piece of navigation below the fold. `.opener--column` keeps the opener
inside the column it opens. Full-bleed heroes remain for the grid and anything
else without a rail.

The headline needs no separate measurement for this: `hero_line_width()` gives
each line its width and the CSS divides `100cqw` by it, so the type fits
whatever container it is put in. What did need changing is the cap — the
full-bleed one is `34svh` *per line*, so two lines are 68svh and four run off
the screen. In the column the headline gets a budget for the whole block
(`20rem`, shared between `--lines`), so a long headline sets smaller rather
than taller.

**A rounded thing inside a rounded thing uses a smaller radius.** For the two
curves to stay parallel the inner one has to be `outer − padding`, clamped at
zero; given the same radius the inner corner is too round for the space and the
curves pull apart. Each padded container publishes
`--s-radius-inner: max(0px, calc(var(--s-radius) - <its own padding>))`, and
anything rounded inside reads `var(--s-radius-inner, var(--s-radius, 6px))` —
falling back to the full radius when it is not inside one, which is correct
because then there is no outer curve to sit within.

An element must not both publish and read that token: it would read its own
value rather than its parent's. Cards that contain nothing rounded, like
`.links--cards .links__item`, only read.

At the shipped 6px radius and 1.75rem padding every inner corner is square. It
matters at the Rounded and Pill presets. **The admin does not do this**: it is
Material 3, whose shape scale steps nested components down by name — a card at
`medium` holds fields at `extra-small` — rather than computing it, and mixing
the two systems would be worse than either.

**Rail sizes sit on an 8px scale**, with 4px as the half step: 16px text, 8px
of padding, a 16px indent, and 12px of padding in the drawer for a 50px tap
target. It was 11px and 14px text with 5px and 10px padding, none of which
landed on anything. Written in rem so the type-size setting in Appearance still
moves them together.

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

**Adding a block** opens a centred dialog (`.blockmenu`, built in `editor.js`,
styled in `admin.css`), always centred rather than anchored, because it is opened
from the outline, the canvas and the keyboard. The choices are a **grid of
cards**, `auto-fill minmax(14rem, 1fr)` in a 52rem dialog, so most of the
twenty-eight block types are visible at once; below 30rem it falls back to one
column. Search filters on label, hint and group.

**The cards are grouped and each carries an icon.** Twenty-eight choices in one
undivided grid is a wall; the groups are what make it scannable by someone who
knows roughly what they want but not what it is called. The order comes from
`block_groups()` and runs from what a page is mostly made of to what it
occasionally needs, rather than alphabetically. A group named on a block but
missing from that list still appears, at the end, so a block can never vanish
from the chooser.

---

## 4b. The component library

**Built, all eight phases. `docs/COMPONENTS.md` is the record of what each one
shipped and why; read it before changing anything here.** What is left is
content: no components have been written, and the area is still behind the
`components_enabled` switch.

A Storybook for Years, living in the Components area that `components_enabled`
already gates. One page per component: the real thing rendered live in a
sandboxed iframe, its variants switchable, its code copyable, and a spec table
of radius, colour tokens, spacing and type **derived from the component's own
CSS** rather than retyped — with anything that is not one of the site's tokens
flagged, so the library audits as well as documents.

Four decisions worth knowing without opening the file:

- **Its own table and its own editor**, not blocks on a page row. This goes
  against the rule in section 3, knowingly: variants, a lifecycle and a
  generated spec table do not model as a stack of blocks, and the cost — search,
  versions and autosave built a second time — was accepted rather than
  overlooked. The reasoning is recorded in the spec so it can be argued with.
- **Imported from Figma Dev Mode by paste.** Figma's clipboard is an encoded
  binary of its internal document format; decoding it would mean a kiwi decoder
  in PHP against a schema Figma changes without notice. Dev Mode's generated
  code is text. A pasted binary clipboard is detected and refused with an
  explanation, because it will get pasted.
- **A Figma link is stored, never fetched.** The file key and node id are parsed
  out and kept, so the REST API can be added later without a migration. No
  token, no dependency on a file staying shared.
- **`allow-scripts` without `allow-same-origin`.** The two together are not a
  sandbox — the frame can remove its own attribute. This is also the one place
  in the codebase that renders raw HTML, confined to an opaque-origin frame and
  writable only by an editor.

Eight phases, each a commit and a working state; a usable library exists at the
end of phase 4.

**Phase 1 shipped** `db/009_components.sql` (three tables — `components`,
`component_variants`, `component_versions`), `lib/components.php`,
`/admin/components` with its list, Add, Delete and reorder, and a drawer item
between Assets and People. **The migration needs applying**: push, wait for the
green run, then Settings, Apply updates.

The admin side deliberately ignores `components_enabled` — that switch stops
readers finding an empty area, and having it also lock out the person filling the
area would be perverse. `components_installed()` covers the window between a
deploy and Apply updates with a message rather than the 500 that
`bootstrap.php` would otherwise show, so the drawer stays usable.

**Phase 2 shipped the sandbox**: `app/views/component-frame.php`, the route
`/components/{slug}/frame/{variant}`, `component_frame_csp()`, the component
screen at `/admin/components/{id}` with variants and base CSS on the left and the
live preview on the right, and `assets/js/components.js` for the height bridge and
the switchers. The phase boundary moved deliberately — a sandbox with nothing in
it cannot be judged, so the variants came forward from phase 3.

**The frame is the one place in this project that renders author-supplied HTML,
CSS and JavaScript.** Four things hold it, and all four are load-bearing:

- `sandbox="allow-scripts"` and **not** `allow-same-origin`. Together they are
  equivalent to no sandbox: a frame with both can reach its own element and remove
  the attribute. Without same-origin it is an opaque origin with no access to the
  parent, the session or storage.
- A policy from `default-src 'none'`, adding back only what a component needs.
  Inline script has to be allowed — the height bridge needs it — so the directive
  doing the work is `connect-src 'none'`: whatever runs in there cannot send
  anything anywhere. `form-action 'none'` and `frame-ancestors 'self'` close the
  other two doors.
- The markup never leaves the frame. On the admin screen it goes into a textarea,
  escaped like any other value.
- **`php tools/check-components.php`** asserts all of the above and fails the
  build if any of it slips. Run it after touching anything in this area. It was
  verified by sabotage rather than assumed to work.

**Phase 3 shipped the details editor and version history.** A Details card on the
component screen, `component_figma_parse()` (which reads the file key and node id
out of a pasted link and fetches nothing), and a history at
`/admin/components/{id}/versions` with restore.

Three things about it:

- **No autosave, deliberately.** Prose is typed continuously, so losing a
  paragraph is a real loss; code is edited in deliberate passes and is broken for
  most of the time it is being written, so autosaving it would fill the history
  with snapshots of half-finished CSS and reload the preview into a broken state
  every second. What autosave protects against here is closing the tab with work
  in it, so `components.js` guards that directly.
- **`component_after_write()` is where a version gets recorded**, in the library
  rather than in six routes that each have to remember. Any new write to a
  component or a variant must end with it.
- **`version_prune_history()` in `versions.php` is now shared** by pages and
  components rather than copied, so the thinning policy cannot drift. Its table
  and column are interpolated, not bound, so they are whitelisted against a
  hardcoded map.

**Phase 4 shipped the public side**, which is the point at which the library
became usable by someone who cannot edit: `/components` as a real index and
`/components/{slug}` with the preview, its switchers, a code panel per variant
and the CSS.

- **A thumbnail is the component in its own frame, not a screenshot.** It cannot
  go out of date. One iframe per card, kept reasonable by the default variant
  only, `loading="lazy"`, and no height bridge on thumbnails — twenty frames each
  reporting and resizing would reflow the index continuously as it loaded. It
  also needs `pointer-events: none`, or the component catches the click and the
  card does nothing.
- **An editor sees drafts there**, as they do in search; otherwise you build a
  component, open the public page to check it, and find nothing.
- **Deprecated components stay listed for everyone**, because "do not use this
  for anything new" is what a reader needs to know, and delisting it only means
  someone copies it out of the theme.
- **`component_is_visible()` is one function**, not the same condition written
  out at three routes. The day one of them differs is the day a draft is public.
- **`components_with_variants()` loads the variants in one query**, because the
  index asking per card is a query per card.

**Phase 5 shipped the derived spec** (`app/lib/spec.php`), which is what makes
this an audit as well as a catalogue: radius, colour, spacing and type read out of
the component's own CSS and matched against the appearance settings and the
published palette, with anything off-system flagged and a written note allowed
over any row.

- **It is a text pass, not a parser, and the page says so.** It reads
  declarations. It does not resolve the cascade, follow a `var()` to a value, or
  know which rule wins. Keep the caveat on the page if you extend it.
- **`var(--x)` alone is the token; a `var()` inside a shorthand is only partly
  tokenised**, because `border: 1px solid var(--rule)` has a hardcoded width in
  it. Neither is flagged, because shorthand is not taken apart.
- **`6px` is never matched against a token held in `rem`.** Converting would mean
  assuming a root font size, and the body size is itself a setting.
- **`design_colour_tokens()` in `styles.php` is now the one source** for
  `/tokens.css` and for the spec's matching, so a colour cannot be a token in one
  and unknown in the other.
- **The class prefix is `devspec`, not `spec`** — the `spec_table` block already
  owns `.spec` on the public side, and `.spec { display: grid }` took the table
  apart. Check a class name against the stylesheet before using it; 28 block types
  have had most of the obvious ones.

**Phase 6 shipped the import** (`app/lib/import.php`). One box on the component
screen; what was pasted is worked out rather than chosen from a menu.

- **Always two steps.** The first only looks and shows what it would do, the
  second writes. Dev Mode's output is close but rarely shippable, so it is never
  saved on the strength of a paste — and the derived spec runs on the incoming
  CSS on the review screen, so a hardcoded colour is flagged before it is
  accepted.
- **Figma's binary clipboard is recognised and refused, with an explanation.**
  It is checked first, because it arrives as an HTML fragment and would otherwise
  be taken for markup. Refusing it clearly is the feature: it is the obvious thing
  to try, and a blank result would read as the import being broken.
- **Applying is additive.** CSS is appended, not substituted, because an import is
  usually one more state rather than a fresh start.

**Phases 7 and 8 finished it.** A component's written guidance is edited in the
*same* block editor the guidelines use — `editor.js` gained `data-save-url` and
drops the page fields when there is no page, so twelve hundred lines are reused
rather than copied. It needs a `#page-settings-template` to exist, so the
component's version holds an explanatory note; without one the inspector throws
on first render.

And components are in search. **The cache key had to gain the components table**:
keyed on pages alone, editing a component would have served a stale index for as
long as nobody touched a page, and nothing would have looked wrong. The name,
summary, group, variant labels and guidance text are indexed; the markup and CSS
are not, because searching "button" should not match every component whose
stylesheet mentions one.

**The preview's `postMessage` does not validate origin, and that is correct** —
the frame's origin is `"null"` because it is sandboxed, so there is nothing to
compare. `event.source === frame.contentWindow` is the check that works, and it
cannot be forged. This is a deliberate exception to the rule in section 4; do not
"fix" it.

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
| No top bar while reading | The rail is the navigation there; a second bar carried a label and a sign-in link and took 4.25rem to do it |
| Section openers sit in the reading column | A full-bleed hero is a landing-page device, and above a rail it puts every piece of navigation below the fold |
| Shopify storefront | Components, when built, are Liquid sections, not React |
| Components get their own table, against the rule above | Variants, a lifecycle and a generated spec table do not model as blocks. See `docs/COMPONENTS.md` |
| Figma Dev Mode paste, not the clipboard or the API | The clipboard is an encoded internal format; the API needs a token and a permanently shared file |

---

## 6. Traps, every one of which has bitten

**MySQL escapes backslashes in string literals.** JSON written into a migration
needs `'` doubled *and* `\` doubled, or `\n` becomes a real newline and the JSON
is invalid. This silently broke a whole page. Test by simulating MySQL's
unescaping, not by running the SQL in SQLite, which does not escape backslashes
and hides the bug entirely.

**More Years has one weight, and asking for another gets a faked one.** The
browser smears the glyphs sideways: the headline looks doubled and the dot of
an i becomes a ring. It happened because the site's heading weight is set by
`.reader__body h1`, which is more specific than `.hero__headline`, and moving
the opener into the reading column brought it under that rule for the first
time. `.hero__line` now pins `font-weight: 400` and `font-synthesis: none`, so
a request for a weight the face does not have renders the real face rather
than a manufactured one. Worth remembering for any single-weight face: the
headline is fitted using the real face's measured widths, so a synthesised one
would not fit even if it looked acceptable.

**A `@font-face` in a static stylesheet cannot know whether its file exists.**
Four Plus Jakarta Sans faces were declared in `site.css` with a note saying
they were harmless while the files were absent. They were not: every page load
fetched four woff2 files and got four 404s. They are written by `font_head()`
in `settings.php` now, which declares only the weights actually on disk and
links Google Fonts when there are none — so a half-finished upload degrades to
the weights present instead of a page of failures.

**A media query placed before the rule it overrides does nothing.** The worst
version of the duplicate-property trap, because it reads as correct: the query
states what should happen at that width, and at equal specificity the later
rule wins anyway. It cost a drawer's tap targets, an opener's corners, and the
reduced-motion rule that was supposed to stop the hero headline being held
invisible — all in one sitting. Put narrow-screen rules **after** the rules they
override, next to them, not in a shared block further up the file.
`check-css.js` reports these as `OVERRIDES A MEDIA QUERY`.

**A `#` inside a character class ends a `#`-delimited pattern.** PHP reports
"Unknown modifier ']'", which points nowhere near the cause, and the `preg_match`
returns false so the surrounding code carries on as though nothing matched.
`'#[?&]node-id=([^&#]+)#i'` cost an afternoon's worth of confusion in
`component_figma_parse` before a check caught it. Use `~` as the delimiter when
the pattern needs a literal `#`.

**`view()` uses `extract()`, so a template shares one scope with its caller.** A
template that loops `as $component` leaves `$component` set to the last row, and
anything rendered afterwards in the same scope quietly gets it. It cost an
afternoon in `check-components.php`, where the admin list's loop meant the public
component page below it rendered for the wrong component — and reported as a
failing assertion rather than as the muddle it was. Re-establish what a view needs
immediately before rendering it, rather than relying on what is already in scope.

**A view's warnings are its bugs, and nothing shows them.** `display_errors` is
off on the server, so "Undefined variable" in a template renders a page that looks
almost right — an empty datalist, a missing chip — and says nothing. Worse, a
check that renders views inside `ob_start()` swallows its own error output into
the buffer and throws it away, so the count goes up and nothing says why.
`check-components.php` turns warnings into failures and writes them to STDERR for
exactly these two reasons. Both bit on 27 September.

**A more specific rule can undo a positioned element.** The canvas overlay sets
`[data-block].is-selected > * { position: relative }` to lift a block's content
above its tint. The block's label is appended as a direct child, so that rule —
two classes and an element — beat `.ed-label { position: absolute }`, turned it
into `position: relative`, and dropped the label out of its corner into normal
flow at the *end* of the block. It rendered at the bottom left and looked like a
deliberate, odd design choice for weeks. The selector excludes it now. Whenever a
rule targets `> *`, ask what else is a child.

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

**An `editable()` region must contain the text and nothing else.** The two-column
guidance block draws a tick before each column heading, and marking the whole
heading editable meant editing it in place read the tick back as part of the
label — so the first keystroke would have stored "✓ We say". Wrap the words in
their own span, as an accordion's summary already does. `check-blocks.php --edit`
catches this by comparing the text on the page with the value the path points at,
which is exactly what it was written for.

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

**Working:** deploy pipeline, three-column editor, 28 block types, 26 numeric
appearance settings, asset library with generated image variants and gated
downloads, upload from inside the editor's picker, sections as scrolling pages
with a hero opener, version history with collapsing and pruning, people and
roles, settings, logo, area toggle, backup export, sign-in throttling, two
kinds of chrome with the mark at the head of the rail while reading, mobile
slide-out drawer, filtering on the admin pages list, text edited in place on
the canvas, and the component library end to end — sandboxed live previews,
variants, the derived spec, the Figma paste, guidance in the block editor and
components in search.

**Content:** 30 pages scaffolded as drafts. One written: Tone of voice. No
components written yet.

**Not built:** motion page content,
type exports in `/tokens.css`, password reset, pagination (deliberately
deferred).

**The asset picker is one file, `picker.js`,** loaded before both `admin.js` and
`editor.js` and used by block fields, page covers and the site logo. It was
written twice and the two copies had already diverged; anything added to it
belongs there, not in a caller.

---

## 9. Outstanding jobs

1. **Write the pages.** The only thing on the critical path; the build is well
   ahead of the content.
2. Re-upload or Replace existing images so variants are generated; variants are
   only made on upload.
3. Self-host Plus Jakarta Sans: four woff2 files (400, 500, 600, 700) into
   `public_html/assets/fonts/`, named `plus-jakarta-sans-400.woff2` and so on.
   `font_head()` picks them up, declares only the weights it finds and stops
   linking Google Fonts. Nothing needs editing, and a partial upload is safe.
   Deliberately not done yet.
4. Turn `debug` off in `app/config.php` on the server.
5. Decide the real domain.
6. Instrument the remaining block text for editing in place, if any turns out to
   be missing. Everything visible is covered bar the deliberate exceptions.
7. `/tokens.css` reads only `colour_palette`, so a `colour_ramp` exports nothing
   and neither does `colour_story`. It exports no type either. Search indexes
   all three; the token export has not caught up.
8. Deferred from the audit: section page caching, password reset.
9. **Write some components.** The library is built and empty, which is the same
   position the guidelines are in. The button and the product card are the two
   most often got wrong.
10. **Turn the Components area on** in Settings once there is something in it. The
    public pages exist now, but readers cannot reach them while the switch is off.

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
php tools/check-components.php
node tools/check-css.js public_html/assets/css/site.css public_html/assets/css/admin.css
```

The last five were ad-hoc scripts and are now committed, because each of them
has caught something real:

- `check-routes.php` renders no pages; it asserts every URL the interface calls
  matches a route. It found a route that was never inserted and two patterns
  with one backslash too many.
- `check-blocks.php` renders all 28 block types three times — empty, full, and
  "gappy", which is full but with the first row of every repeater left blank —
  through the sanitiser first, and reports anything that throws, shows nothing,
  or puts an empty value into an attribute that will be read as a colour or a
  link. It needs no database. `--edit` additionally requires every empty block
  to be visible, which is what the editor canvas depends on, and checks that
  every `editable()` path resolves *and* that the text on the page matches the
  value it points at. The gappy pass exists for that last check: it is the only
  one that produces a list with a hole in it.
- `check-components.php` asserts the things the preview frame's safety rests on:
  the sandbox attribute read off the rendered page and compared exactly, the
  content security policy's `'none'` directives, that a hostile variant's markup
  is escaped on the admin screen and live only inside the frame, and that a
  `javascript:` URL never becomes an `href`. Every one of those fails silently in
  the browser — the page renders and looks correct — which is why they are
  asserted rather than reviewed. It needs no database.
- `check-css.js` checks braces balance, finds any property set twice on the
  same selector in the same at-rule context, and flags a rule inside a media
  query that a later rule outside it overrides. Read its output rather than
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
