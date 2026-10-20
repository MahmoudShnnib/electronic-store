# Admin dashboard UI

The admin dashboard (`/admin`, everything under `resources/js/pages/admin/`) is built on
**Ant Design Vue 4** (`ant-design-vue`, `@ant-design/icons-vue`). So is the storefront —
see `storefront-ui.md`, which covers the shop's own theme and class hooks and assumes you
have read this file. There is no second component library in the project any more. Icons
everywhere are `lucide-vue-next`, not AntD's own icon set.

This file is the map for changing how the dashboard looks. Read the "Global CSS interop" section
before debugging any layout that looks broken across many pages at once — it is almost always one
of the two conflicts documented there, not a per-page mistake.

## Where the design actually lives

| What you want to change | File |
| --- | --- |
| **Any colour, radius, font or spacing value** | `resources/js/theme/adminTokens.ts` — the only place raw values live (see `design/ADMIN_STYLE.md`) |
| How tokens reach AntD and CSS | `resources/js/composables/useAdminTheme.ts` (used by `AdminLayout.vue` and `PosLayout.vue`) |
| Sidebar links, grouping, permissions | `resources/js/composables/useAdminNav.ts` (the data — also feeds the command palette); `components/admin/AdminNav.vue` renders it |
| Top bar, sider/rail/drawer shell, quick-create menu | `resources/js/layouts/AdminLayout.vue` |
| Command palette (Ctrl/Cmd+K) | `components/admin/CommandPalette.vue` + `admin.search` (`Admin\SearchController`) |
| "Needs attention" tiles and sidebar count badges | `App\Services\Admin\AdminAttention` (dashboard prop + `admin.attention` JSON) |
| Anything shared across pages (tables, filters, modals, form fields) | `resources/js/components/admin/*` |
| Global CSS that AntD and Tailwind fight over | the AntD interop block in `resources/css/app.css` |
| The dashboard's whole look (sidebar, top bar, cards, KPI boxes, tables, pills, modals) | the "Admin theme" block in `app.css` — every value is a `var(--ad-*)` |
| Bidi rules shared with the shop | the "Admin dashboard chrome" block, same file |
| One page only | that page's `.vue` file |

Prefer the shared components over per-page markup. Most pages are already a thin composition of
`PageHeader` + `FilterBar` + `DataTable` + `Pagination`.

## Theme

**2026-10-06 restyle.** The admin no longer calls `useAntdTheme`. `useAdminTheme()`
builds the AntD theme from `theme/adminTokens.ts` (palette per mode, radius, font
size) and writes the same tokens onto `<html>` as `--ad-*` custom properties, which
the "Admin theme" CSS block and the Tailwind `ad-*` colours (`bg-ad-card`,
`text-ad-muted`, `border-ad-line`…) read. Chart.js code gets raw colours from
`useAdminColors()`. The admin font is IBM Plex Sans Arabic (Bunny Fonts, injected by
`useAdminTheme`); the Tajawal metric overrides below now only matter to the shop.
The table/card/pill look rules that used to be shared with the storefront were
re-scoped to `:has(.store-shell)` (same rendering there) so the admin could change
alone. `useAntdTheme` itself is unchanged — the storefront still uses it.

The rest of this section describes the mechanism both composables share.

`useAntdTheme(token)` returns a reactive AntD theme config and is passed to the `ConfigProvider`
that wraps the whole admin layout. It:

- mirrors the app's Tailwind `.dark` class onto AntD's `darkAlgorithm` / `defaultAlgorithm`,
  watching `documentElement` with a `MutationObserver` (AntD's CSS-in-JS never sees the class
  itself, so the swap has to be explicit);
- pins `fontFamily: 'inherit'` so Tajawal survives instead of AntD's own Latin stack;
- merges whatever token overrides the caller passes. `AdminLayout.vue` passes
  `{ colorPrimary: '#2563eb', borderRadius: 6 }` — change the brand colour there.

### Swapping it

