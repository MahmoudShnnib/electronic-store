# State of play

Written 2026-08-26, updated 2026-09-01. Read this first in a new session;
it is the handoff note.

## Where the work is

Branch **`design-tweeks`**, last commit `95a4a79`. **Nothing since then is committed** —
the working tree now carries four full passes of work:

1. The admin dashboard's Ant Design polish pass.
2. The storefront's Ant Design migration, plus a follow-up round of fixes.
3. The 2026-08-27 landing-page redesign toward gamingcenter.ly (see the section below).
4. The 2026-09-01 Web Interface Guidelines pass (see the section below).

If the user asks you to commit, expect a large diff and split it sensibly — the deletions
are a natural separate commit from the storefront rewrite, and the 08-27 redesign (which
includes a migration + new backend pieces) is a third.

## The 2026-08-27 redesign (gamingcenter.ly direction)

The user's brief, over several increasingly emphatic rounds: clone gamingcenter.ly's
snake-border CTA and footer exactly, add their header categories button and hero layout,
curate the landing page, and kill the side gaps. `agents/storefront-ui.md` has the full
map; the short version of what changed:

- **Backend:** `HomePageService` gained public `categoryCards()` and a cached
  `categorySections()` (top-2 categories, min 4 products); `home.category_sections`
  added to `HomeCache`; `Inertia::share('navCategories', …)` in **AppServiceProvider**
  (deliberately not the middleware — confirm-first zone); a real newsletter backend
  (migration `newsletter_subscribers`, `NewsletterController`, `newsletter.subscribe`
  route with the auth throttle, Arabic string in `lang/ar.json`).
- **Frontend:** hero = framed carousel + two data-driven promo tiles; white category
  cards; trust strip; sections capped at 6 with title icons; category spotlight rows;
  promo-tile marquee deleted; `SnakeButton.vue` + `StoreFooter.vue` are literal clones
  of the reference site's markup/CSS (their red remapped to green); "All categories"
  header dropdown; container widened 90rem → 100rem **paired with** catalogue
  `2xl:grid-cols-5`; CatchGame got a spring/squash/shake juice pass.
- **Third global AntD ⇄ Tailwind conflict** found and fixed:
  `.ant-btn > span { display:inline-block }` beats `.flex` — see `agents/admin-ui.md`.
- **Dev-data rows exist:** two test emails in `newsletter_subscribers`
  (`test-subscriber@` / `gcfooter-test@example.com`). Harmless; delete before demoing.

Deliberately declined, with the user told why: an LYD/USD currency switch (products only
store LYD — it is a dual-pricing feature, not styling) and a blog/posts section (no blog
backend). Social links in the footer are `#` placeholders awaiting real profile URLs.

## The 2026-09-01 guidelines pass

The working tree now carries a fourth pass: an audit of every `.vue` file
against Vercel's Web Interface Guidelines, and fixes for everything it found.
It touches ~25 files across both shells plus `app.css`, `app.blade.php` and the
i18n bundles (five new keys). `agents/storefront-ui.md` and `agents/admin-ui.md`
each end with a section listing what changed and which parts are easy to undo by
accident. Two new files: `composables/useMediaQuery.ts` and `lib/money.ts`.

Findings from that pass that are **not** fixed and are worth knowing:

- **AntD `Popconfirm` does not work in the storefront.** Its trigger fires
  `open-change` and no popover ever mounts — verified in the browser, controlled
  and uncontrolled. `Cart.vue` now confirms with a declarative `<Modal>`
  instead. `account/Addresses.vue`, `account/Orders.vue` and
  `account/OrderDetail.vue` still use `Popconfirm` for their delete
  confirmations, so those buttons are probably dead. Not investigated further.
- **The storefront header can get stuck hidden.** After scrolling down and back
  to the top by script, `useHideOnScroll` left `-translate-y-full` on the header
  at `scrollY === 0`. Reproduced once, not chased.
- Browser QA covered the storefront in light mode, LTR, at 1920px and 420px.
  **RTL and the dashboard were not clicked through** — the language toggle would
  not fire through the automation harness, and the admin needs a login.

## What is true now

- **Both halves of the app are Ant Design Vue over Tailwind.** There is no second component
  library. The shadcn/radix tree was deleted.
- Admin theme: blue (`#2563eb`), radius 6. Storefront theme: green (`#0F9D58`), radius 10 —
  see `useAntdTheme.ts` and `useStoreTheme.ts`.
