# Admin dashboard design pass

A polish pass over the Ant Design admin dashboard, on top of the AntD migration
that landed earlier on `design-tweeks`. 30 files.

This is the change record: what moved and why. For how the dashboard is built
now — the class hooks, the scoping rules, the traps — read
[`docs/agents/admin-ui.md`](agents/admin-ui.md), which was updated alongside it.

**Verification.** `npm run lint`, `npm run build` and `./vendor/bin/pest`
(149 passed, 660 assertions) all clean. `./vendor/bin/pint --dirty` passes; no
PHP changed. `vue-tsc --noEmit` still fails on the pre-existing `tsconfig`
issue (`types: ['./resources/js/types', 'vue/tsx']`) — unrelated, don't chase.
Visual QA covered light/dark × LTR/RTL at 1920, 1536, 1456, 820 and 612px.

---

## 1. The reported symptom, and what was actually wrong

The bug report was a black gutter down the left of the page and buttons whose
icons sat wrong. Both came from the same place.

`app.css` scales the root font size for the storefront — 16px, 17px ≥768px,
**20px ≥1280px**. Tailwind's utilities are rem and scale with it. Ant Design's
tokens are px and do not. So above 1280px:

| | intended | actual |
| --- | --- | --- |
| content offset (`lg:ms-64`) | 256px | **320px** |
| Sider width (AntD `:width="256"`) | 256px | 256px |

64px of exposed page background — the gutter. The same mismatch was behind
everything else that looked "slightly off": 17.5px Tailwind labels beside 14px
AntD table text, 20px lucide icons in 14px icon slots, a `h-72` chart box
rendering at 360px.

**Fix:** pin the root back to 16px inside the admin shell, so both systems sit
on one scale. The storefront keeps its scaling.

```css
html:has(.admin-shell) { font-size: 16px; }
```

One rule, and the whole class of alignment defects goes with it. The old
"root font size scales" gotcha in `admin-ui.md` was inverted to match.

---

## 2. Chrome and surfaces

- **Sidebar brand row pinned.** The Sider scrolled as one block, so the store
  name scrolled away with the nav. AntD wraps slot content in its own div, so
  the column layout lives on `.ant-layout-sider-children`.
- **Overlay scrollbars** (`.admin-scroll`). The native 15px bar rendered as a
  bright grey strip down the sider's inner edge and took 15px out of the 256px
  it was given. Gutter now always reserved, thumb painted only on hover. Shared
  with `.ant-drawer-body` and `.ant-modal-body`.
- **Header is the one translucent surface.** It is the only chrome content
  scrolls beneath, so it gets `backdrop-filter` with an opaque fallback outside
  `@supports`. Everything else stays solid.
- **Light mode got a content field** (`#f5f6f8`). It was white cards on a white
  page — nothing read as a surface. Dark mode already had the separation.
- Dropped three `!important` overrides on the avatar button that the global
  `.ant-btn` rule already handles.

## 3. Tables

`bordered` drew a full grid and turned every list into a spreadsheet. Removed
from all 10 call sites across 5 files; horizontal rules only.

The interior treatment — header weight and divider, row separators, 16px cell
insets at the card edge, overlay horizontal scrollbar, centred empty row —
applies to **every** table in the dashboard, including the raw `<Table>`s in
reports and detail pages. Only the outer card edge is opt-in via `.admin-table`,
because a table filling a `Card` body already has one.

Trailing action icons are aligned optically: `-me-1` cancels the icon button's
own inset so the glyph, not the invisible hit area, lines up with the column
heading. Measured — header text and glyph now share the same right edge.

## 4. Bidirectional text

Arabic and English share a page, and the bidi algorithm reorders anything
neutral. Three real defects, all found by looking rather than reported:

1. **Every timestamp rendered reversed.** `2026-08-23 23:57:35` is digits and
   separators with no strong character, so in an RTL paragraph the space between
   the two runs takes the paragraph direction and the browser paints
   `23:57:35 2026-08-23`. Table cells, descriptions values and form fields now
   get `unicode-bidi: plaintext`, which resolves direction per element from its
   own content.
2. **`+218-91-0000000` rendered as `218-91-0000000+`**, and English paragraphs
   in textareas had their full stop moved to the front.
3. **Dates inside Arabic sentences.** `plaintext` cannot reach an interpolated
   value, so all 5 `{date}` placeholders in `ar.json` are wrapped in U+2066 /
   U+2069 — the same isolate technique `App\Support\Money::format` already used
   for amounts.

