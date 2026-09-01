# Storefront UI

The storefront (`/`, everything under `resources/js/pages/storefront/`, plus the auth and
settings screens) is built on **Ant Design Vue 4**, same as the dashboard. Icons are
`lucide-vue-next`. There is no second component library any more — the shadcn/radix
starter-kit tree was deleted when this migration landed.

Read `admin-ui.md` first if you have not: the AntD ⇄ Tailwind conflicts (there are three
fixed globally now, including `.ant-btn > span.flex` losing its flex layout), the
`html:has()` scoping rule and the `html`-rooted selector trap are documented there and
apply here unchanged. This file covers what is specific to the shop.

## Where the design lives

| What you want to change | File |
| --- | --- |
| Accent colour, corner radius | `resources/js/composables/useStoreTheme.ts` |
| Header, drawer, tab bar, "All categories" menu | `resources/js/layouts/StoreLayout.vue` |
| Footer (the gc-footer-v2 clone) | `resources/js/components/store/StoreFooter.vue` |
| The snake-border CTA | `resources/js/components/store/SnakeButton.vue` |
| The homepage mini-game | `resources/js/components/store/CatchGame.vue` |
| Anything shared across shop pages | `resources/js/components/store/*` |
| Surfaces, chrome, product card, filters, snake button CSS, footer CSS | the "Storefront chrome" and "Storefront components" blocks at the bottom of `resources/css/app.css` |
| Sign-in / register / reset screens | `resources/js/layouts/auth/AuthSimpleLayout.vue` + `pages/auth/*` |
| Account settings | `resources/js/layouts/settings/Layout.vue` + `pages/settings/*` |

## The reference site

The user's stated design direction is **gamingcenter.ly, remapped to this shop's green**.
Two pieces are *literal clones* of that site, not approximations:

- **`SnakeButton.vue` + the `.gc-snake-btn` CSS block** — their markup (four
  `gc-snake-btn-border` spans + a text span) and their CSS fetched verbatim from the live
  page, with `#ec1d24/#ff3439` mapped to `#0f9d58/#4ade9b`. Full-length gradient bars sweep
  each edge via `transform` with staggered delays, each bar carries a `blur(12px)` glow
  copy, hover lifts the button, doubles the snake speed, expands a radial burst and fades
  in a tech-grid overlay. One deviation: their recipe assumes a dark page, so light mode
  overrides the hover to a solid green fill with a white label
  (`html:not(.dark) .gc-snake-btn:hover`). Used by the hero "Shop now" and the sale
  banner "View all".
- **`StoreFooter.vue` + the `.gc-footer-v2` CSS block** — their footer structure and class
  names end to end: `#gc-particles` canvas (a real JS particle field, ~70 green embers,
  skipped under reduced motion), `gc-scanlines`, `gc-grid-overlay`, pulsing logo glow,
  four social links (their exact Facebook/Instagram/YouTube/TikTok SVG paths; hrefs are
  `#` placeholders in one array until real profiles exist), mobile accordion columns,
  a working newsletter form, payment badges, and the line–dot–line decor bar. **The footer
  is deliberately dark in both themes** — it is the closing statement and the neon reads
  only on darkness — so it owns its own palette instead of the `--st-*` tokens. This is a
  knowing exception to the "no hardcoded dark-only colours" rule below, made at the user's
  explicit request.

## Theme

`useStoreTheme()` wraps `useAntdTheme()` with the shop's tokens and is the single source
for both `StoreLayout` and `AuthSimpleLayout`:

- `colorPrimary: '#0F9D58'` — green was already the site's commerce colour, so it is the
  seed. The dashboard stays blue on purpose; they are different products to the person
  using them.
- `borderRadius: 10` — the dashboard is 6 and businesslike, the shop is softer.

**Every page needs a `ConfigProvider` above it.** `StoreLayout` has one (theme + AntD
locale + `direction`), so any page using that layout is covered. The auth screens sit
outside it and carry their own. If you add a third entry point, give it one too, and put
`.store-shell` on its root element.

## Palette

Both themes are declared as custom properties on `.store-shell` (`--st-ground`,
`--st-surface`, `--st-ink`, `--st-line`, `--st-slab`, …), so a surface is described once
and the two modes stay in step.

- **Nothing in the storefront may hardcode a light- or dark-only colour** — with two
  deliberate exceptions: the footer (see above) and the category cards' white tiles
  (white in both themes is what makes the row pop on a dark page, same as the reference
  site).
- **Light mode is not pure white.** The ground is a warm off-white with white cards on
  top. `--st-slab` is a tinted bright panel in light and a near-black one in dark.

The accent green is the only saturated colour on the page.

## Homepage anatomy (top to bottom)

All in `pages/storefront/Home.vue` unless noted. The order copies the reference site.