- Every shell root carries a marker class (`.admin-shell` / `.store-shell`) and a
  `ConfigProvider`. Both pin the root font to 16px.
- Storefront colours are custom properties on `.store-shell` (`--st-*`). Nothing in the
  storefront should hardcode a light- or dark-only colour.
- Content-level CSS (table interiors, card edges, overlay scrollbars, status pills, the
  whole bidi block) is scoped `html:has(.admin-shell, .store-shell)` — **shared by both
  shells**. Change it once, check both sides.

## Counter receipt printing (2026-09-10)

The register no longer needs the browser print dialog. `Receipt.vue` rasterises its own
`.receipt` node with `html2canvas-pro` and posts the PNG to `admin.pos.receipt.print`;
`ReceiptPrinter` wraps it in ESC/POS and pushes it down one of three transports
(`network` / `device` / `command`, chosen by `POS_PRINTER_DRIVER`). Cut and cash-drawer
kick included; the drawer only fires on cash, read from the order rather than the request.

**Leaving `POS_PRINTER_DRIVER` empty is supported** — the page falls back to
`window.print()`, which is still a correct slip. Nothing about this can fail a sale.

Read `agents/pos-printing.md` before touching it. The one thing worth knowing up front:
a bitmap is used instead of ESC/POS text runs *because no printer code page renders joined
Arabic, Latin product names and `Money::format()`'s LTR isolate on one line*. Do not
"simplify" it into text commands.

