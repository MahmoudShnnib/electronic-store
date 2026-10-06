# Admin restyle — progress

Branch `admin-restyle` (cut from `audit/finance`, which contains all of `main`).
Spec: `design/ADMIN_STYLE.md`. If interrupted, continue from "Next step".

## Next step

Tokens + theme plumbing (`resources/js/theme/adminTokens.ts`, `useAdminTheme`).

## Done

- [x] Step 1/2 — `ADMIN_STYLE.md` (design system + page map)

## Foundation

- [ ] Tokens module, AntD theme, `--ad-*` CSS variables, Tailwind `ad-*` colours, font
- [ ] Split shared admin/store CSS so admin rules can change without touching the shop
- [ ] Layout: sidebar (collapsible groups), top bar, content padding
- [ ] Shared components: PageHeader, StatCard, DataTable, StatusBadge, FilterBar,
      Pagination, FormModal, ConfirmDialog, Field, HelpHint, AdminSelect, AdminDateInput
- [ ] PosLayout

## Pages (numbers from ADMIN_STYLE.md §8)

| # | Page | Status | Notes |
| --- | --- | --- | --- |
| 1 | Dashboard | todo | |
| 2 | Products | todo | |
| 3 | Product form | todo | |
| 4 | Categories | todo | |
| 5 | Brands | todo | |
| 6 | Specifications | todo | |
| 7 | Stock | todo | |
| 8 | Stock movements | todo | |
| 9 | Warehouses | todo | |
| 10 | Suppliers | todo | |
| 11 | Purchase orders | todo | |
| 12 | PO form | todo | |
| 13 | PO detail | todo | |
| 14 | PO print | todo | paper — toolbar only |
| 15 | POS register | todo | |
| 16 | POS open shift | todo | |
| 17 | POS close shift | todo | |
| 18 | POS receipt | todo | paper — toolbar only |
| 19 | Cashier shifts | todo | |
| 20 | Orders | todo | |
| 21 | New order | todo | |
| 22 | Order detail | todo | |
| 23 | Invoice | todo | paper — toolbar only |
| 24 | Delivery slip | todo | paper — toolbar only |
| 25 | Refunds | todo | |
| 26 | Treasury | todo | |
| 27 | Customers | todo | |
| 28 | Customer detail | todo | |
| 29 | Reviews | todo | |
| 30 | Coupons | todo | |
| 31 | Sales report | todo | |
| 32 | Inventory report | todo | |
| 33 | Invoices report | todo | |
| 34 | Users | todo | |
| 35 | Roles | todo | |
| 36 | Audit logs | todo | |
| 37 | Settings | todo | |

## Verification

- [ ] `npm run lint`, `npm run build`
- [ ] `./vendor/bin/pest`
- [ ] Browser walk-through of every page: dark/light × LTR/RTL, desktop + phone width
- [ ] Storefront spot-check (unchanged)

## Issues found