`useAppearance` owns the swap and is the only thing that writes the `.dark`
class. It exports a shared `isDark` ref that `useAntdTheme` reads directly —
deliberately *not* a `MutationObserver` on `documentElement`, which is what it
used to be. Going through the DOM meant Tailwind's class landed a frame or two
before AntD had regenerated its tokens, so the page sat visibly half-light and
half-dark while ~18 style tags were injected.

The user-initiated change then runs inside `document.startViewTransition`, which
holds the old frame until the callback resolves and cross-fades once. Two
things to know before editing that callback:

- **Never await a frame inside it.** Rendering is paused for the duration, so
  `requestAnimationFrame` never fires and the transition dies with
  `TimeoutError: Transition was aborted because of timeout in DOM update`.
  `await nextTick()` is the right and sufficient wait — Vue's flush is where
  AntD re-renders and injects.
- **The transition's promises reject when it is skipped**, which is what a
  second toggle mid-fade does. All three are caught and dropped on purpose;
  without that they surface as unhandled `AbortError: Transition was skipped`.

Browsers without the API, and anyone with `prefers-reduced-motion: reduce`, fall
through to a plain immediate swap. Timing lives in the "Theme swap" block in
`app.css`.

`ant-design-vue/dist/reset.css` is deliberately **not** imported anywhere. Tailwind's preflight
already normalises, and AntD's reset re-declares `html` font-family plus heading/paragraph
margins, which fights it.

The same `ConfigProvider` also drives locale and direction: `locale` picks `ar_EG` / `en_GB` for
AntD's own strings (calendar, table, pagination), `direction` flips the whole tree to RTL, and a
`watchEffect` keeps `dayjs.locale()` in step.

## Shared components (`resources/js/components/admin/`)