**The hardware, as wired on 2026-09-10:** an Xprinter POS-80 (fw 3.027PR6M), **USB only**,
576-dot head, running on `driver=cups` through queue `XP80` built on the vendor PPD
(`XP-80.ppd`, from Xprinter's macOS driver package). A full receipt prints in ~3s.

**Why the vendor driver rather than our own ESC/POS:** the controller overruns on a real
receipt. Text and a 13KB raster print clean; an 83KB one produces a foot of GB18030
gibberish. Pacing it ourselves works but costs a CUPS job per chunk — 88 seconds a slip.
The vendor filter streams inside one job and exposes cut, drawer and density as job
options, so nothing was given up. The hand-rolled ESC/POS paths are still there and still
correct for `network` / `device` / `command`. **Do not "simplify" the cups path back into
raw ESC/POS over USB** — that is the bug, not a cleanup.

**The till does not change screen.** Taking payment prints from the register and confirms
in a modal; it no longer routes to the receipt page. The slip markup lives in
`components/pos/ReceiptPaper.vue` and the rasterise-and-post in
`composables/usePosPrinting.ts`, shared by the register and the reprint page — one copy,
so the paper cannot drift between them.

**Configured, and not through `.env`.** `.env` is permission-blocked from Claude, and a
printer bolted to a desk is the wrong thing to need a redeploy anyway — so printer wiring
resolves through `PrinterConfig`: `.env` first, then a `pos_printer` row in the `settings`
table written by `php artisan pos:printer`. **Never read `config('pos.printer.*')`
directly** or stored settings are silently ignored.

**Not built:** a local bridge (QZ Tray). If the app ever moves to a remote VPS while the
printer stays USB-attached at the shop, none of the three drivers can reach it — the LAN
variant plus the `network` driver is the answer there.

## The register's stock is live (2026-09-12)

`Register.vue` no longer trusts the catalogue it was rendered with. Two things changed:

- **A tile shows what is left to sell, not what the warehouse holds.** `available` is the
  shelf figure minus whatever the basket already holds, and a tile with nothing left is
  **filtered out of the grid** rather than dimmed. Two in stock means two taps, then the
  tile is gone. Emptying the basket brings it back.
- **`usePoll` re-reads `products` / `categories` / `shift` / `recentSales` every ten
  seconds**, so another till's sale or a delivery booked in the back office shows up
  without a reload. The partial reload keeps the basket, the search box and the scroll.

Two things there are load-bearing and easy to undo by accident:

- The poll is **stopped while a sale is in flight** (`midSale`), *and* guarded with
  `onBefore`. An Inertia visit started while the checkout POST is running cancels it, and
  the poll restarts itself when the tab returns to the foreground, which is the case
  stop() alone does not cover.
- Basket lines hold **no frozen `available`**. They read `stockByKey` live, and a poll that
  lands under the basket trims the line — or removes it — with a warning, so the cashier
  cannot reach the confirmation dialog holding a quantity the server will refuse.

Verified in the browser against the real register: 2 → tap → 1 → tap → gone; stock dropped
to 0 from outside removed the line with its toast inside one poll cycle; restocking put the
tile back at "9 left" with no reload.

## Verify commands

Run these before claiming anything is done. All four were green at the time of writing.

```
npm run lint                  # eslint --fix
npm run build                 # vite build
./vendor/bin/pint --dirty     # PHP formatting
./vendor/bin/pest             # 198 passed (888 assertions)
```

Visual QA is worth doing in all four combinations: light/dark × LTR/RTL. Both toggles are in
the storefront header and the admin top bar.

## Known issues — do not re-investigate

- **`vue-tsc --noEmit` fails.** Pre-existing `tsconfig` problem: `types` contains
  `./resources/js/types`, which is a directory, not a type package. Unrelated to any UI
  work. Don't chase it.
- **The dev server runs on port 5174**, not 5173 — 5173 was occupied. `public/hot` points at
  `http://[::1]:5174`.
- **Headless Chromium screenshots lie.** `--window-size` includes browser chrome and shots
  are often taken mid-layout, which produced a convincing but false "mobile hero text is
  clipped" report. Measure with `document.documentElement.scrollWidth` vs `clientWidth` in a
  real browser before believing a screenshot.
- **CDP `zoom` captures time out intermittently.** `getComputedStyle` and DOM measurement
  through the JS tool have been reliable; full-viewport screenshots occasionally return a
  stale frame. Take a second shot before concluding anything.

## Deliberately not done

- **`@vueuse/core`, `@vueuse/integrations`, `@tailwindcss/forms`** are installed but
  unreferenced. Safe to remove; it was not done in the same pass as 138 file deletions.
- **`app/Http/Middleware/` was never touched.** Sharing the customer's wishlist ids as a
  global Inertia prop would be tidier than resolving it per resource, but that directory is
  a confirm-first zone in `CLAUDE.md`. Ask before changing it.
- **`MobileBottomNav` is bespoke**, not an AntD component — AntD has no floating pill nav.
  It takes the accent so it agrees with everything else. Leave it that way unless asked.

## Things that were fixed, so don't "fix" them again

- The root font is pinned to 16px inside both shells **on purpose**. Unpinning it
  reintroduces a 25% rem/px mismatch — that is what made `max-w-7xl` measure 1600px on a
  1536px viewport and let the promo rail bleed off the page.
- **The storefront renders `max-w-7xl` at 100rem** (`html:has(.store-shell) .max-w-7xl`),
  paired with the homepage grid's `:xl="4"` (6-up) and the catalogue grid's
  `2xl:grid-cols-5`. Width and density are a set. It was briefly 96rem with a 4-up grid,
  which stretched product cards to 367x540px — wider container, same column count,
  everything too big. Change one, check the others. Dashboard stays 1280.
- **Dark surfaces are spaced by L\*, not contrast ratio** — ground L* 3, band L* 9, card
  L* 15, roughly five points apart. An earlier pass used a step that measured 1.045:1 and
  was invisible on screen. Ratio compresses near black; measure L*.
- **The brand rail is deliberately full-bleed.** The category promo-tile marquee that used
  to sit beside it was deleted in the 08-27 redesign as duplicate clutter — don't
  resurrect it.
- A hairline grid on the hero slab was tried and **removed** — it read as graph paper.
  The slab's depth treatment is now the accent glow plus the `.store-slab-deco` shapes
  (ring + blob); the footer's cyber grid is scoped to `.gc-footer-v2` on purpose.
- `bordered` is deliberately unused on admin tables; the interior treatment is in `app.css`.
- The wishlist heart is **always visible** by design (outline unsaved, filled red saved).
  Hover-gating it is what made it look like it disappeared on click, and it meant the
  control did not exist at all on touch.
- The storefront search is a plain `Input` with a prefix icon, not `Input.Search` — the
  attached button is a dead control beside debounced suggestions.
- `once()` in `ProductCardResource` is what keeps the wishlist lookup to one query per
  request across a 24-card grid. It also has to stay outside the homepage's section cache,
  which holds models rather than rendered resources.

## Where to look next

`docs/README.md` is the index. The two architecture maps are `agents/admin-ui.md` and
`agents/storefront-ui.md`; the change records explaining *why* are one level up in `docs/`.
