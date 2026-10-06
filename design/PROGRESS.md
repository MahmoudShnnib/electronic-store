# Admin restyle — progress

Branch `admin-restyle` (cut from `audit/finance`, which contains all of `main`).
Spec: `design/ADMIN_STYLE.md`. If interrupted, continue from "Next step".

## Next step

Nothing — restyle complete. Open items for the user are under "Issues found".

## Done

- [x] Step 1/2 — `ADMIN_STYLE.md` (design system + page map)

## Foundation

- [x] Tokens module, AntD theme, `--ad-*` CSS variables, Tailwind `ad-*` colours, font
- [x] Split shared admin/store CSS so admin rules can change without touching the shop
- [x] Layout: sidebar (collapsible groups), top bar, content padding
- [x] Shared components: PageHeader, StatCard, DataTable, FilterBar rewritten; StatusBadge,
      Pagination, FormModal, ConfirmDialog, Field, HelpHint, AdminSelect, AdminDateInput
      restyled through AntD tokens + the admin CSS block (no markup change needed)
- [x] PosLayout

## Pages (numbers from ADMIN_STYLE.md §8)

| # | Page | Status | Notes |
| --- | --- | --- | --- |
| 1 | Dashboard | done | reference layout rebuilt |
| 2 | Products | done | tokens / shared components; browser-checked |
| 3 | Product form | done | tokens / shared components; browser-checked |
| 4 | Categories | done | tokens / shared components; browser-checked |
| 5 | Brands | done | tokens / shared components; browser-checked |
| 6 | Specifications | done | tokens / shared components; browser-checked |
| 7 | Stock | done | tokens / shared components; browser-checked |
| 8 | Stock movements | done | tokens / shared components; browser-checked |
| 9 | Warehouses | done | tokens / shared components; browser-checked |
| 10 | Suppliers | done | tokens / shared components; browser-checked |
| 11 | Purchase orders | done | tokens / shared components; browser-checked |
| 12 | PO form | done | tokens / shared components; browser-checked |
| 13 | PO detail | done | tokens / shared components; browser-checked |
| 14 | PO print | done | paper — untouched |
| 15 | POS register | done | tokens; .admin-shell fixes basket clipping |
| 16 | POS open shift | done | tokens |
| 17 | POS close shift | done | AntD tokens only |
| 18 | POS receipt | done | paper — untouched |
| 19 | Cashier shifts | done | tokens / shared components; browser-checked |
| 20 | Orders | done | tokens / shared components; browser-checked |
| 21 | New order | done | tokens / shared components; browser-checked |
| 22 | Order detail | done | tokens / shared components; browser-checked |
| 23 | Invoice | done | paper — untouched |
| 24 | Delivery slip | done | paper — untouched |
| 25 | Refunds | done | tokens / shared components; browser-checked |
| 26 | Treasury | done | tokens / shared components; browser-checked |
| 27 | Customers | done | tokens / shared components; browser-checked |
| 28 | Customer detail | done | tokens / shared components; browser-checked |
| 29 | Reviews | done | tokens / shared components; browser-checked |
| 30 | Coupons | done | tokens / shared components; browser-checked |
| 31 | Sales report | done | chart colours from tokens |
| 32 | Inventory report | done | tokens / shared components; browser-checked |
| 33 | Invoices report | done | tokens / shared components; browser-checked |
| 34 | Users | done | tokens / shared components; browser-checked |
| 35 | Roles | done | tokens / shared components; browser-checked |
| 36 | Audit logs | done | tokens / shared components; browser-checked |
| 37 | Settings | done | tokens / shared components; browser-checked |

## Verification

- [x] `npm run lint`, `npm run build`, `pint --dirty` — clean. `prettier --check resources/`
      flags only `components/pos/ReceiptPaper.vue`, which this branch never touched.
- [x] `./vendor/bin/pest` — 497 passed (2342 assertions)
- [x] Automated crawl of 30 routes: no horizontal overflow at 375px, 768px and 1440px;
      no stray light surfaces in dark mode
- [x] Screenshots: dashboard (dark/light, RTL/LTR, phone + drawer), orders, order detail,
      products, product form, sales report, audit logs, roles, coupons modal, POS register
- [x] Storefront spot-check: Tajawal font, 14px cards, pills and layout unchanged

## Issues found

- Pre-existing: right after login the dashboard can render English text in an RTL
  layout (client i18n/`<html dir>` keep the login page's locale until a full reload).
  Not a styling issue; left alone.
- Pre-existing: the product form title "New product" is not translated.
- Not screenshot-reviewed one by one (crawl-checked only): brands, categories,
  warehouses, suppliers, PO pages, stock/movements, refunds, treasury, customers,
  reviews, users, settings, inventory/invoices reports, POS shift screens.
- Browser automation: clicks in the emulated 1440px viewport mis-map; interactions
  were checked through DOM events instead.
