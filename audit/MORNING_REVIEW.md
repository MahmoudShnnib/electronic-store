# Update 2026-10-10 — owner decisions applied (branch `feat/finance-decisions`)

| Decision | What changed | Commit |
|---|---|---|
| BD3 | New `lost` (lost / stolen) stock write-off type | `005d3b7` |
| BD8 | A product with any stock (shelf, reserved, damaged, incoming) can't be deleted; bulk delete skips and names them | `662fd40` |
| BD7 | Cancelling keeps the coupon use as history, marked released (migration `2026_10_10_100000`) | `6759c62` |
| BD4 | Zero price allowed, with a warning on the product form | `d87950f` |
| (follows "they only pay in full") | A payment must be exactly the amount owed; order page has one "Record full payment" button | `e486b91` |
| BD1 / BD6 | Whole returns only: every unit back + everything paid refunded, or nothing; a delivered order is refunded only through the return form | `2abccfc` |
| BD5 | Revenue = money received, on the day it arrives (payments in, refunds out) — reports + dashboard | `3f57e4f` |
| BD2 | FIFO costing: every purchase keeps its unit cost, sales use the oldest units first, profit uses what they cost; stock dialog asks the unit cost; POS shows admins the highest cost of the units in that warehouse (branch `feat/fifo-costing`, migration `2026_10_10_110000`) | `53818d5`, `6a79e1a`, `31fe2b7`, `31bc0cd`, `539404b`, `91c9959` |

Also on `feat/fifo-costing`: `32e0af1` (separate session) — two requests creating the same new
stock row at once no longer abort the Postgres transaction. Checks on the branch tip: 536 tests
pass (543 after the owner-call commits), Concurrency suite 5 pass, `php audit/check_invariants.php` ALL RULES PASS (dev DB), pint clean.

Rules M3/M4: the case that broke them (deposit refunded, then paid in full) can no longer
happen now that payments and refunds are always whole.

Needs on deploy: `php artisan down`, `php artisan migrate` (2026_10_05_100000, 2026_10_10_100000,
2026_10_10_110000, 2026_10_11_100000, 2026_10_11_110000), reload PHP-FPM, `php audit/check_invariants.php` (S10/S11 must pass), `php artisan up`.
The FIFO migration seeds one batch per stock row at today's cost; stock moved by old code between
the migration and the code switch would have no batch, hence the maintenance window.

Owner calls on the FIFO gaps (2026-10-10), all built on `feat/fifo-costing`:
- PO shipping and discount are spread over the received units (`d3a2b0f`).
- Free (0-total) orders book their goods' cost the day they're completed (`6e626c4`).
- Sales report shows what written-off stock cost, by kind (`15929f9`, `321a54c`; migrations
  `2026_10_11_100000`, `2026_10_11_110000`). Moving stock onto new variants has its own type
  (`variant_split`) and is not a write-off; old write-offs are costed at the cost price of the time.
- Supplier returns keep using the oldest stock: the owner only cancels POs, never ships received
  stock back (no screen for it today).
- Still open: cashiers can see product cost prices on the products page (owner: later).
- Mahmoud's PR #5 (POS one card per product) merges cleanly but hides the admin "highest cost" line;
  integration fix ready on local branch `compat/pr-check` (`c05d0b4`) — apply after #5 lands on main.
  PR #4 (storefront restyle) is compatible as is.

---

# Morning review — overnight finance & inventory audit (2026-10-05)

Branch `audit/finance` (forked from `fix/db-consistency-qa` @ d46c4ee). 18 commits, nothing
pushed or merged. Your uncommitted `CLAUDE.md` / `package-lock.json` changes are untouched.
Full suite: **497 passed** (was 445). `php audit/check_invariants.php`: **ALL RULES PASS**
on the local dev DB and the test DB.
19 iterations of 80 used.

## 1. Scenario results

