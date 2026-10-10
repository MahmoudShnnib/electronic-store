# Invariants (Phase 1)

Rules that must ALWAYS hold. Each rule id matches a check in `audit/invariants.php`
(run with `php audit/check_invariants.php`). Do not change a rule to make a test pass —
if a rule looks wrong, note it in MORNING_REVIEW.md.

Terms: *net paid* = Σ confirmed payments − Σ completed refunds. *Open order* = status
`in_progress`. *Revenue statuses* = `in_progress`, `completed`, `refunded` (the rule the
dashboard and sales report already use). Money is integer hundredths.

## Stock

- **S1 Ledger = shelf.** For every (warehouse, product, variant): `stock_levels.quantity`
  = Σ `inventory_movements.quantity_change`. The ledger is a chain: each row's
  `quantity_before` = previous row's `quantity_after`, and `before + change = after`.
  (This is "on hand = received − sold + returned − written off ± adjustments": every one of
  those is a ledger type.)
- **S2 Movement signs.** A movement's sign matches its type: `customer_order`, `lost`,
  `damaged`, `manual_decrease`, `supplier_return`, `transfer_out` ≤ 0;
  `initial`, `purchase_receipt`, `order_cancellation`, `customer_return`,
  `manual_increase`, `transfer_in` ≥ 0; `correction` either way. No row has change 0
  except a supplier return drawn entirely from damaged stock.
- **S3 Never negative.** `quantity`, `reserved`, `damaged`, `incoming` ≥ 0.
- **S4 Reservations.** `reserved` = Σ quantities of open-order lines for that key.
- **S5 Incoming.** `incoming` = Σ (ordered − received) on sent / partially received POs.
- **S6 Every ledger row has a stock row** (no orphan movements).
- **S7 Order stock legs.** Per order and key: Σ `customer_order` = −(units sold);
  a cancelled order also has `order_cancellation` = +(units sold); an open/completed/
  refunded order has none.
- **S8 No double return.** Per order line, units returned (`customer_return` rows for the
  order) ≤ units sold. Returns only exist on completed/refunded orders.

- **S9 Damaged is on the ledger.** For every key, `stock_levels.damaged` = Σ
  `inventory_movements.damaged_change` (added during the audit, BUG 2). Rows written before
  that migration only carry the delta for `damaged` write-offs; older supplier returns from
  damaged can't be reconstructed.

- **S10 FIFO batches = shelf.** For every stock row, Σ `stock_batches.quantity_remaining` =
  max(0, `quantity`). Every unit on the shelf has a known purchase cost (BD2, 2026-10-10).
  Reserved units already left their batches (their cost is on the order line), and so did
  damaged ones when written off.
- **S11 Order line cost slices.** Every order line's `cost_layers` add up to its `quantity`
  and to its `cost_cents` (what the units taken from stock cost, oldest first).

## Orders & prices

- **O1 Line arithmetic.** `line_total = unit_price × quantity − discount` on every line;
  `quantity` ≥ 1; `unit_price` ≥ 0; `line_total` ≥ 0.
- **O2 Order arithmetic.** `subtotal` = Σ unit_price × quantity; `discount` = Σ line
  discounts; `total` = Σ line totals = `subtotal − discount + tax` (no shipping column
  exists); `total` ≥ 0.
- **O3 Price snapshot.** Past orders keep the price they were sold at: no code path writes
  `order_items` price/discount/total columns or `orders` money columns after placement.
  The DB holds no price history per line, so the checker can only verify O1/O2 hold; the
  "price change" scenario test proves old orders and reports are unchanged. (Lines may be
  re-pointed to a variant by the stock-split feature, so `updated_at` is not a usable signal.)
- **O4 Status bookkeeping.** Status history starts from null, has no gaps and ends at the
  current status; `completed_at`/`cancelled_at` set for those statuses.

## Money

- **M1 Refund caps.** Per order: 0 ≤ refunded ≤ paid, 0 ≤ net paid ≤ total.
- **M2 Payment status** equals what the money says (unpaid / partially_paid / paid /
  partially_refunded / refunded; a 0-total completed order is paid).
- **M3 Closed orders are settled.** A completed order with no refunds has net paid = total.
  A refunded order has at least one refund. A cancelled order holds no money (net paid 0).
  A completed order never carries refunds (it must have moved to refunded).
- **M4 Net revenue = money.** For orders in completed/refunded status:
  Σ payments − Σ refunds = Σ (total − refunds), i.e. every closed order was paid exactly
  its total. (Open orders are revenue that is still receivable.)
- **M5 Treasury mirrors payments and refunds.** Each confirmed payment has exactly one
  treasury IN of the same amount on its account (cod/cash → cash, bank_transfer → bank);
  each cash/bank refund exactly one OUT; no treasury leg points at a missing source.
- **M6 Treasury balances** per account ≥ 0.
- **M7 Treasury identity.** Order-linked treasury net (IN − OUT) = Σ confirmed payments −
  Σ cash/bank refunds.

## Audit trail

- **A1 Who and why on stock.** Manual movement types (`initial`, `manual_increase`,
  `manual_decrease`, `damaged`, `correction`, `supplier_return`) carry a reason and a
  user. Every other movement carries a reference (order / PO / transfer).
- **A2 Who on money.** Every payment has `confirmed_by` and `confirmed_at`; every refund
  has `created_by`; every manual treasury leg (no source) has `created_by` and a note.
- **A3 Audit log.** Each payment has a `payment_confirmed` and each refund a
  `refund_created` activity entry on its order; status transitions are logged.
- **A4 Nothing financial hard-deleted.** Every payment/refund/treasury/ledger row still
  points at an existing order/source; orders are only soft-deleted.

## Write-offs

- **W1 Write-offs are not sales or refunds.** `damaged` / `manual_decrease` /
  `supplier_return` movements never reference an order, and no payment, refund or
  treasury leg is created by them.

## Coupons

- **C1** `coupons.used_count` = number of active (not released) redemptions, ≤ `usage_limit`;
  no active redemption is held by a cancelled order (cancelling releases it — kept as history, BD7).

## 🔴 Unclear — Business Decision Required (not enforced, see MORNING_REVIEW.md)

- Revenue is recognised when an order is **placed** (open unpaid orders count). Should it be
  at payment or completion?
- "Stolen / lost" stock has no movement type of its own (only `manual_decrease` + free text).
- A partial refund turns a completed order into `refunded`, after which no further item can
  go through the return form.
- Cancelling an order hard-deletes its coupon redemption row (the order keeps
  `discount_cents` and `coupon_code`).
- Whether damaged units should still count in inventory value (currently they don't).