1. **Hero** — a framed, rounded carousel card (2/3 width, `.store-slab .store-slab-deco`)
   with two promo tiles beside it. The tiles are data-driven stand-ins for the reference
   site's ad banners: the category with the best live discount, then the biggest category.
2. **Category cards** (`.store-category-card`) — white tiles holding the category photo
   (icon fallback), name and count/discount beneath. A grid, not a rail.
3. **Trust strip** (`.store-trust-item`) — COD / fast delivery / genuine / easy returns.
4. **Product sections** — Top deals, New arrivals, Best sellers; each capped at
   `SECTION_CAP = 6` (one full 6-up row) with icons on the titles.
5. **Category spotlights** — the two biggest root categories get their own product row.
   Server-side: `HomePageService::categorySections()`, cached as
   `home.category_sections` (flushed by `HomeCache`). Sections with fewer than 4
   products are dropped. Nested resource collections serialize **wrapped** — the frontend
   reads `section.products.data`.
6. **Sale banner** — second `.store-slab-deco` slab.
7. **Brand marquee** — the only remaining marquee. The category promo-tile marquee was
   **removed on purpose**: it duplicated the category grid and the user wanted less
   clutter. Don't bring it back.
8. **Cart Challenge** (`CatchGame.vue`) — see below.
9. **Footer** (in `StoreLayout`, rendered by `StoreFooter.vue`).

## Header

- **"All categories" pill** (`.store-cats-btn`) — the loudest header control, cloned from
  the reference site's كل الأقسام button. A `Dropdown` listing every root category with
  its product count. Data comes from `Inertia::share('navCategories', …)` in
  `AppServiceProvider` (NOT the middleware — that directory is confirm-first), backed by
  the same 10-minute `home.category_cards` cache the homepage uses.
- Desktop only; the drawer covers mobile.

## Newsletter (real backend)

The footer form posts to `newsletter.subscribe` → `Web\NewsletterController` →
`newsletter_subscribers` table (email unique + locale). Throttled with the `auth`
limiter. Success returns a normal flash message; the Arabic translation lives in
`lang/ar.json`. It stores emails and nothing else — there is no sending machinery.

## Class hooks (`app.css`)

| Class | On | What it does |
| --- | --- | --- |
| `.store-shell` | `StoreLayout` / `AuthSimpleLayout` root | Pins the root font size and paints the page ground. The marker every `html:has(…)` rule keys off. |
| `.store-header` | the sticky header | Translucent + `backdrop-filter`, opaque fallback outside `@supports`. |
| `.store-cats-btn` | header "All categories" | Filled accent pill with glow. |
| `.store-slab` | hero card + sale banner | The full-bleed surface — tinted bright in light, near-black in dark. |
| `.store-slab-deco` | both slabs | The abstract shapes: an outlined ring + accent blob at `z-index: -1` under `isolation: isolate`, slow-drifting, reduced-motion aware. |
| `.store-band` | category grid section | Alternate ground for scroll rhythm. |
| `.gc-snake-btn` | `SnakeButton.vue` | The cloned snake-border CTA (see reference-site section). |
| `.gc-footer-v2` | `StoreFooter.vue` | The cloned footer (see reference-site section). |
| `.store-category-card` | homepage category tiles | White photo tile; hover lifts with an accent ring. |
| `.store-trust-item` | trust strip | Bordered surface chip with accent icon. |
| `.store-section-title` | every section heading | The short accent underline stamp (flips in RTL). |
| `.store-product-card` | `ProductCard` | Card, media frame, sale flag, wishlist button, price line. |
| `.store-filter-tag` | catalogue chips | Pill shape over `Tag.CheckableTag`. |
| `.store-totals` / `.store-money` | cart, checkout, order pages | Tabular figures pushed to the trailing edge. |
| `.overlay-scroll` | any scroll region | The shared overlay scrollbar. |

## The mini-game

`CatchGame.vue` is self-contained. The 2026-08-27 "juice" pass added: the basket is a
spring chasing the pointer (`basketTarget` lerped in the tick) and leans into its
velocity; caught items linger 0.3s to play a burst animation; a "+1" popper floats up
from each catch; the basket squash-and-stretches (`game-basket-pop` animates `scale` so
it composes with the inline translate/rotate transform — keep it that way); losing a
life shakes the arena; the score badge bumps via `:key="score"` remount; overlays
bounce in. Every animation is disabled under `prefers-reduced-motion`.

## Gotchas

- **The storefront pins its root font size too.** `app.css` scales `html` for the
  storefront, then pins it back to 16px under `html:has(.store-shell)`. If something
  looks off by a quarter, suspect a reintroduced rem/px mismatch before nudging a number.