| id | Scenario | Result |
|---|---|---|
| H1 | Buy 1 item | ✅ |
| H2 | Buy several items | ✅ |
| H3 | Buy the last item in stock | ✅ |
| H4 | Full refund | ✅ after BUG 1 |
| H5 | Partial refund (one item of several) | ✅ after BUG 1 (🔴 BD1) |
| H6 | Refund then re-buy | ✅ after BUG 1 |
| H7 | Add stock | ✅ |
| H8 | Add stock to a product with pending orders | ✅ (🔴 BD2) |
| H9 | Change price; old orders and reports unchanged | ✅ |
| H10 | Edit an order before shipping | **BLOCKED** — no order-editing feature exists |
| H11 | Cancel an unpaid order | ✅ after BUG 3 |
| H12 | Cancel a paid order (+ deposit refunded on an open order) | ✅ after BUGS 3, 4 |
| H13 | Write off stolen/damaged, incl. one in a cart | ✅ after BUG 2 (🔴 BD3) |
| F1 | Payment fails / times out after stock reserved | ✅ |
| F2 | Refund fails at the payment step | ✅ |
| F3 | App/DB error mid-purchase (and mid-payment) | ✅ |
| F4 | Two customers buy the last unit at the same time | ✅ (real forked processes) |
| F5 | Double-click Pay / retried request (sequential + simultaneous) | ✅ |
| F6 | Refund > paid, twice, on cancelled / unpaid order | ✅ |
| F7 | Write off more than exists | ✅ |
| F8 | Delete a product that has past orders | ✅ |
| F9 | Invalid inputs (negative, zero, decimal, huge) | ✅ |
| X1 | Extra: inventory value vs sale cost for a variant with no own cost | ✅ after BUG 6 |
| X2 | Extra: dashboard day vs report day around midnight | ✅ after BUG 7 |

Each scenario test snapshots ~every money/stock place (stock levels, ledger, orders and
lines, admin order page, payments, refunds, treasury, coupons, catalogue prices, storefront
availability, sales/invoice/valuation reports, dashboard), runs the action through the real
routes, asserts the **exact** set of places that changed, and then runs the invariant checker.
Tests: `tests/Feature/FinanceAudit/*`, `tests/Concurrency/RaceTest.php`.

## 2. Bugs found (all fixed — none blocked)

| # | Bug | Commit |
|---|---|---|
| 1 | Dashboard best sellers counted refunded orders in full (a fully returned product still showed as a best seller with its revenue). Regression from 3dde664. | `cb287ee` |
| 2 | Supplier returns "from damaged" left no ledger record of how many units left the damaged count. New column `inventory_movements.damaged_change` + migration + invariant S9. | `cf1059e` |
| 3 | "Outstanding" on the order page and invoices report ignored cancellation and refunded deposits — showed a cancelled order as owing its total, and less owed than completion actually collects after a deposit refund. | `89dbbb5` |
| 4 | Revenue (reports + dashboard) subtracted a refunded *deposit* from the sale: deposit 300 refunded, order then paid 1,000 → booked 700 while the treasury held 1,000. | `df328b9` |
| 5 | Manual treasury deposits/withdrawals could be saved with no reason. Note is now required. | `926983a` |
| 6 | Inventory valuation counted variants with no own cost at 0; sales/profit use the product cost. | `820115a` |
| 7 | Dashboard "today"/"this month"/chart used UTC days; reports use Africa/Tripoli. | `5e16eea` |

Details (expected / actual / place / root cause): `audit/BUGS.md`.

## 3. 🔴 Business decisions required (nothing implemented)

- **BD1** Product/category/brand reports drop an order entirely once any refund lands (a kept phone vanishes because one cable came back). Keep, net returned units, or split the refund? (details below)
- **BD2** Costing: receiving a PO never updates product cost, so valuation/profit use the typed cost, not what was paid. Standard cost, last cost or weighted average?
- **BD3** No "stolen/lost" movement type — theft is a `manual_decrease` with free text.
- **BD4** A product can be priced 0 (form allows `min:0`); such orders complete as "paid" with no payment. Intended?
- **BD5** Revenue is recognised when an order is *placed* (open, unpaid COD orders count). Or at payment/completion?
- **BD6** A partial refund moves a completed order to `refunded`; after that no other item can go through the return form (only money-only refunds).
- **BD7** Cancelling an order hard-deletes its coupon-redemption row (the order keeps discount + coupon code).
- **BD8** A deleted (trashed) product's remaining stock still counts in inventory value. Units physically exist, so I left it.
- **Rules M3/M4** — my own wording is wrong for "deposit refunded, then order completed"; proposed new wording below. Not changed, per your rule.

## 4. Changes to payment, refund or pricing logic — review these first

No change was made to `app/Actions/Payments/`, `app/Actions/Checkout/`, `CreateRefund` or
`ResolveOrderIssue`, middleware, auth or roles. What *does* touch money:

1. **`df328b9` revenue formula** (`ReportService::NET_TOTAL`, `DashboardController` `$netTotal`): open orders count at their total; completed/refunded at payments − refunds (= total − refunds for every ordinary sale). Changes reported revenue only where a refund happened on an open order.
2. **`89dbbb5` outstanding** (`OrderQueryService::showProps`, `ReportService::invoices/invoiceSummary`): total − (payments − refunds) for open/completed orders, 0 for cancelled/refunded — the amount `settleOutstanding` collects. Display/report only; collection logic unchanged.
3. **`926983a` treasury note required** (`StoreTreasuryTransactionRequest`). Four existing tests now send a note so they keep testing what they were written for.
4. Reporting only: `cb287ee` (best sellers), `820115a` (valuation cost fallback), `5e16eea` (dashboard timezone).
5. Inventory: `cf1059e` adds `damaged_change` to the ledger (`RecordMovement`, migration).

## 5. Top 3 recommendations

1. **One source of truth for derived money.** 4 of 7 bugs were the same number (revenue, outstanding, best sellers, cost) computed differently on different screens. Put "net paid", "owed", "revenue of an order" and "unit cost" in one place (a query scope / small service) that reports, dashboard, order page and invoices all call.
2. **Make refunds say what they are for.** A refund row has no link to order lines and no "prepayment vs goods returned" flag, so reports must guess from order status (BD1, BUG 4, rules M3/M4). Add `refund_items` (or at least a `kind`) when you next touch refunds.
3. **Run the checker continuously.** Schedule `php audit/check_invariants.php` (read-only) daily against production and alert on FAIL, and keep the `Concurrency` suite in CI (needs `pcntl`/`posix`). The existing row locks held under every race I threw at them; this keeps it that way.

## 6. Things you need to do

- ~~`php artisan migrate` on dev~~ — done 2026-10-05 at your request (S9 now PASS on dev). Still needed on deploy: `2026_10_05_100000_add_damaged_change_to_inventory_movements`.
- CI: `phpunit.xml` now has a `Concurrency` suite; the runner needs the `pcntl` and `posix` PHP extensions.
- Decide BD1–BD8 and the M3/M4 wording.

---


## Questions / notes for you

- The overnight prompt and the project CLAUDE.md disagree: CLAUDE.md says Payments/Checkout/Returns need your OK first, the prompt says never wait. I followed the prompt for audit bug fixes only (DECISIONS D2) and list every such change below so you can review them first.

## 🔴 Business Decision Required

- **BD1 — Product/category/brand reports after a partial refund.** The sales summary nets a
  refund per order (revenue 1,100 − 50 refunded = 1,050), but the per-product, per-category
  and per-brand breakdowns drop any order with a refund entirely (`ReportService::revenueItems`),
  so a phone the customer kept vanishes from "sales by product" because one cable came back.
  The code's own comment says per-product lines "can't be netted against an order-level
  refund". Options: (a) keep as is; (b) include refunded orders and subtract returned *units*
  (from the return ledger) while attributing the refunded money to the returned lines;
  (c) proportional split of the refund across lines. Needs your call — a refund amount isn't
  always tied to a returned item (e.g. 50 LYD goodwill for a scratch). Pinned by
  RefundTest "H5". The dashboard's best-sellers list now follows the same rule (BUG 1).

- **BD2 — Inventory costing method.** Receiving a purchase order never updates the product's
  cost price, so inventory valuation and profit use whatever cost was typed on the product
  form, not what was actually paid (H8: PO at 650, valued at 700). Options: keep manual
  standard cost / last purchase cost / weighted average. Not changed.
- **BD3 — "Stolen / lost" stock.** There is no movement type for theft or loss; staff use
  "manual decrease" with a free-text reason (H13). Works for the ledger, but theft can't be
  reported separately from miscounts. Add a `lost` type? Not changed.

## Things you need to do

- Run `php artisan migrate` on the dev DB (and production when deploying): migration
  `2026_10_05_100000_add_damaged_change_to_inventory_movements` (BUG 2). I did not migrate the
  dev DB myself; `check_invariants` shows S9 as SKIP until you do.

## Rules that look wrong (not changed — your call)

- **M3** says "a completed order never carries refunds (it must have moved to refunded)" and
  **M4** says "every closed order was paid exactly its total". Both break for a legitimate
  flow: take a 300 deposit, refund it while the order is open, complete the order (1,000
  collected). Money is right (paid 1,300, refunded 300, kept 1,000) but the wording fails.
  Proposed wording: M3 — "a refund made *after* completion moves the order to refunded";
  M4 — "for closed orders, payments − refunds = revenue the reports record". Until you
  decide, `check_invariants` will FAIL M3/M4 on any such order.

## Missing feature

- **H10 — editing an order** (change a quantity, remove a line before shipping) does not
  exist. Today staff must cancel and re-enter the order. If you want it, it needs rules for
  re-pricing (old price or current?), coupons, partial stock release and paid deposits.