`plaintext` has a cost worth knowing: it also makes `text-align: start` resolve
against the *cell's* direction, so an English value in an Arabic table jumped to
the left while its Arabic neighbours stayed right — one column, two alignments.
Alignment is therefore restated physically per document direction immediately
below those rules. **Keep the pair together.**

Database content in an unknown language (a status comment, an actor name) is
marked `dir="auto"`; AntD stamps `direction: rtl` on its Typography under an RTL
`ConfigProvider` and the CSS property outranks the attribute, so the admin scope
re-asserts `plaintext` for those nodes.

## 5. Theme swap

Measured before changing anything: the swap injects **18–35 style tags, all in
one frame, ~200ms after the click**. It was never slow *loading*. Tailwind's
`.dark` class took effect immediately while AntD's tokens landed 200ms later, so
the page sat visibly half-light and half-dark for that window — the "block by
block" swap.

Two changes:

- `useAntdTheme` reads a shared `isDark` ref exported by `useAppearance` instead
  of running a `MutationObserver` on `documentElement`. The token swap is now
  scheduled in the same tick the class flips, not a DOM round-trip later.
  Sampling both systems every 25ms across the swap: **0 mixed samples**, where
  the two were previously ~200ms (≈8 samples) apart.
- The user-initiated change runs inside `document.startViewTransition`, which
  holds the old frame until the callback resolves and cross-fades once (260ms).

Two things to know before editing that callback, both learned the hard way:

- **Never await a frame inside it.** Rendering is paused for its duration, so
  `requestAnimationFrame` never fires and the transition dies with
  `TimeoutError: Transition was aborted because of timeout in DOM update`.
  `await nextTick()` is the right and sufficient wait.
- **Its promises reject when the transition is skipped**, which is what a second
  toggle mid-fade does. All three are caught and dropped deliberately; without
  that they surface as unhandled `AbortError: Transition was skipped`.

Browsers without the API, and anyone with `prefers-reduced-motion: reduce`, fall
through to a plain immediate swap.

## 6. Status pills

AntD's presets read badly at both ends of the theme. Dark `success` sat at
`rgb(22,35,18)` against a `rgb(20,20,20)` card — a chip with no edge — and the
light equivalent was a near-white wash with a pastel border.

Replaced across all seven families with one system: a tint carrying enough hue
to read as a chip, a border a step stronger, and label text dark-and-saturated
on light / bright on dark. Tags are 12px, which AA counts as small text, so the
bar is 4.5:1. Measured:

| | text on pill | pill vs card |
| --- | --- | --- |
| light | 4.85 – 5.88 | visible tint |
| dark | 6.90 – 8.21 | visible tint + 0.36α border |

Keyed off AntD's own `.ant-tag-*` classes, so `StatusBadge`'s slug → preset map
remains the single place a new status picks its colour.

## 7. Explaining things

New `HelpHint` component: a circled `?` revealing one or two sentences on hover
**and on focus**. It renders a real `<button>`, so the explanation is
keyboard-reachable and announced rather than hover-only trivia.

