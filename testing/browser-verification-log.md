# QA 2.0 — browser verification log

Live end-to-end verification of every fix applied in the QA 2.0 batch, run against the
real app (`http://127.0.0.1:8000`, real Postgres, Vite dev server on `[::1]:5173`) in
Chrome, both locales.

- **Session 1** — 2026-08-23 early hours (interrupted; laptop closed)
- **Session 2** — 2026-08-23 afternoon (documented, then stopped for the day)
- **Session 3** — 2026-08-23 evening (this log; resumed and finished the QA 2.0 list)
- **Status: QA 2.0 list complete.** One new defect found and fixed (B13). Six pre-existing
  unrequested issues were found here and fixed on 2026-08-24 — see
  [Found while testing — fixed 2026-08-24](#found-while-testing--fixed-2026-08-24).

Companion document: [`QA-2.0-report.md`](QA-2.0-report.md) — what was fixed and why.
This file is only about **what was tested, how, and what the app actually did**.

---

## Environment notes for the next session

| Thing | Value |
|---|---|
| App URL | `http://127.0.0.1:8000` (was 8001 in session 1 — the port moved) |
| Vite | user's own, `http://[::1]:5173` (IPv6 — an IPv4 `curl` cannot see it). **Do not start a second Vite**; it rewrites `public/hot`. |
| Admin login | `admin@store.test` / `password` |
| Customer login | `customer@store.test` / `password` |
| Test suite | `php artisan test` → **135 passed, 636 assertions** (re-run at the start of session 2, and again after every fix in session 3) |
| Session 3 convention | User said **"no need to restore anything so u continue nicely"** — demo/test data created while verifying is left as-is going forward. Only session 1–2 mutations were reversed (see table below); everything from session 3 onward stays. |

### Browser-automation gotchas (cost real time; don't rediscover them)

1. **`GET /logout` does nothing** — the route is POST. Log out with:
   ```js
   const t = decodeURIComponent(document.cookie.split('; ').find(c=>c.startsWith('XSRF-TOKEN=')).split('=')[1]);
   await fetch('/logout', {method:'POST', headers:{'X-XSRF-TOKEN':t,'X-Requested-With':'XMLHttpRequest'}});
   ```
   Silently running as the wrong user wasted a chunk of session 1.
2. **Chrome autofill overwrites typed emails.** Use `form_input` with an element ref, or
   `triple_click` + `Delete` before typing.
3. **Locale switch without the UI:** `POST /locale` with `{"locale":"ar"}` and the XSRF header.
4. **Toasts auto-dismiss after 3s** and a `screenshot` round-trip is often slower than that.
   Read them from the DOM instead: `[...document.querySelectorAll('[role=status]')].map(e=>e.innerText)`.
   A `MutationObserver` does **not** catch them (Vue inserts the node empty, then fills it).
5. **Clicking modal buttons by pixel coordinate is unreliable** — the modal is often cut off at
   the viewport bottom. Use `find` → click by `ref`.
6. **`window.confirm` freezes the extension.** Stub it before any destructive click:
   `window.confirm = () => true`.
7. `php artisan tinker <file>` hangs. Use `--execute` only.

---

## Results — every item, with what the app actually did

Legend: ✅ verified in the browser · ⚪ not yet tested · 🐛 new defect found while testing

### Admin

| # | Item | Result |
|---|---|---|
| B16 | Arabic admin messages | ✅ Brand edit modal in Arabic renders **حفظ** / **إلغاء**; saving raises the Arabic toast **"تم تحديث العلامة التجارية."** Return rejection raised **"تم تحديث طلب الإرجاع."** Coupon create raised **"تم إنشاء الكوبون."** |
| B16 | 🐛 **New defect** — status badges | Every `StatusBadge` without a server `*_label` rendered the raw English slug (`Active`) inside an otherwise fully-Arabic page. 29 of 42 call sites. **Fixed** (see below), re-verified: brands list now reads **نشط**. |
| B29 | Audit log diff | ✅ Session 1. Page fully Arabic RTL, events translated (تعديل / حذف / إنشاء), per-field diff with قبل/بعد. |
| B27 | Store name from settings | ✅ Header, footer, page titles and login all read **"Electronic Store69"** (the saved setting), not `config('app.name')`. |
| B4 | Redemption history | ✅ Clicking the usage count opens **"عمليات استخدام SUMMER"** listing customer, order `ORD-260823-YHYA8Z`, amount `7.000 د.ل.`, date. |
| B4 | Min order in whole units | ✅ `0.03` rejected at the input: *"Value must be greater than or equal to 1."* |
| B4 | Expired codes stay reserved | ✅ Seeded `QAEXPIRED` with `expires_at` in the past, then created `QAEXPIRED` again through the UI — accepted. The expired row is soft-deleted by the controller so its redemption history survives; list shows only the live one. |
| — | 🐛 Admin "New" modal pre-fill | ✅ Fixed in session 1, re-verified: **كوبون جديد** opens completely blank after a prior create. |
| A2 | Money format | ✅ Everywhere it was checked: `238.000 LYD` / `119.000 د.ل.` / `692.000 د.ل.` — amount first in both directions (bidi isolate holds under RTL). |
| A2 | 🐛 **New defect** — suggested refund | `/admin/returns/{id}` rendered the suggested refund as a raw `119.00` (no currency, 2 decimals) while every neighbouring figure went through `Money`. **Fixed**, re-verified live: now `119.000 د.ل.` |
| B19/B000 | Return photos reach admin | ✅ Both uploaded photos render as thumbnails on the admin return detail; files land in `storage/app/public/returns`. |
| **B24** | PO send / receive / cancel over HTTP | ✅ **Critical bug, now fixed.** Created `PO-260823-MTWDYF` (2 lines — 1 line does not arm `preventLazyLoading`) → **Send** (`draft→sent`, incoming reserved, `+0` on-hand) → **Receive** (`sent→received`, `+1` on-hand each line) — no 500 at either step. Created a second PO `PO-260823-VJXTEJ`, sent it, then **Cancelled it before receiving** — status → `ملغى`, ledger shows a real non-zero incoming-release movement, no 500. All three actions verified over real HTTP with a route-bound model. |
| B10 | Inventory pages, soft-deleted product | ✅ Soft-deleted **UltraSharp 27 4K Monitor** (had stock + an opening-stock movement). `/admin/inventory` and `/admin/inventory/movements` both load — 17/40 rows respectively, the soft-deleted product's name renders correctly instead of the old *"Attempt to read property name on null"* crash. |
| B2 | Product ID column | ✅ `/admin/products` shows an **ID** column and it's sortable (`?sort=id&dir=asc` → 1, 2, 3, 4, 6, 8… in order, the soft-deleted id 5 correctly absent). |
| **B13** | 🐛 **New defect found and fixed** — numeric spec value rejected on save | Typing **any** number (not just fractional) into a `type="number"` specification field — e.g. `7` or `6.9` into "Screen size (inch)" — made Save fail with *"The specifications.8.value field must be a string."* Root cause: Vue 3.4's `v-model` auto-detects the dynamic `:type="'number'"` binding on the input and silently casts the typed value to a JS number before it reaches Inertia's form data, but `ProductRequest.php:66` validates `specifications.*.value` as `string`. **Fixed** in `resources/js/pages/admin/products/Form.vue`'s submit transform — casts the value back to a string (except empty/null) right before sending. Re-verified: `6.9` now saves cleanly, persists across a reload, and renders as **"Screen size 6.9inch"** on the storefront product page. |
| B15 | Nested product-save errors invisible | ✅ Forced a duplicate variant SKU on `Galaxy S25 Ultra` (both variants set to `GALAXY-S25-ULTRA-V1`) → Save now shows an inline error under **each** variant's SKU field (*"The variants.0.sku field has a duplicate value."* / `variants.1`) **and** a *"يرجى تصحيح الحقول التالية:"* (please correct the following fields) summary block above Save listing both — confirms the `:error` binding and error-summary fix from the plan are live, not just present in the diff. |
| A2 | Admin order detail — labelled info cards | ✅ `/admin/orders/2` — the customer card shows separately labelled **الاسم** / **البريد الإلكتروني** / **الهاتف**, and the shipping-address card shows separately labelled **الاسم** / **الهاتف** / **المدينة** (`Tripoli`) / **العنوان تحديدا** (`Hay Alandalus street 5`) — no more one bare joined string. Discount renders `−0.000 د.ل.` with the sign correctly outside the bidi isolate. |
| B7 (admin) | Admin orders items count | ✅ Already correct — `/admin/orders` shows **2** for an order with 1 line of quantity 2 (units, not line count); `OrderQueryService` was never broken here, only the storefront equivalent was (already verified separately). |
| B16 (residual) | Product/PO save flashes + wrapped exceptions | ✅ Confirmed in code and live: `ProductController.php:56` and `PurchaseOrderController.php:51` both use `__()`, with Arabic strings present in `lang/ar.json` (*"تم إنشاء المنتج «:name»."*, *"تم إنشاء أمر الشراء :number."*). Live-verified the product-update flash: **"تم تحديث المنتج."** `CancelPurchaseOrder`, `SendPurchaseOrder`, and `TransferStock` exception messages are all wrapped in `__()` with matching `ar.json` entries (verified in code; the "empty PO" and "warehouses must differ" paths are guarded earlier by request validation so aren't reachable through the UI to see live, but the strings and translations are confirmed present). |
| **A2** | 🎯 **Critical bug, re-verified live** — expired sale prices no longer charged | Set an **expired sale window** on `Archer AX90 WiFi 6 Router` (`sale_ends_at` = yesterday) via the DB — the product page immediately stopped showing the struck-through sale price (`219.000` gone, only `249.000` shown), and adding it to cart charged **249.000 د.ل.** (regular price), not the stale `219.000`. Separately, `Galaxy S25 Ultra — 256GB` variant 1 has `sale_price_cents=109900` but its **parent product's** sale window already ended (`2026-08-22`) — the cart correctly charges the variant's regular price **1,199.000 د.ل.**, not the `1,099.000` sale price. Confirms both the product-level and variant-level halves of `ProductVariant::currentPriceCents()`'s window gating are working at the actual checkout price point. |

### Storefront — customer

| # | Item | Result |
|---|---|---|
| A8 | Return link in the orders list | ✅ Delivered order shows **Request return**; cancelled order shows only **View**. |
| A8 | Return page gated server-side | ✅ `/account/orders/1/return` (cancelled order) redirects to the order detail instead of rendering the form. |
| A4 | Cancel from the orders list | ✅ Pending order shows a red **Cancel order**; clicking it cancelled the order, raised the Arabic toast **"تم إلغاء طلبك."** and flipped the badge to **ملغى**. |
| B7 | Storefront items count | ✅ Column shows **units**, not line count (order with 1 line × 2 units reads `2`). |
| B19 | Photo type rejected visibly | ✅ Uploading `receipt.txt` → red *"Only JPG, PNG or WebP images are accepted."* |
| B19 | Photo count capped visibly | ✅ Uploading 6 PNGs → *"You can attach at most 5 photos."* (was a silent `.slice(0,5)`). |
| B6 | Rejected return cannot be re-requested | ✅ 2-unit order → returned 1 → admin rejected it → the form now shows **"1 returnable"**, not 2. The rejected unit stays consumed. Returning the last unit then showed **"0 returnable"** with the item and submit disabled. |
| A9/B22 | Out-of-stock is visible | ✅ Zeroed PS5 Pro stock (ledgered movement). Product page: red **"Out of stock"**, Add to cart *and* Buy now both `disabled=true`. Catalog card: **"Out of stock"** badge. |
| B23 | "Add to cart does nothing" | ✅ With 1 unit in stock, a second add raised a red toast **"Only 1 left in stock."** The 422 that used to vanish is now visible. |
| B3 | Coupon silently dropped | ✅ Applied `SUMMER` (−7.000 LYD, total 692.000). Soft-deleted the coupon in the DB, reloaded checkout → red banner **"This coupon is no longer available."** and the total honestly reverted to 699.000. Restoring the coupon brought back **Discount (SUMMER) −7.000 LYD**. |
| A1 | Checkout validation | ✅ Empty address → native *"Please fill out this field."* · Empty city → red border + **"Choose a city."** · Phone `aaaa` → **"Enter a Libyan mobile number, e.g. 0912345678 or +218912345678."** inline *and* as a toast. Dead hardcoded `country` field is gone. |
| A1 | Order placement | ✅ Valid data placed `ORD-260823-YHYA8Z` at 692.000 LYD with the coupon applied. |
| B5 | Reserved stock ledger | ✅ After cancelling that order: `quantity=1, reserved=0`, and the ledger holds a matching `order_cancellation` row (`before=0 → after=1`). No silent failure, no negative reserved. |
| A3 | One font | ✅ `Tajawal` is the only family on `<html>`, `<body>` and headings, in **both** `lang=en/dir=ltr` and `lang=ar/dir=rtl`. Instrument Sans is gone. |
| A2 | Arabic money | ✅ `692.000 د.ل.` renders amount-first under RTL. |

---

## Fixes made **during** this verification session

These were not in the original plan — the browser found them.

### 1. `StatusBadge` rendered raw English slugs (B16 residual)

`resources/js/components/admin/StatusBadge.vue` fell back to `props.status.replaceAll('_',' ')`
whenever a call site passed no `label`. 29 of 42 call sites do exactly that (`is_active`
flags, review status, item condition), so an Arabic admin page showed `Active` / `pending`
/ `resellable` in English.

Root-cause fix in the shared component rather than 29 call sites:

```ts
const { t, te } = useI18n();

const text = computed(() => {
    if (props.label) return props.label;
    const key = `admin.common.${props.status}`;
    return te(key) ? t(key) : props.status.replaceAll('_', ' ');
});
```

Added the missing keys to both `resources/js/i18n/{en,ar}.json` under `admin.common`:
`pending`, `approved`, `rejected`, `resellable` (`active`, `inactive`, `damaged` already existed).

### 2. Suggested refund bypassed `Money` (A2 residual)

`admin/returns/Show.vue` did `(suggested_refund_cents / 100).toFixed(2)` — the only figure on
the page not routed through the single formatting point. Moved the formatting to the server:

- `app/Services/Admin/ReturnQueryService.php` — `suggested_refund_cents` → `suggested_refund_formatted` wrapped in `Money::format()`
- `resources/js/pages/admin/returns/Show.vue` — renders the string

### 3. Numeric specification values rejected on save (B13, session 3)

`resources/js/pages/admin/products/Form.vue` renders spec-value inputs with a **dynamic**
`:type="attribute.type === 'number' ? 'number' : 'text'"` binding. Since Vue 3.4, `vModelText`
auto-detects `vnode.props.type === 'number'` at runtime (independent of any `.number` v-model
modifier) and casts the typed value to a JS number before it ever reaches `form.specifications`.
`ProductRequest.php:66` validates `specifications.*.value` as `string`, so **any** numeric spec
value — not just fractional ones — got silently rejected with *"The specifications.N.value field
must be a string."* This is a different, deeper bug than the `step="any"` fix already in the
codebase (that one only stopped the browser's native HTML5 constraint validation from blocking
submission before the request was even sent).

Root-cause fix in the submit transform, right before the Inertia request is built:

```ts
specifications: Object.fromEntries(
    Object.entries(data.specifications)
        .filter(([attributeId]) => visibleAttributeIds.value.has(Number(attributeId)))
        .map(([attributeId, spec]) => [
            attributeId,
            { ...spec, value: spec.value === '' || spec.value === null ? spec.value : String(spec.value) },
        ]),
),
```

Verified: `6.9` (and plain `7`) now save cleanly, persist across a reload, and render correctly
on the storefront product page.

---

## Regression closeout (session 3)

| Check | Result |
|---|---|
| `php artisan test` | **135 passed, 636 assertions** — re-run after the B13 fix and again after Pint, still green. |
| `./vendor/bin/pint --test` | Found pre-existing style issues in 5 files from earlier-session edits (`ApplyCoupon.php`, `SaveProduct.php`, `PlaceOrder.php`, `CreateRefund.php`, `ProductRequest.php`) — none from this session's own change. Ran `./vendor/bin/pint` to auto-fix; re-ran the test suite after, still 135 passed. |
| `npx prettier --check` | Clean on every changed `.vue`/`.js` file — no action needed. |
| `npm run build` | Clean production build, no errors. |

## QA 2.0 list: closed

Every item from the original QA 2.0 batch has now been exercised live in the browser at least
once across the three sessions, including the two "worse than reported" money bugs from Phase 1
(B24 purchase-order crashes, and the expired-sale-price overcharge — the latter re-verified this
session at both the product level and the variant-inherits-parent-window level, confirmed at the
actual cart charge point). One new defect (B13) was found and fixed during this pass. What's left
is the pre-existing, unrequested list below — never part of the QA 2.0 document, the user's call
on whether to schedule them.

---

## Found while testing — fixed 2026-08-24

All six were left as "your call" when first found; you later asked for them to be fixed.
See `QA-2.0-report.md` §5 for the fix details and live-verification notes. Left here for the
original context of how each was found.

1. ~~**Return reasons are untranslated.**~~ `ReturnController::create` shipped raw slugs
   (`defective`, `wrong_item`, …) and both the storefront select and the admin return detail
   rendered them as-is — English inside an Arabic page. Fixed: `ReturnReason` enum +
   translations, server-side validation against the whitelist.
2. ~~**Dates are never localized.**~~ `Sun, Aug 23, 2026 2:58 PM` on the Arabic orders list.
   Fixed: `Support\Dates` (mirrors `Money`), locale-aware via Carbon.
3. ~~**A cancelled order does not release its coupon redemption.**~~ `SUMMER` still read
   `used = 1` after `ORD-260823-YHYA8Z` was cancelled, so the usage limit was consumed by an
   order that no longer existed. Fixed: `CancelOrder` now deletes the redemption and
   decrements `used_count` (floored at zero).
4. ~~**A fully-returned order still renders the return form**~~ (with `0 returnable` and
   everything disabled). `Order::isReturnable()` checked status + window but not remaining
   quantity, and the orders list still showed the *Request return* link. Fixed:
   `hasReturnableUnits()`, N+1-safe via a `withSum` aggregate.
5. ~~**The `form.reset()` pre-fill bug still exists in 6 other admin pages**~~ — brands,
   categories, roles, users, warehouses, suppliers. Only `coupons/Index.vue` had the
   `form.defaults({...blank})` fix. Fixed: same one-line change applied to all six.
6. ~~**One orphaned return photo**~~ in `storage/app/public/returns` predated the B000 fix
   (`1CitdCBJoWKqPx0x4E2zBQ8ZppDOA9RyzoAXd6q9.png`). The fix itself was already correct — new
   failures clean up after themselves. Fixed: confirmed unreferenced in the DB, deleted.

---

## Demo data touched, and how it was left

Sessions 1–2: all mutations were made through the app's own actions so the ledger stays
honest, and all were reversed.

| Change | Reversal |
|---|---|
| PS5 Pro (id 6) stock 9 → 0 → 1 | Restored to **9** via a `correction` movement (`reason: 'QA test teardown: restore opening stock'`). Ledger keeps all four rows. |
| `SUMMER` soft-deleted to test B3 | Restored. |
| `QAEXPIRED` ×2, `QATEST1` coupons | Force-deleted. |
| Order `ORD-260823-YHYA8Z` placed then cancelled | Left cancelled — it is the evidence for A4/B5. |
| Return `RMA-260823-JYAASA` (rejected) + a second return on the same order | Left in place — evidence for B6/B19. |
| GameSir brand saved three times (no field changes) | Harmless; adds audit rows. |

Session 3: per the user's explicit instruction ("no need to restore anything so u continue
nicely"), nothing below was reversed — left as-is intentionally.

| Change | Left as |
|---|---|
| `PO-260823-MTWDYF` created, sent, received (2 lines: Archer AX90 ×1, LaserJet Pro M404 ×1) | Status `مستلم` (received). Stock levels reflect the real receipt (+1 each). |
| `PO-260823-VJXTEJ` created, sent, then cancelled before receiving (2 lines: MX Master 4 Mouse ×1, ZenBook 14 OLED ×1) | Status `ملغى` (cancelled). Incoming reservation released, on-hand stock untouched. |
| `UltraSharp 27 4K Monitor` (product id 5) | Soft-deleted (moved to trash) to test B10. Still restorable from `/admin/products` → *Show trash*. |
| `Galaxy S25 Ultra` — Screen size spec | Set to `6.9` (was empty). Duplicate-SKU test on its variants was typed then reverted back to the real SKUs before saving, so the product itself is not left broken. |
| `Archer AX90 WiFi 6 Router` (product id 8) | `sale_starts_at`/`sale_ends_at` set to an expired window (ends yesterday) via tinker, to prove the sale price stops applying. Sale price itself (`219.000`) untouched — only the dates were added. |
| Guest/admin-session cart | Now holds `Galaxy S25 Ultra — 256GB` ×1 (pre-existing from earlier testing) + `Archer AX90 WiFi 6 Router` ×1 (added this session), plus a stale, correctly-rejected `QA10` coupon code. |
