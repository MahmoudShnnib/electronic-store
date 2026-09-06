# One library, two products

Change record for the storefront's move to Ant Design Vue — 41 storefront files touched,
138 deleted, one component library removed.

This is the companion to `admin-design-polish.md`, which covered the dashboard. Where that
document is about polishing an already-migrated surface, this one is about a migration: the
storefront was still on shadcn/radix, and the two halves of the app looked and behaved like
two different codebases because they were.

For the architecture — class hooks, scoping rules, named traps — read
`docs/agents/storefront-ui.md`. This file is the record of what moved and why.

---

## The thing that was actually wrong

The storefront had **no `ConfigProvider` at all**. Only `Home.vue` carried a local one,
which is why exactly one page rendered green AntD controls and every other page fell back
to AntD's default blue and LTR — under an app that is bilingual and RTL half the time.

That single omission explains most of what looked like unrelated bugs. It is now on
`StoreLayout` (theme + locale + direction), with the auth screens carrying their own
because they sit outside that layout.

## The container bug

The promo rail bled off both edges of the viewport. Not a rail bug.

The storefront scales its root font — 16px, 17px ≥768px, 20px ≥1280px — but Ant Design's
tokens are px and do not scale with it. At a 1536px viewport the root was 20px, so:

| | intended | actual |
| --- | --- | --- |
| `max-w-7xl` (80rem) | 1280px | **1600px** |

A container wider than the viewport, so the "centred" rail started off-screen. This is the
same rem/px mismatch that produced the dashboard's 64px gutter, and it takes the same fix:
pin the root back to 16px inside `.store-shell`. Both systems now agree on one scale.

**Watch for it:** if something on either shell looks off by roughly a quarter, suspect a
reintroduced rem/px mismatch before nudging a number.

---

## What moved

**Foundation**

- `useStoreTheme()` — the shop's tokens in one place, shared by `StoreLayout` and
  `AuthSimpleLayout`. `colorPrimary: #0F9D58`, because green was already the site's
  commerce colour (sale flags, "buy now", the saving line); making it the seed put every
  primary button, focus ring, checked control and active tab on it instead of leaving forty
  call sites to hardcode one. The dashboard stays blue: different products to the person
  using them.
- `.store-shell` + a storefront chrome block in `app.css`, mirroring the admin's.

**Pages** (17) — Home, Catalog, Product, Cart, Checkout, Order confirmation, Wishlist,
Static pages; account Orders, Order detail, Addresses, Returns, Request return; and the
three settings screens, which were still on a *third* layout (the starter kit's shadcn
sidebar) even though the account menu links straight to them.

**Auth** (6) — Login, Register, Forgot, Reset, Confirm, Verify. All six were shadcn, and
four of them were hardcoded English.

**Components** (10) — ProductCard, RatingStars, QuantityInput, Pagination, EmptyState,
SearchBar, CatalogFilters, CitySelect, MobileBottomNav, plus a new `StatusTag`.

### Substitutions worth knowing

| Was | Now | Why |
| --- | --- | --- |
| 184-line hand-rolled combobox (`CitySelect`) | AntD `Select` with `show-search` | ~50 lines. Loses the custom mobile sheet; AntD's dropdown is fine on touch. |
| `window.confirm` on cancel/delete | `Popconfirm` | A native dialog blocks the page, can't be translated, can't be styled. |
| Hand-rolled status colour maps in three files | `StatusTag.vue` | A customer reading "refunded" on an order and on a return should see the same chip. |
| `Input.Search` | plain `Input` + prefix icon | Its attached button is a dead control beside debounced suggestions — the same call `FilterBar` already made. |
| Stacked description / specs / reviews | `Tabs` | Three separate reads, not three walls to scroll past. |
| Custom success page | AntD `Result` | It already owns the icon, hierarchy and action row. |

### Rules shared rather than copied

The status-pill palette, table interiors, card edges, overlay scrollbars and the entire
bidi block are now scoped `html:has(.admin-shell, .store-shell)`. `:has()` takes a selector
list, so **one** rule covers both shells at the same specificity a single one had.

Without this the shop's `<Tag color="success">` would have used AntD's washed-out preset —
the exact thing the dashboard pass had already fixed once. `.admin-scroll` was renamed
`.overlay-scroll` for the same reason: it was never admin-specific.

---

## The deletion

`pages/Dashboard.vue` and `pages/Welcome.vue` were unrouted starter-kit leftovers.
`Welcome.vue` even called `route('dashboard')`, which does not exist — it would have thrown
if anything had rendered it.

They were still being bundled, because `app.ts` resolves pages through
`import.meta.glob('./pages/**/*.vue')`. Those two files dragged the entire shadcn/radix
tree into the production build behind them.

I traced reachability from the live entry points and removed what nothing could reach:

- **138 files** — 109 shadcn `ui/*` components, the app-shell tree (`AppSidebar`,
  `AppHeader`, `NavMain`, `UserMenuContent`, …), three dead layouts, `lib/utils.ts`.
