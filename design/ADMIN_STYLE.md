# Admin style — design system

Source of truth for how the admin dashboard looks. Extracted from the reference
`dashboard_3.html` (a dark re-skin of this same dashboard) on 2026-10-06, then
extended to the parts of the admin the reference does not show.

**Where the tokens live in code:** `resources/js/theme/adminTokens.ts`. It is the
only file that holds raw colour/size values. From there:

- `AdminLayout.vue` / `PosLayout.vue` call `useAdminTheme()`, which
  1. feeds the AntD `ConfigProvider` its token map (so every AntD control picks up
     the palette, radius and font), and
  2. writes every token onto `<html>` as a CSS custom property (`--ad-*`). They stay
     there after leaving the admin; only admin CSS reads `--ad-*`, so the storefront
     is unaffected (and nothing has to count mounts across the dashboard ⇄ register
     layout swap).
- `resources/css/app.css` ("Admin theme" block) and Tailwind (`ad-*` colours in
  `tailwind.config.js`, all `var(--ad-*)`) only ever reference the variables.

Why TS and not a CSS file as the source: AntD v4 computes hover/active shades from
real hex values at runtime, so it cannot consume `var(--x)`. Keeping the hex values
in TS and pushing them *out* to CSS is the only arrangement with one copy.

Light mode is not in the reference (it only has a toggle button). It is designed
here to mirror the dark palette step-for-step, marked **(designed)**.

---

## 1. Colour palette

| Token (`--ad-*`) | Dark (reference) | Light (designed) | Used for |
| --- | --- | --- | --- |
| `bg` | `#000000` | `#f5f5f5` | Page ground behind content |
| `side` | `#0a0a0a` | `#fafafa` | Sidebar |
| `card` | `#141414` | `#ffffff` | Cards, tables, KPI boxes, inputs |
| `head` | `#1a1a1a` | `#f7f7f7` | Table header row, elevated popovers/modals |
| `hover` | `#171717` | `#f0f0f0` | Nav item hover, ghost button hover |
| `row-hover` | `#181818` | `#fafafa` | Table row hover |
| `line` | `#262626` | `#e5e5e5` | Card borders, table rules |
| `line-soft` | `#1f1f1f` | `#ededed` | Chrome dividers (sidebar, top bar), list separators |
| `line-strong` | `#333333` | `#d4d4d4` | Chart baseline, input hover border |
| `rail` | `#2a2a2a` | `#e5e5e5` | Sub-nav indent rail |
| `text` | `#f5f5f5` | `#0a0a0a` | Primary text, figures |
| `text-2` | `#e5e5e5` | `#262626` | Nav labels, top bar links |
| `text-3` | `#c4c4c4` | `#404040` | KPI / group labels |
| `muted` | `#a3a3a3` | `#525252` | Secondary text, subtitles, dates, meta |
| `label` | `#808080` | `#737373` | Form labels' hint text, axis text |
| `faint` | `#737373` | `#8a8a8a` | Zero values (deliberately de-emphasised) |
| `primary` | `#2563eb` | `#2563eb` | Primary buttons, avatar |
| `primary-hover` | `#1d4ed8` | `#1d4ed8` | Primary button hover |
| `accent` | `#3b82f6` | `#2563eb` | Links, active nav, focus ring, chart line |
| `accent-hover` | `#60a5fa` | `#1d4ed8` | Link hover |
| `active-bg` | `#111827` | `#eff6ff` | Active nav item background |
| `money` | `#10b981` | `#047857` | Positive money figures |
| `success` | `#10b981` | `#059669` | Success states, bar chart |
| `warning` | `#f59e0b` **(designed)** | `#b45309` | Warning figures/alerts |
| `danger` | `#ef4444` | `#dc2626` | Errors, out-of-stock, destructive |
| `icon-bg` / `icon` | `#1c1c1c` / `#d4d4d4` | `#f5f5f5` / `#404040` | KPI icon box |
| `icon-money-bg` / `icon-money` | `#0a2a1f` / `#34d399` | `#ecfdf5` / `#047857` | KPI icon box (money) |
| `badge-bg` / `badge` | `#3a1515` / `#fca5a5` | `#fee2e2` / `#b91c1c` | Count badge in nav |
| `chart-fill` | `#172554` @ 55% | `#dbeafe` @ 60% | Area under revenue line |

