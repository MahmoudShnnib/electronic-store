# ADR 0001 — Split the storefront off the backend (the Oth-codes layout)

Status: accepted, phase 1 in progress — 2026-10-17

## Context

The user asked for the backend to be split from the shop "the way Oth-codes is
written", so the shop becomes dynamic. `~/Oth-codes` holds two repos:

- `w-store-backend` — Laravel 12. Owns the database, the admin dashboard and POS
  (Inertia), and a versioned JSON API (`/api/v1`) that is the *only* thing the
  shop talks to. Shop content is a CMS: `pages` made of ordered `page_sections`
  (`type` + `data` JSON), plus a `/storefront` bootstrap endpoint (brand,
  navigation, footer, contact, payment methods) cached per content version.
- `watch-website` — a standalone React 19 + Vite SPA (react-router, TanStack
  Query, Tailwind). Pages fetch `/pages/{key}` and render `sections[]` through a
  type → component registry (`src/sections/registry.ts`), so the admin can
  reorder, hide, add and edit homepage blocks without a deploy.

Today this repo renders the shop with Inertia + Vue from the same app as the
admin. The homepage layout is hardcoded in `Home.vue`; only the hero slides and
two promo tiles are editable (two `settings` rows).

## Decision

1. **Backend stays in this repo** and keeps the admin dashboard and POS as they
   are (Inertia + Vue + Ant Design). Rewriting a working admin buys nothing.
2. **The shop moves to a new repo, `electronic-store-web`** (sibling of this
   one): React 19 + Vite + TypeScript + react-router + TanStack Query +
   Tailwind, laid out like `watch-website` (`lib/api/*`, `context/*`,
   `sections/*` registry, `pages/*`). It copies the *current* storefront look
   (green dark-tech theme, header, footer, cards, RTL) — new architecture, same
   face.
3. **The API is the contract.** Everything the shop shows comes from `/api/v1`.
   The existing mobile-oriented endpoints (catalog, cart via `X-Cart-Token`,
   checkout with `Idempotency-Key`, Sanctum bearer auth, orders, addresses,
   wishlist, reviews) are reused as-is; content endpoints are added.
4. **Shop content becomes a CMS**: `pages` + `page_sections`, same shape as
   Oth-codes but following this repo's conventions (`title` / `title_ar`
   columns, not spatie-translatable; images on the `public` disk by path, not
   medialibrary). Product-driven sections are resolved server-side so a page is
   one request.
5. The Inertia storefront keeps working until the SPA reaches parity, then its
   routes redirect to the SPA (phase 4). Nothing is deleted before that.

## API contract (content)

`GET /api/v1/storefront` — boot payload, cached, `version` changes on any
content edit:

```json
{ "data": {
  "version": "…",
  "locales": ["ar", "en"], "default_locale": "ar",
  "currency": { "code": "LYD", "symbol": { "ar": "د.ل" }, "fraction_digits": 3 },
  "contact": { "phone": "…", "email": "…" },
  "social_links": { "facebook": "…" },
  "payment_methods": ["cod"],
  "cities": [{ "en": "Tripoli", "ar": "طرابلس" }],
  "nav_categories": [{ "id": 1, "name": "…", "name_ar": "…", "slug": "…", "image": "…", "products_count": 12 }],
  "pages": [{ "key": "home", "slug": null, "title": "…", "title_ar": "…" }]
} }
```

`GET /api/v1/pages/{key-or-slug}` — a published page and its active sections in
order; 404 otherwise:

```json
{ "data": {
  "key": "home", "slug": null, "title": "…", "title_ar": "…", "seo": {},
  "sections": [{ "id": 3, "type": "product_carousel", "data": { "title": "…", "products": [ …ProductCard… ] } }]
} }
```

Section types (`App\Enums\PageSectionType`) and their stored → served `data`:

| type | stored | served adds |
|---|---|---|
| `hero` | `slides[]` of `{product_id, title, title_ar, image_path}`; empty = automatic | `slides[]` with `product` card + `image` URL, or `null` for automatic |
| `promo_tiles` | `tiles[]` of `{category_id, title, title_ar, image_path}`; empty = automatic | `tiles[]` of category cards |
| `category_rail` | `title`, `title_ar` | `categories[]` |
| `trust_strip` | `items[]` of `{icon, title, title_ar, text, text_ar}` | — |
| `product_carousel` | `title`, `title_ar`, `source` (`featured`/`new_arrivals`/`on_sale`/`best_sellers`/`category`/`manual`), `category_id?`, `product_ids?`, `limit` | `products[]` cards, `link` |
| `category_spotlight` | `count` (top N root categories, min 4 products) | `rows[]` of `{category, products[]}` |
| `brand_strip` | `title`, `title_ar` | `brands[]` |
| `image_banner` | `image_path`, `link`, `title`, `title_ar` | `image` URL |
| `rich_text` | `body`, `body_ar` (sanitised HTML) | — |
| `newsletter` | `title`, `title_ar`, `text`, `text_ar` | — |

Locale: content payloads carry both languages (`x` / `x_ar`) and the SPA picks.
Everything else follows the request's language: `SetApiLocale` (api group,
approved 2026-10-19) reads `?locale=ar|en`, then `Accept-Language`, then
`store.default_locale` (Arabic), and answers with `Content-Language`.

Static pages: `about`, `contact`, `privacy-policy`, `terms`, `return-policy` are
system pages (same keys as the Inertia `/pages/{page}` URLs), each with one
`rich_text` section. While that section is empty it is served with the
dashboard Settings text (`page.{key}`, plain text → escaped `<p>`), so both
shops agree; text written in the page builder wins. Saving any setting bumps
the content cache.

## API contract (catalogue)

`GET /api/v1/products` and `GET /api/v1/products/facets` take the same query
string (`App\Services\Storefront\CatalogQuery`): `q`, `categories` and `brands`
(slugs, `a,b` or `x[]=a&x[]=b`; the older single `category` / `brand` still
work; a category matches its active subtree), `min_price` / `max_price`
(dinars, like `current_price`), `in_stock`, `on_sale`, `featured`, `sort`
(`newest` | `price_asc` | `price_desc` | `name` | `best_selling`).

Facets: `{ total, categories: [{id, parent_id, slug, name, name_ar, count}],
brands: [{id, slug, name, name_ar, count}], price: {min, max}, in_stock,
on_sale, selected: {…} }`. Each facet ignores its own filter (so multi-select
shows what another option would add); category counts roll up the subtree;
zero-count options are omitted unless selected.

## API contract (product page, search, newsletter)

- `GET /products/{slug}` now also carries `short_description_ar`,
  `description_ar`, `available` (sellable units), per variant `on_sale` and
  `available`, and `specification_groups` — an ordered, bilingual
  `[{name, name_ar, items: [{name, name_ar, value, value_ar, unit}]}]`. The old
  English-keyed `specifications` stays for existing clients.
- `GET /products/{slug}/related` — up to 8 product cards: same category first,
  then the rest of the parent category's subtree, newest first.
- `GET /products/{slug}/reviews` — approved reviews, paginated (10), plus
  `summary: {avg, count, distribution: {5..1}}`, `can_review` and
  `review_eligibility` (`eligible` | `guest` | `already_reviewed` |
  `not_purchased`; send the bearer token to get the customer's own answer).
  Eligibility lives in `SubmitReview::eligibility()`, shared with the Inertia
  page and the submit action.
- `GET /search/suggest?q=` — up to 8 products and 4 categories for 2+ characters.
- `POST /newsletter` `{email}` — 201 new / 200 already subscribed, message in
  the `?locale=` language; throttled like auth.
- `GET /storefront` gains `store: {name, logo}` (Settings `store_name`;
  `public/logo.svg` — no storefront logo upload exists yet).

Not closable without schema work: categories and brands have no Arabic
description column.

## Phases

1. **Backend content API** (this branch): migrations, models, enum, payload
   builder, `/storefront`, `/pages/{key}`, CORS for the SPA origin, home page
   seeded with sections that reproduce today's homepage, tests.
2. **Admin page builder**: a Vue screen to add / reorder / toggle / edit
   sections (replaces `admin/homepage`, which keeps working meanwhile).
3. **`electronic-store-web` SPA**: scaffold, API client, contexts (locale/RTL,
   auth token, guest cart token), section registry, then pages: home, catalog
   (category/brand/search), product, cart, checkout, confirmation, auth,
   account (orders, addresses), wishlist, static pages.
4. **API gaps the SPA will hit** (add as found): catalog facets/filters,
   related products, search suggest, newsletter, returns, Google login handoff
   (token instead of session), static pages.
5. **Cut-over**: Inertia shop routes redirect to the SPA; delete the Vue
   storefront.