- **6 dependencies** — `radix-vue`, `@headlessui/vue`, `clsx`, `tailwind-merge`,
  `class-variance-authority`, `lucide` (the vanilla one; `lucide-vue-next` stays).
- `components.json`, shadcn's config, whose aliases already pointed at a directory that no
  longer existed.

The `en-gb` locale chunk alone went **255.8 kB → 97.8 kB**.

---

## Follow-up round

Three defects you reported after the migration landed.

### The wishlist button vanished instead of morphing

The real fault was that **nothing ever told the card whether a product was saved**. The
heart was a fire-and-forget toggle with no state behind it, and it appeared to vanish
because it was hover-gated — after the click Inertia re-rendered the card and the fade-out
won the race.

- `ProductCardResource` now exposes `wishlisted`, resolved **once per request** via
  `once()`: a 24-card grid costs one query, not 24. It also stays out of the homepage's
  10-minute section cache, which holds models rather than rendered resources — so no
  customer can ever be served another's saved state.
- The hover gate is gone. The heart is **always** on screen: a quiet outline when unsaved,
  filled red when saved, with a short pop on the swap and `aria-pressed` for screen
  readers. Hover-gating also meant the control did not exist at all on touch.

### Things stayed dark in light mode

Real, and larger than it looked: the hero, the sale banner, the alternating promo tiles and
the footer all carried **hardcoded dark colours**. They were painted for a dark page and
never consulted the theme, so half the promo rail was literally `#1C1C1C` under a white
page.

The storefront palette is now custom properties on `.store-shell` — `--st-ground`,
`--st-surface`, `--st-ink`, `--st-line`, `--st-slab`, `--st-tile-*`, `--st-footer` — so a
surface is described once and both modes stay in step.

**Measured after: zero dark-painted elements** on home, product, checkout or settings in
light mode. The only remaining hit is AntD's image hover mask, which is supposed to be
dark.

### Light mode was boring

It was white cards on a white page — the same flatness the dashboard had before its content
field was stepped back.

- Warm off-white ground (`#F6F6F3`) with white cards on top, so cards read as surfaces.
- Hero and sale banner became a soft green-tinted panel with a radial accent glow behind
  the product, instead of a black band.
- Promo tiles alternate white and a soft green wash; dark mode keeps the original
  cream-and-charcoal checker. Their discount flag inverts against whichever tile it sits
  on, so it can't render white-on-pale.
- The footer steps one further from the ground, closing the page in either theme.
- The ◆ brand mark takes the accent green everywhere — header, drawer, footer, sign-in.

Green stays the only saturated colour on the page.

---

## Bugs found along the way

- **`Avatar`'s responsive `size` object doesn't resolve.** The category rail asked for
  `{ xs: 112, …, lg: 176 }` and every tile rendered at AntD's 32px default, because the
  breakpoint hook had not resolved. Plain CSS now.
- **`Big Summer Sale` was hardcoded English** — and survived a first replacement pass that
  silently no-op'd against reformatted source. Caught by re-reading the rendered page
  rather than trusting the edit.
- **`dropdownClassName` is deprecated** in AntD 4 (`popupClassName` now) — surfaced as
  console errors on every page carrying a Select or AutoComplete.
- **Hardcoded English** across four auth screens and all three settings pages; ~40 new keys
  in both locales.
- **The settings pages were on a third layout entirely**, so clicking "Settings" from the
  account menu dropped the customer into a different-looking application.

## Not done, and why

- **`vue-tsc --noEmit`** still fails on a pre-existing `tsconfig` `types` entry
  (`./resources/js/types`). Unrelated to any of this; documented as don't-chase.
- **`HandleInertiaRequests` was left alone.** Sharing the wishlist ids as a global Inertia
  prop would have been the tidier plumbing, but `app/Http/Middleware/` is a
  confirm-first zone in `CLAUDE.md`, and the per-resource approach is safe without it.
- **`MobileBottomNav` stays bespoke.** AntD has no floating pill nav; it takes the accent
  so it agrees with everything else, but it is not an AntD component and shouldn't pretend
  to be.
- **`@vueuse/*` and `@tailwindcss/forms`** are unreferenced but still installed. Removing
  them wasn't provably safe in the same pass as 138 file deletions; worth a follow-up.

## Verification

Run on the final state:

```
npm run lint            clean
npm run build           clean
./vendor/bin/pint --dirty   passed
./vendor/bin/pest       149 passed (660 assertions)
```

Visual QA covered light/dark × LTR/RTL at 1536px and at a real 393px viewport, with zero
console errors on every page opened. One thing I nearly reported as a bug and shouldn't
have: a headless screenshot showed the mobile hero text clipped, but measuring the live
viewport gave `scrollWidth === clientWidth` with the paragraph fully inside. That was a
capture artifact.