| Component | Notes |
| --- | --- |
| `DataTable.vue` | Carries `.admin-table` (the card edge — see Tables below). AntD `Table` behind the original slot API — call sites pass `columns` / `rows` and fill `#cell-<key>`, `#row-actions`, `#select`, `#row-select`, `#empty`. Used by 16 pages. Sorting is **server-driven**: it emits `sort` and re-reads `sortOrder` from props each render, overriding AntD's internal three-state cycle. |
| `Pagination.vue` | Takes the whole Laravel paginator as `:paginator`, not the `links` array. Navigation rewrites `page` on the current URL, so active filters survive without this component knowing about them. |
| `FilterBar.vue` | Debounced search (400ms) + N selects; pushes state into the query string via Inertia `router.get` with `preserveState`/`replace`. The search field is a plain `Input` with a prefix icon — `InputSearch`'s attached button was a dead control next to the debounce. |
| `PageHeader.vue` | Title + subtitle + `#actions` slot. Every index page starts with one. |
| `StatCard.vue` | AntD `Card` + `Statistic`. `tone` maps to a colour (AntD's `Statistic` takes a colour, not a class). Carries `h-full` so a card with a `hint` doesn't make its grid row ragged. |
| `StatusBadge.vue` | AntD `Tag` with a status-slug → preset-colour map. Falls back to `admin.common.<status>` translations before showing the raw slug. |
| `Field.vue` | Presentational AntD `FormItem`. Validation is server-side (Inertia `errors`), so status/message come from props, never from an AntD validation rule. |
| `FormModal.vue` | `footer: null` — every call site renders its own submit row inside the default slot. Body is capped at `calc(100vh - 10rem)` with `overflow-y: auto` so tall forms scroll instead of overflowing the viewport. |
| `ConfirmDialog.vue` | AntD `Modal` with ok/cancel, `danger` and `processing` flags. |
| `AdminSelect.vue` | AntD `Select` behind the original `v-model` contract. Values round-trip loosely on purpose (Inertia sends ids as numbers, option lists may carry strings); `''` means "nothing selected". |
| `AdminDateInput.vue` | AntD `DatePicker` behind a plain string contract: `YYYY-MM-DD`, or `YYYY-MM-DDTHH:mm` with `withTime`; `''` means unset. Localisation/direction come from the layout's `ConfigProvider`. |
| `HelpHint.vue` | Circled `?` with a tooltip, for meanings a label cannot carry. See "Explaining things" below. |
| `types.ts` | `Paginator<T>` and `Option`. |

Deleted during the migration: `EmptyState.vue` (dead code — the storefront has its own) and
`ui.ts` (the old shared Tailwind class-name constants, orphaned once every component became AntD).

## The font's own metrics (`@font-face` overrides, top of `app.css`)

Tajawal is declared by the app, not pulled from Bunny's stylesheet, because its
vertical metrics need correcting. Text only looks centred in a line box when the
font's `ascent - descent` equals its cap height. Tajawal ships hhea 643/-357
against a cap height of 633 — a difference of 286, not 633 — so every glyph
rendered **173/1000 em above the middle of its own line**: 2.4px at 14px, 4px at
24px. Icons sit where flexbox puts them, so icon and label disagreed in every
button, pill, field label and stat card at once.

Blink reads hhea unless the OS/2 `USE_TYPO_METRICS` bit is set, which this font
leaves off. On Windows it falls back to the (correctly centred) `usWin*` pair
instead, so the same page looked right on one machine and wrong on another —
worth remembering before trusting a screenshot from someone else's OS.

`ascent-override: 81.65%` / `descent-override: 18.35%` / `line-gap-override: 20%`
rebuild the metrics around the cap band. Ascent and descent still sum to 1em and
the gap is unchanged, so `line-height: normal` stays 1.2 and nothing changes
height — only the glyphs move down into the middle of the line.

Two consequences worth knowing:

- **Don't re-add a `<link>` to a hosted Tajawal stylesheet.** A second set of
  `@font-face` rules for the same family would race these and silently win or
  lose depending on load order.
- **Changing the typeface means redoing this.** Measure the new font's cap
  height and hhea pair before assuming it is centred; most UI faces are, and
  need no overrides at all.

## Global CSS interop (`resources/css/app.css`, bottom of file)

Three AntD ⇄ Tailwind conflicts are fixed globally. All produced defects that *looked* like dozens
of unrelated per-page bugs. If you see something similar, suspect a fourth conflict of the same
kind before editing call sites.

**1. Icons inside AntD controls.** Tailwind's preflight sets `svg { display: block }`. AntD's
button is `display: inline-block` and expects an inline icon, so a lucide `<svg>` in an icon slot
forced a line break — the label dropped under the icon and out of the button's fixed height, on
every icon+label button in the dashboard. Fixed by making `.ant-btn` `inline-flex` with a `gap`.

The selectors are **doubled** (`.ant-btn.ant-btn`) on purpose: AntD injects its CSS-in-JS at
runtime, i.e. after this stylesheet, so a single class would lose the cascade. Keep that pattern
for any new override in this block.

**2. Utility classes vs AntD's scoped selectors.** AntD scopes
`.ant-table-thead > tr > th { text-align: start }` at specificity 0-1-2, which outranks a
single-class utility (0-1-0). Columns configured with `class: 'text-end'` therefore aligned their
body cells but not their headers. Fixed by re-stating the alignment at AntD's own specificity.

Symptom to watch for: some call sites using the `!important` variant of a utility and some not.
That inconsistency is evidence of an unresolved specificity fight, not a style preference.

**3. Flex spans inside AntD buttons.** ant-design-vue styles `.ant-btn > span
{ display: inline-block }` above a single utility class, so a `<span class="flex items-center
gap-2">` holding an icon + label inside a `Button` silently loses its flex layout — the icon and
label stack and escape the button's fixed height. Diagnosed by comparing `getComputedStyle`
(`display: block`) against the utility's intent; the screenshots alone looked like a wild layout
bug. Fixed globally: `html:has(.admin-shell, .store-shell) .ant-btn > span.flex
{ display: inline-flex }`.

## Chrome, surfaces and scrollbars

The "Admin dashboard chrome" block in `app.css` finishes what the AntD migration left to the app.
Five class hooks, all set in `AdminLayout.vue` or `DataTable.vue`:

| Class | On | What it does |
| --- | --- | --- |
| `.admin-shell` | the outer `Layout` | Pins the root font size (above) and paints the content field: `#f5f6f8` light, `#0a0a0a` dark. Light mode previously had white cards on a white page, so nothing read as a surface. Also the marker every `html:has(…)` rule keys off. |
| `.admin-sider` | the desktop `Sider` | Makes AntD's `.ant-layout-sider-children` a flex column, so the brand row stays pinned and only the nav scrolls. |
| `.admin-header` | the `Header` | Translucent + `backdrop-filter`, with an opaque fallback outside `@supports`. The header is the only chrome content scrolls beneath, so it is the only surface that gets the treatment. |
| `.admin-scroll` | the sider's nav region | Overlay scrollbar: gutter always reserved, thumb painted only on hover. The native 15px bar rendered as a bright grey strip and ate 15px of the sider's 256px. Shared with `.ant-drawer-body` and `.ant-modal-body`. |
| `.admin-table` | `DataTable`'s wrapper | The outer card edge only — border plus 10px corners. |
| `.admin-hint` | `HelpHint`'s button | The inline explanation affordance — muted until hovered or focused. |

### Status pills

AntD's preset tag colours are overridden wholesale for the dashboard. Its own
`success` sat at `rgb(22,35,18)` against a `rgb(20,20,20)` card — a chip with no
edge — and the light equivalent was a near-white wash. The replacements are one
system across both themes: a tint that carries enough hue to read as a chip, a
border a step stronger, and label text dark-and-saturated on light / bright on
dark. Every pair clears 4.5:1; tags are 12px, which AA counts as small text.

Keyed off AntD's own `.ant-tag-*` classes, so `StatusBadge`'s slug → preset map
is still the only place a new status picks its colour. `blue` and `green` are
the static presets a few call sites pass directly and share the `processing` and
`success` families on purpose.

### Explaining things (`HelpHint`)

A circled `?` that reveals one or two sentences on hover **and on focus** — it
renders a real `<button>` so the explanation is keyboard-reachable and announced,
not hover-only trivia. Three shared components take an optional `help` prop and
place it for you: `Field` (beside the label), `StatCard` (beside the figure's
label) and `DataTable`'s `Column` (beside the column heading). Copy lives under
`admin.help.*` in both locales.

Use it for figures and columns whose name does not say what they hold —
`Available` vs `Reserved` vs `Incoming`, `Est. profit`, `Outstanding`. **Verify
the formula in the service before writing the sentence**; a confidently wrong
tooltip is worse than none. Icon-only buttons are a different problem and are
already covered by `Tooltip` + `aria-label` everywhere.


### Tables

`bordered` is deliberately unused. It drew a full grid and turned every list into a spreadsheet.
The interior treatment — horizontal rules only, header weight and divider, 16px cell insets at the
card edge, overlay horizontal scrollbar, centred empty row — applies to **every** table in the
dashboard, including the raw `<Table>`s in reports and detail pages. Only the outer card edge is
opt-in via `.admin-table`, because a table filling a `Card` body already has one; adding it there
would double the border.

`.admin-totals` is the matching hook for a `Descriptions` block used as a totals column: it pushes
the figures to the trailing edge so they land in the same column as the table's own total.

## Bidirectional text

Arabic is not a mirrored copy of English — the two share a page, and the bidi algorithm reorders
anything neutral it finds. Three defects came out of that, all now handled centrally:

1. **Numeric timestamps reversed.** `2026-08-23 23:57:35` is digits and separators with no strong
   character, so inside an RTL paragraph the space between the two digit runs takes the paragraph
   direction and the browser renders `23:57:35 2026-08-23`. Table cells, descriptions values, and
   form fields get `unicode-bidi: plaintext`, which resolves direction per element from its own
   content. Because `plaintext` also makes `text-align: start` resolve against the *cell's* direction
   (one column, two alignments), alignment is restated physically per document direction right below
   those rules. **Keep the pair together if you touch either.**
2. **Dates inside sentences.** `plaintext` cannot help when the timestamp is interpolated into an
   Arabic string, so every `{date}` placeholder in `ar.json` is wrapped in U+2066 / U+2069
   (LTR isolate / pop). Same technique `App\Support\Money::format` already uses for amounts — copy
   it for any new Arabic message that embeds a number or a timestamp.
3. **User data in an unknown language.** Database content (a status comment, an actor name) gets
   `dir="auto"` on the element. AntD stamps `direction: rtl` on its Typography under an RTL
   `ConfigProvider` and the CSS property outranks the attribute, so `html:has(.admin-shell)
   [dir='auto']` re-asserts `plaintext`.

## Scoping: `.admin-shell` vs `html:has(.admin-shell)`

AntD portals `Modal`, `Drawer`, `Select` dropdowns and `DatePicker` panels to `<body>` — outside the
`.admin-shell` element. A descendant selector rooted at `.admin-shell` silently misses all of them,
which is how the form-field bidi fixes worked on the page and not inside any modal.

Rule of thumb: style the shell element itself with `.admin-shell`; style *content* with
`html:has(.admin-shell)`, which scopes to the document while the dashboard is mounted and follows
content into portals.

**The trap that comes with it:** `dir` and `.dark` both live on `<html>`, so once a selector is
rooted at `html` they have to be *compounded onto it*, never used as an ancestor. These match
nothing, silently, and the rule just falls back to its light/LTR value:

```css
.dark html:has(.admin-shell) .ant-card { … }        /* never matches */
[dir='rtl'] html:has(.admin-shell) td { … }         /* never matches */
```

```css
html.dark:has(.admin-shell) .ant-card { … }         /* correct */
html[dir='rtl']:has(.admin-shell) td { … }          /* correct */
```

Worth checking whenever a dark-mode or RTL override "does nothing" — the selector is a more likely
culprit than specificity.

## Layout structure

`AdminLayout.vue` renders `ConfigProvider > Layout > (Sider | Drawer) + Layout > (Header + Content)`.

**2026-10-18 shell redesign** ("simpler and more powerful"):

- **Navigation is data** in `useAdminNav.ts`: the dashboard link, five working groups
  (Sales · Products · Stock & purchasing · Online store · Finance & reports) and a bottom
  block (Team & access, Settings). Every link carries the `permission` its controller
  checks; `super_admin` passes everything. A group left with one permitted link renders as
  that link. Storefront editing is all under **Online store**: the page builder
  (`admin.pages.*`) first, then the old hero/tiles screen (`admin.homepage.index`, labelled
  "current shop" until the SPA replaces the Inertia shop), coupons, reviews.
- **Sider** is `!fixed`, 256px, or a 72px **icon rail** (toggle in the top bar's first
  button, remembered in `localStorage` as `adminSidebarRail`). In the rail a group opens as
  an AntD `Popover` flyout on the inline-end side (`rightTop` LTR / `leftTop` RTL — AntD
  does not flip placements itself). The content `Layout` offsets with `lg:ms-64` /
  `lg:ms-[72px]`. Below Tailwind `lg` (1024px) the same button opens a **Drawer** instead;
  the JS media queries in the layout use Tailwind's breakpoints, not AntD's 992px.
- Group open/closed state persists as `adminNavGroups.v2`; the group holding the current
  page always opens. Active link: longest path match, on path segments.
- **Top bar**: sidebar toggle · search field (a button that opens the palette) · Create menu
  (`quickActions` — new product / manual order / purchase order, permission-gated) · POS ·
  storefront / language / theme (from `md` up; inside the account menu on phones) · account.
- **Command palette** (`Ctrl/Cmd+K`, or `/` when not typing): pages and actions are matched
  locally in *both* languages with Arabic folding (diacritics, alef/yaa/taa-marbuta forms);
  2+ characters also query `admin.search`, which returns `null` for any section the user may
  not open. Products link to the edit form only with `products.update`.
- **Badges**: `useAttentionCounts()` reads `admin.attention` once and then at most once a
  minute on navigation (the layout persists across visits); the dashboard seeds it from its
  own `attention` prop. A closed group shows the sum of its links' badges.
- `app.ts` follows `page.props.locale` on every Inertia visit. Before, signing in (which
  switches to the account's saved locale server-side) left the guest page's strings and
  direction in place until a reload — English text in an RTL shell.

## Gotchas when editing

- **The admin opts out of the root font-size scaling.** `app.css` scales `html` for the storefront
  (16px, 17px ≥768px, 20px ≥1280px), then pins it back to 16px under `html:has(.admin-shell)`.
  Without that pin every rem utility in the dashboard was 25% larger than the AntD component it
  decorated: a 256px `Sider` under a 320px `lg:ms-64` (a 64px black gutter down the page), 17.5px
  labels beside 14px table text, 20px icons in 14px icon slots. If you find a size that looks off by
  a quarter, check you are not reintroducing a rem/px mismatch rather than nudging a number.
- **RTL.** Use logical utilities (`ms-`/`me-`, `start-`/`end-`, `text-end`) rather than
  `left`/`right`. But an element that carries its own `dir="rtl"` must not also own its directional
  spacing — logical properties resolve against the element's own direction, so the gap lands on the
  wrong side. Put the spacing on the parent (flex `gap`) instead. See the bilingual name cell in
  `pages/admin/categories/Index.vue`.
- **Table column alignment.** `align: 'right'` emits `text-align: right`, which stays pinned right
  under RTL. Use the `text-end` class instead — `DataTable` already does this for its actions
  column.
- **Popups are portalled to `<body>`** (selects, date pickers, dropdowns). They still pick up the
  theme through `ConfigProvider` context, but they are outside any `.dark`-scoped Tailwind, so
  don't style them with Tailwind dark variants.
- **Charts are Chart.js** (`vue-chartjs`), not AntD. They need explicit help on the dark theme:
  doughnut arcs default to a white border (`borderWidth: 0` on the dataset), and a sparse bar
  series will stretch one bar across the whole plot (`maxBarThickness`).
- **Hardcoded English.** A few strings still bypass i18n. When you touch a page, check its labels
  render in Arabic before calling it done.

## Verifying a change

```
npm run lint
npm run build
```

`vue-tsc --noEmit` currently fails on a pre-existing `tsconfig` issue (`types: ['./resources/js/types',
'vue/tsx']`), unrelated to the UI — don't chase it.

Visual QA is worth doing in all four combinations: light/dark × LTR/RTL. The theme toggle and
language switch are in the top bar from `md` up, and in the account menu on a phone.

## Web Interface Guidelines pass (2026-09-01)

The dashboard was included in the storefront's guidelines audit
(`storefront-ui.md` has the full list — the shared CSS block at the bottom of
`app.css` covers both shells). Dashboard-specific outcomes:

- **The returns lightbox is an AntD `Modal`,** not the hand-rolled overlay it
  used to be. Escape, the focus trap and restore-focus-on-close come with the
  component; the overlay had none of the three.
- **`FilterBar`'s search field is `type="search"`, `spellcheck="false"`,
  `autocomplete="off"`, with an `aria-label`** — a placeholder is not a label.
- **The purchase-order form formats its own figures through
  `resources/js/lib/money.ts`,** the client-side twin of `App\Support\Money`
  (amount first, three fraction digits, LTR isolate). It exists only for totals
  the browser computes while lines are still being edited — everything else is
  pre-formatted by the server, and should stay that way.
- **Every `<img>` carries explicit `width`/`height`.**

Verified by `npm run lint`, `npm run build`, `pint --dirty` and the Pest suite;
the dashboard was **not** clicked through in a browser this pass — the session
had no admin login and entering credentials was out of scope.