Contrast checks (WCAG, normal text needs 4.5:1): dark `muted` on `card` 7.6:1,
`faint` on `card` 3.9:1 (zero values only, never sole carrier of meaning), light
`muted` on white 7.8:1, light `money` on white 5.5:1, light `faint` 3.5:1 (same
caveat).

### Status pills

Reference shows two (Completed, Refunded); the rest are **designed** on the same
recipe — dark: 900-ish border, 950 fill, 400 label; light: 200 border, 50 fill, 700
label. Keyed off AntD's `.ant-tag-*` classes, so `StatusBadge`'s slug map is still
the only place a status picks its family.

| Family (AntD class) | Dark border / bg / text | Light border / bg / text | Statuses |
| --- | --- | --- | --- |
| success / green | `#166534` / `#052e16` / `#4ade80` | `#bbf7d0` / `#f0fdf4` / `#15803d` | completed, paid, active… |
| purple | `#6b21a8` / `#2e1065` / `#d8b4fe` | `#e9d5ff` / `#faf5ff` / `#7e22ce` | refunded, returned |
| processing / blue | `#1e40af` / `#172554` / `#93c5fd` | `#bfdbfe` / `#eff6ff` / `#1d4ed8` | processing, shipped… |
| geekblue | `#3730a3` / `#1e1b4b` / `#a5b4fc` | `#c7d2fe` / `#eef2ff` / `#4338ca` | ready_for_shipping |
| warning | `#92400e` / `#451a03` / `#fcd34d` | `#fde68a` / `#fffbeb` / `#b45309` | pending, unpaid… |
| error | `#991b1b` / `#450a0a` / `#fca5a5` | `#fecaca` / `#fef2f2` / `#b91c1c` | cancelled, failed… |
| default | `#404040` / `#1f1f1f` / `#d4d4d4` | `#e5e5e5` / `#f5f5f5` / `#404040` | draft, closed… |

## 2. Typography

