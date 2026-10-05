# Morning review

(Final report goes at the top when the audit completes.)

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