- **Width and density move together, or the page gets worse.** The shop renders
  `max-w-7xl` at **100rem (1600px)** via `html:has(.store-shell) .max-w-7xl` — widened
  from 90rem to kill the side gaps on 1920px monitors, **paired with** a
  `2xl:grid-cols-5` step on the catalogue grid and the homepage grid's `:xl="4"` (6-up).
  Retune it there, never at the call sites, and never for the dashboard (1280). Widening
  without adding columns is what produced 367×540px product cards once already.
- **The brand marquee is full-bleed on purpose.** Clipped inside the container it stopped
  dead ~300px from each edge.
- **Size dark-surface steps in L\*, not contrast ratio.** Ground L* ~4, band L* ~10,
  card L* ~15. Ratio compresses near black; 1.045:1 measured as "different" and was
  invisible.
- **Content rules are shared with the dashboard.** Scoped
  `html:has(.admin-shell, .store-shell)`. Change them once; check both sides.
- **Status pills go through `StatusTag.vue`**, which owns the slug → AntD preset map for
  orders, payments and returns together.
- **Pagination takes Laravel's `links` array**, not a page number.
- **The wishlist heart is a state, not a hover affordance.** Always on screen; outline
  unsaved, filled red saved. `ProductCardResource` resolves it once per request via
  `once()`, outside the homepage's section cache.
- **No `window.confirm`.** Use `Popconfirm`.
- **`Avatar`'s responsive `size` object is not reliable** — plain CSS tiles instead.
- **`Input`, not `Input.Search`.**
- **Hardcoded English.** When you touch a page, check it renders in Arabic before calling
  it done.

## Verifying a change

```
npm run lint
npm run build
```

Visual QA is worth doing in all four combinations: light/dark × LTR/RTL. Both toggles are
in the header. `vue-tsc --noEmit` still fails on a pre-existing `tsconfig` `types` entry —
unrelated, don't chase it.

## Web Interface Guidelines pass (2026-09-01)

The storefront was audited against Vercel's Web Interface Guidelines and the
findings fixed. The parts that are easy to undo by accident:

- **`<h1>` is the visually-hidden line at the top of `Home.vue`, not the hero
  slide title.** The carousel rotates, and a page whose only top-level heading
  changes every six seconds gives a screen reader an outline that will not sit
  still. Slide titles are `<h2>`.
- **The first hero slide's image is `loading="eager"` + `fetchpriority="high"`;
  the rest stay lazy.** It is the page's LCP element.
- **Catalogue filter chips carry `role="checkbox"`, `aria-checked`, `tabindex`
  and Enter/Space handlers.** AntD's `Tag.CheckableTag` is a bare `<span>` with
  none of those — it was click-only, which is what an earlier QA pass reported
  as "the brand filter does nothing".
- **The toast stack is the live region** (`role="status" aria-live="polite"` on
  the container in `FlashMessages.vue`), not the individual toast. A live
  element inserted after the page settles is announced unreliably; text
  arriving inside a region that already exists is not.
- **The footer's accordion headings are `<button>`s inside the `<h4>`,** and
  they only report `aria-expanded` below `lg` (`useMediaQuery`) because above it
  the columns are always open — a collapsed state there would be a lie.
- **The particle canvas has a stop.** `.gc-motion-toggle` in the footer's bottom
  bar, remembered in `localStorage`; reduced motion still skips the canvas
  entirely. Motion that outlives five seconds beside other content needs a
  pause control, not only a reduced-motion path.
- **The mini-game owns the arrow keys through a focusable arena**
  (`tabindex="0"`, `role="application"`), not a `window` listener. The old
  global listener stole Left/Right from the whole page while the game was open.
- **Confirmations in the shop are declarative `<Modal>`s.** `Popconfirm` does
  not work here: its trigger fires `open-change` and no popover ever mounts.
  `Modal.confirm()` renders outside the shop's `ConfigProvider` and comes out
  unthemed at the wrong scale. `Cart.vue` shows the shape that does work. The
  account pages still use `Popconfirm` and are worth checking.
- **Interaction hygiene lives in one block at the bottom of `app.css`**:
  `touch-action: manipulation`, transparent tap highlight,
  `overscroll-behavior: contain` on drawers/modals/dropdowns/overlay-scroll
  regions, `scroll-margin-top` under the sticky header, `text-wrap: balance` on
  headings, and the `env(safe-area-inset-*)` hooks
  (`.store-tabbar-safe`, `.store-toast-safe`).
- **`theme-color` tracks the theme.** The blade bootstrap sets it before first
  paint and `updateTheme` keeps it in step; both use the `--st-ground` pair
  (`#f1f1ec` / `#0a0c0d`). Changing the ground means changing those two too.
- **Autofocus is desktop-only** on the auth screens and `DeleteUser`
  (`useMediaQuery('(min-width: 768px) and (pointer: fine)')`).

Not done, deliberately: non-breaking spaces between figures and their units in
`i18n`, which would mean touching every Arabic string for a typographic detail,
and list virtualisation, which the server-side paginators make unnecessary.