Font: **IBM Plex Sans Arabic** (400/500/600/700), Latin + Arabic in one family,
loaded from Bunny Fonts (the project's existing font CDN) only while an admin
layout is mounted. Fallback `system-ui, sans-serif`. The storefront keeps Tajawal.
Figures use `font-variant-numeric: tabular-nums` wherever they line up in columns.

| Role | Size / weight / line-height | Colour |
| --- | --- | --- |
| Body | 14px / 400 / 1.5 | `text` |
| Page title (h1) | 24px / 700 | `text` |
| Page subtitle | 13.5px / 400 | `muted` |
| Card title | 14px / 600 | `text` |
| Group / KPI label | 14px / 500 | `text-3` |
| KPI value | 26px / 700 / 1.2, tabular | `text` (or `money` / `faint` for zero) |
| KPI currency suffix | 14px / 500 | `muted` |
| Stat cell value | 24px / 700 / 1.25, tabular | `text` |
| Stat cell label | 13.5px | `muted` |
| Table text | 13.5px; header 600 | `text`; dates `muted` |
| Nav item / sub-item | 14px / 13.5px | `text-2` / slightly muted |
| Brand | 15px / 600 | `text` |
| Pill | 12px / 500 | per family |
| Badge | 11.5px / 600 | `badge` |
| Meta (list secondary line) | 12px | `muted` |
| Chart axis | 11px, tabular | `muted` |

## 3. Spacing, radius, shadow

- **Spacing:** 4px base. Common steps: 2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 28.
  - Page padding 28px (16px under 640px). Gap between rows/cards 16px.
  - Card head padding 14px 20px. KPI box 16px 18px, icon gap 14px.
  - Table cells 14px 12px; first/last column 20px from the card edge; header 12px.
  - List rows 12px 20px; activity rows 11px 20px.
- **Radius:** `--ad-radius` 8px (buttons, inputs, nav items), `--ad-radius-lg` 10px
  (cards, tables, KPI icon box, modals), `--ad-radius-sm` 6px (pills, tags), badges
  fully rounded, avatar circle.
- **Shadow:** none on surfaces — the reference is flat and separates with 1px
  borders. Popovers/dropdowns/modals **(designed)** get `--ad-shadow-pop`:
  `0 8px 24px rgb(0 0 0 / .5)` dark, `0 8px 24px rgb(0 0 0 / .08)` light.
- **Focus:** `2px solid accent`, offset 2px, on `:focus-visible` only.

## 4. Layout

- **Shell:** sidebar + content column. Content column min-width 0.
- **Sidebar** (≥ 1024px: fixed, full height, own scroll; below: AntD Drawer, flips
  side with locale): 256px, `side` background, `line-soft` end border, 10px inner
  padding.
  - Brand row: 64px tall, icon in `accent`, store name 15/600, bottom border.
  - Sections separated by a 1px `line-soft` rule (10px 6px margin).
  - **Nav item:** 40px min-height, 0 12px padding, 12px icon gap, radius 8.
    Hover `hover` bg + `text`. Active: `active-bg`, `accent` text, weight 500, plus
    a 3px `accent` bar on the inline-start edge (inset 9px top/bottom).
  - **Group button** (collapsible): same box as a nav item, chevron at the end that
    rotates 180° when open. Open/closed state persists in `localStorage`
    (`adminNavGroups`); the group holding the current page is always open.
  - **Sub-items:** 36px, 13.5px, indented 21px with a 1px `rail` on the
    inline-start side.
  - **Bottom block** (Platform + Settings) pinned to the bottom with a top border.
- **Top bar:** sticky, 64px min-height, padding 12px 28px (16px on phones),
  `bg` at 90% opacity + 6px backdrop blur, bottom border `line-soft`. Order:
  menu button (mobile) · **Point of sale** primary button · page slot · spacer ·
  Storefront link · language switch · theme toggle · avatar + name (dropdown).
- **Content:** padding 28px, vertical rhythm 16px. Max content width unbounded
  (like the reference).
- **Grids:** flex-wrap rows with 16px gap. KPI boxes `flex: 1 1 220px`; wide card
  `flex: 2 1 456px`; narrow `flex: 1 1 220px`; charts `flex: 1 1 420px`; orders
  table `flex: 2 1 520px` beside a `flex: 1 1 300px` side column. Rows wrap to
  single column naturally on narrow screens.

## 5. Components

| Component | Spec |
| --- | --- |
| **Card** (AntD `Card`) | `card` bg, 1px `line` border, radius 10, no shadow. Head: 14px/600, padding 14px 20px, bottom border `line`; `extra` links 13px `accent`. |
| **KPI box** (`StatCard`) | Card with optional 44×44 icon box (radius 10, `icon-bg`/`icon`, money variant green), label 14/500 `text-3`, value 26/700 tabular. Tone: success → `money`, warning → `warning`, danger → `danger`, zero → `faint`. Hint text 12px `muted`. |
| **Stat group** **(new, from reference)** | Card with a 14/500 title and N cells in a row separated by `line` rules; cell = 13.5px label + 24/700 value, whole cell is a link with `row-hover` hover. |
| **Buttons** | Height 32 (AntD default) for page actions, 40 for the top-bar POS button. Radius 8. Primary: `primary` bg, white, 600; hover `primary-hover`. Default: `card` bg, `line` border, `text`; hover border `line-strong`. Text/ghost: transparent, `text-2`, hover `hover` bg. Danger keeps AntD danger red from `danger`. |
| **Inputs / selects / date pickers / number inputs / textareas** | `card` bg, 1px `line` border, radius 8, 14px text; hover border `line-strong`; focus border `accent` + 2px `accent` @ 25% ring. Placeholder `label`. (Designed — reference has no inputs.) |
| **Table** | Card edge (1px `line`, radius 10, overflow hidden). Header row `head` bg, 600, 12px padding, bottom `line`. Cells 13.5px, 14px 12px padding, horizontal `line` rules only, 20px inset at the card edges. Row hover `row-hover`. Numbers/money: `text-end` + tabular-nums + nowrap. Dates `muted`, nowrap. |
| **Status pill** (`StatusBadge` / `Tag`) | 12px/500, padding 1px 9px, radius 6, 1px border. Colours: table in §1. |
| **Badge** | 20px tall, min-width 20, 0 6px padding, radius 10, 11.5/600, `badge-bg`/`badge`. |
| **Modal** **(designed)** | `head` bg (one step above the card it opens over), 1px `line` border, radius 10, `shadow-pop`. Title 16/600. Mask `rgb(0 0 0 / .6)`. |
| **Dropdown / popover / select panel** **(designed)** | `head` bg, 1px `line` border, radius 8, `shadow-pop`; items 36px, hover `hover`. |
| **Tabs / segmented** **(designed)** | Ink bar + active label `accent`, inactive `muted`, divider `line`. |
| **Pagination** **(designed)** | 32px items, radius 8, active item: `accent` border + text on `card`; total text `muted` 13px. |
| **Empty state** **(designed)** | Centred in the table/card body, 40px block padding, icon `faint`, text `muted` 13.5px. |
| **Alert** **(designed)** | Radius 10, tinted with the matching pill family (border/bg/text), 14px. |
| **Descriptions** | Label `muted`, value `text`; bordered variant uses `line` and `head` label cells. |
| **Timeline / list** | Rows separated by `line-soft`; secondary line 12px `muted`. |
| **Help hint** | Circled `?`, `label` colour, hover `hover` bg + `text`. Unchanged behaviour. |
| **Charts** (Chart.js) | Line: `accent` stroke 2px, `chart-fill` area, no points except hover. Bars: `success`, radius 4 on top. Grid `line-soft`, axis text 11px `muted`. Colours read from the tokens at render time. |

### Layout rules added during the restyle

- The content column is a flex column with a 16px gap; page-level `mt-*`/`mb-*`
  on its direct children are neutralised so the gap owns the rhythm.
- Grids that only declare breakpoint templates (`grid xl:grid-cols-3`) get a
  `minmax(0, 1fr)` base column, so a wide table cannot push a phone screen sideways.
- Card heads wrap (title above actions) on narrow screens.

## 6. Light / dark

The reference is dark-only with a toggle; both modes ship. `useAppearance` remains
the only writer of the `.dark` class; `useAdminTheme` follows its `isDark` ref and
swaps both the AntD algorithm/tokens and the `--ad-*` variables in the same tick.

## 7. Not matched / deliberately different

- **Nav count badges** (e.g. "1 out of stock" on Inventory): the layout has no such
  number today; showing it needs a new shared Inertia prop from the middleware
  (high-risk zone + a data change). Badge style is defined above and ready; not wired.
- **Print documents** (invoice, delivery slip, PO print, receipt) are paper, not
  screens: they stay black-on-white. Only their on-screen toolbar picks up tokens.
- **POS register** keeps its full-screen layout (no sidebar, by design) but takes
  the same tokens, font and top-bar styling.
- **Profile / password pages** linked from the avatar menu are shared with
  customers (`layouts/settings`), so they are out of scope.
- Sidebar is 256px (reference 250px) so the existing `lg:ms-64` content offset holds.
- **Currency placement** (matched, 2026-10-06 follow-up): KPI figures show the amount
  large and the currency small and muted after it (`StatCard` splits the
  `App\Support\Money` string for display; the value is never re-formatted). Across
  the whole admin, `useAdminMoneyBidi` closes Money's LTR isolate after the amount, so
  in Arabic "د.ل" sits on the left of the figure as in the reference; English is
  unchanged. `Money` itself, the storefront and the printed receipt are untouched.
- **Stat-group cells are not links** (the reference's are). Linking them would add
  navigation that does not exist today; left as plain figures.
- The amount never breaks; a long one pushes its currency to the next line.
- **Dashboard grid** follows the content column's own width (container queries), so at
  ~700px (1024px window minus the sidebar) the KPI row stays four across in a compact
  card form (small icon beside the label, figure on its own row) instead of 2×2 boxes
  that were half empty; status counts take a full row with inventory/customers split
  2:1 under it; charts sit side by side from 640px.

---

## 8. Admin page map

Shared chrome on every `AdminLayout` page: sidebar, top bar, flash messages.
"DT" = `DataTable`, "FB" = `FilterBar`, "PG" = `Pagination`, "PH" = `PageHeader`.

| # | Page (route) | File | Components used |
| --- | --- | --- | --- |
| 1 | Dashboard (`admin.dashboard`) | `Dashboard.vue` | PH, Alert, StatCard, Card, Line + Bar charts, Table, StatusBadge, List |
| 2 | Products (`admin.products.index`) | `products/Index.vue` | PH, custom table + Checkbox (bulk), StatusBadge, FormModal, Tooltip, Button, PG |
| 3 | Product form (`admin.products.create/edit`) | `products/Form.vue` | PH, Card, Field, Input, InputNumber, Textarea, AdminSelect, AdminDateInput, Checkbox, Tag, Alert, Button |
| 4 | Categories | `categories/Index.vue` | PH, FB, DT, StatusBadge, FormModal, Field, Input, InputNumber, Textarea, Checkbox, Upload, PG |
| 5 | Brands | `brands/Index.vue` | PH, FB, DT, StatusBadge, FormModal, Field, Input, InputNumber, Textarea, Checkbox, PG |
| 6 | Specifications | `specifications/Index.vue` | PH, Card, Field, Input, InputNumber, Checkbox, Tag, Empty, Button |
| 7 | Stock | `inventory/Index.vue` | PH, DT, AdminSelect, FormModal, Field, Input, InputNumber, Checkbox, PG |
| 8 | Stock movements | `inventory/Movements.vue` | PH, DT, StatusBadge, PG |
| 9 | Warehouses | `warehouses/Index.vue` | PH, DT, StatusBadge, Tag, FormModal, Field, Input, Textarea, Checkbox |
| 10 | Suppliers | `suppliers/Index.vue` | PH, DT, StatusBadge, FormModal, Field, Input, Textarea, PG |
| 11 | Purchase orders | `purchase-orders/Index.vue` | PH, DT, StatusBadge, PG |
| 12 | Purchase order form | `purchase-orders/Form.vue` | PH, Card, Field, InputNumber, Textarea, AdminDateInput, Button (line editor) |
| 13 | Purchase order detail | `purchase-orders/Show.vue` | Card, Descriptions, StatusBadge, FormModal (receive), InputNumber |
| 14 | Purchase order print | `purchase-orders/Print.vue` | Paper document (no layout) |
| 15 | POS register | `pos/Register.vue` | PosLayout, product grid, basket, Input, InputNumber, RadioGroup, Modal, Empty, ReceiptPaper |
| 16 | POS open shift | `pos/OpenShift.vue` | PosLayout, Card, Field, InputNumber, RadioGroup, Textarea |
| 17 | POS close shift | `pos/CloseShift.vue` | PosLayout, Card, Descriptions, Alert, Field, InputNumber, Textarea |
| 18 | POS receipt | `pos/Receipt.vue` | Paper (ReceiptPaper) + toolbar |
| 19 | Cashier shifts | `pos/Shifts.vue` | PH, DT, StatusBadge, PG |
| 20 | Orders | `orders/Index.vue` | PH, DT, StatusBadge, PG |
| 21 | New order | `orders/Create.vue` | PH, Card, Field, Input, InputNumber, Textarea, AdminSelect |
| 22 | Order detail | `orders/Show.vue` | PH, Card, Descriptions, DT, StatusBadge, Timeline, Field, AdminSelect, Input, InputNumber, Textarea, Checkbox, HelpHint |
| 23 | Invoice | `orders/Invoice.vue` | Paper document |
| 24 | Delivery slip | `orders/DeliverySlip.vue` | Paper document |
| 25 | Refunds | `refunds/Index.vue` | PH, FB, DT, PG |
| 26 | Treasury | `treasury/Index.vue` | PH, StatCard, Card, DT, Field, Input, InputNumber, PG |
| 27 | Customers | `customers/Index.vue` | PH, DT, Tooltip, PG |
| 28 | Customer detail | `customers/Show.vue` | PH, Card, Descriptions, List, StatusBadge, Tag |
| 29 | Reviews | `reviews/Index.vue` | PH, Card list, StatusBadge, Tag, Empty, PG |
| 30 | Coupons | `coupons/Index.vue` | PH, FB, DT, FormModal, Field, Input, InputNumber, AdminSelect, AdminDateInput, Checkbox, PG |
| 31 | Sales report | `reports/Sales.vue` | PH, Field, AdminDateInput, StatCard, Card, Line + Doughnut charts |
| 32 | Inventory report | `reports/Inventory.vue` | PH, StatCard, Card, tables |
| 33 | Invoices report | `reports/Invoices.vue` | PH, Field, AdminDateInput, StatCard, Card, Table, StatusBadge |
| 34 | Users | `users/Index.vue` | PH, FB, DT, StatusBadge, Tag, FormModal, Field, Input, Checkbox, PG |
| 35 | Roles & permissions | `roles/Index.vue` | PH, Card, Tag, FormModal, Field, Input, Checkbox |
| 36 | Audit logs | `audit-logs/Index.vue` | PH, Table, filters, PG |
| 37 | Settings | `settings/Index.vue` | PH, Card, Field, Input, Textarea, Upload |

Layouts: `AdminLayout.vue` (pages 1–13, 19–37), `PosLayout.vue` (15–17), none (print
pages 14, 18, 23, 24).