Three shared components take an optional `help` prop and place it: `Field`
(beside the label), `StatCard` (beside the figure's label) and `DataTable`'s
`Column` (beside the column heading). 11 keys under `admin.help.*` in both
locales, across 12 placements:

| Where | Explains |
| --- | --- |
| Stock table | Available, Reserved, Damaged, Incoming |
| Product form (×2) | Low-stock threshold |
| Sales report | Avg order value, Discounts given, Est. profit |
| Inventory report | Inventory cost value, Inventory retail value |
| Order → Payment | Outstanding |

**Every sentence was checked against the service that produces the number**
before it was written. This mattered: the first draft of the Available hint said
"total minus reserved", and `InventoryQueryService` keeps them as separate
columns. A confidently wrong tooltip is worse than none.

A circled `?` rather than `!` is deliberate — the dashboard already uses circled
red as its error language, so `!` would read as a warning on a healthy field.

Every icon-only button was audited for a missing tooltip. All were already
covered by `Tooltip` + `aria-label` except the mobile hamburger, which does not
need one.

## 8. Page-level fixes

| Page | Fix |
| --- | --- |
| Settings | Split into two balanced columns on `xl`. General + Payments are short, Static pages is six fields tall; pairing them beats a stranded 768px strip. |
| Order → items | Totals block aligned to the table's own figure column (it was floating 140px short), and inset to match the cell padding. |
| Order → timeline | `Pending →Cancelled` — Vue condensed the whitespace-only node between a closing `<template>` and the next binding. Now one interpolation. |
| Customer → profile | `'Yes'`/`'No'` were hardcoded English; colon style was inconsistent with the order page. |
| Customer → header | "Deactivate account" was a solid red primary — the loudest control on the page was the destructive one. Now outlined; "Activate" keeps the solid primary. |
| Products → trash | Soft-deleted rows still showed a green **Active** badge, indistinguishable from live ones. Now a red **Deleted** badge. |
| Return → details | Customer name and email rendered as `QA Customercustomer@store.test`. |
| Reviews | Empty state floated on the page background; the list is cards, so it is one too. |
| Specifications | `"1 uses"` → vue-i18n pluralisation, both locales. |
| Brands, Categories | Upload button was labelled with the field's own name ("Logo" under a "Logo" label). Now "⬆ Choose…". |
| Inventory report | Export link was stranded at the leading edge; anchored to the grid it acts on. |
| Sales report | Doughnut is a ring with dot legend keys; single-day series now draws a visible point instead of an empty chart. |
| Filter bar | `InputSearch`'s attached submit button was a dead control next to the 400ms debounce. Plain `Input` with a prefix icon. |

## 9. Bugs found while working, including my own

- **`.dark html:has(.admin-shell)` matches nothing.** `.dark` *is* the `<html>`
  element, so it cannot also be an ancestor of it. I introduced this in the
  portal-scoping change and 20 selectors silently fell back to their light
  values in dark mode before I caught it. Same trap applies to `[dir='rtl']`.
  Correct forms are `html.dark:has(…)` and `html[dir='rtl']:has(…)`. Written up
  in `admin-ui.md` because it fails silently and looks like a specificity
  problem.
- **Portalled content is outside `.admin-shell`.** AntD sends `Modal`, `Drawer`,
  `Select` dropdowns and `DatePicker` panels to `<body>`. A descendant selector
  rooted at `.admin-shell` misses all of them — which is how the form-field bidi
  fixes worked on the page but not inside any modal. Content rules are keyed off
  `html:has(.admin-shell)` instead.
- **Tooling note.** Full-viewport CDP screenshots in this session repeatedly
  returned stale frames — light pages captured as dark, layouts captured
  mid-scroll. `zoom`, and `getComputedStyle` read back through the page, were
  reliable. Several "defects" were capture artifacts; several real ones were
  only visible in measurements. Measure, don't squint.

## 10. Files changed

30 files. `HelpHint.vue` is new; everything else is a modification.

```
resources/css/app.css                            resources/js/pages/admin/
resources/js/layouts/AdminLayout.vue               Dashboard.vue
resources/js/composables/                          audit-logs/Index.vue
  useAppearance.ts                                 brands/Index.vue
  useAntdTheme.ts                                  categories/Index.vue
resources/js/components/admin/                     customers/Show.vue
  HelpHint.vue            (new)                    inventory/Index.vue
  DataTable.vue                                    orders/Show.vue
  Field.vue                                        products/Form.vue
  FilterBar.vue                                    products/Index.vue
  StatCard.vue                                     purchase-orders/Show.vue
  StatusBadge.vue                                  reports/Inventory.vue
resources/js/i18n/en.json                          reports/Invoices.vue
resources/js/i18n/ar.json                          reports/Sales.vue
docs/agents/admin-ui.md                            returns/Show.vue
docs/admin-design-polish.md   (new, this file)     reviews/Index.vue
                                                   settings/Index.vue
                                                   specifications/Index.vue
```

New i18n keys: `admin.common.yes`, `admin.common.no`, `admin.common.deleted`,
and 11 under `admin.help.*`. `admin.specifications.uses_n` became a plural form.

## 11. Not done, and why

- **Dashboard stat grid still tiles 4 / 4 / 2.** Ten cards do not divide into
  AntD's 24-column grid at any width that also reads well. Fixing it means
  replacing `Row`/`Col` with a CSS grid across ten call sites — a large diff for
  a small gain.
- **Storefront theme toggle is untested here.** It shares `updateAppearance`, so
  it inherits the cross-fade, but the toggle is not on the home header and the
  storefront was out of scope.
- **`vue-tsc` still fails** on the pre-existing `tsconfig` `types` entry. Not
  touched — it is unrelated to the UI and CI does not gate on it.
- **Wide tables still scroll horizontally** inside their card (`scroll.x` is
  `max-content`). Correct behaviour, but on the purchase-order detail page it
  triggers for only ~24px of overflow. Left as is.

## 12. State

Nothing is committed. The branch also carries the earlier AntD migration
uncommitted, so `git diff` against `main` is much larger than this pass.
`CLAUDE.md` and `docs/` are untracked but not gitignored.
