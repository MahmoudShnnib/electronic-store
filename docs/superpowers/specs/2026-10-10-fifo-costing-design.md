# FIFO stock costing (BD2) — design

Owner rule (2026-10-10): every time stock is bought or added, the price paid for those units
is recorded. Sales use up the oldest units first (FIFO, chosen over "cheapest first"), so profit
uses what the sold units really cost. At the POS register, admin and super_admin see the
highest cost among the units still in that register's warehouse; cashiers never receive it.

## Data

- `stock_batches`: `stock_level_id` (FK, cascade), `unit_cost_cents`, `quantity_received`,
  `quantity_remaining`, `received_at`. CHECK `0 <= remaining <= received`, `received > 0`.
  One row per arrival of stock on one stock row. FIFO order = `received_at, id`.
- `order_items.cost_cents` (exact total cost of the line) and `order_items.cost_layers`
  (jsonb list of `{cost, qty, received_at}` — the slices the line took). `unit_cost_cents`
  stays as `intdiv(cost_cents, quantity)` for display only; nothing multiplies it again.

## The one rule, in `RecordMovement` (under the stock row lock it already takes)

- **Decrease**: take `min(units, max(0, before))` from batches, oldest first. Units no batch
  covers (only possible after drift) become a slice at the default cost. The slices taken are
  exposed on the returned movement (`$movement->costLayers`).
- **Increase**: create batches on the locked row for `max(0, after) - max(0, before)` units —
  from `costLayers` if given (keeping their `received_at`, so restored units sell first), else at
  `unitCostCents`, else at the default cost.
- Default cost = variant cost, or the product cost when the variant has none (`NULLIF(…, 0)`).
- Never branches on movement type; never writes a batch of another stock row.

Callers: PlaceOrder stores the slices on the order line; CancelOrder / ResolveOrderIssue give
back the line's slices; TransferStock passes the out-leg slices to the in-leg (new batches in
the destination); SaveProduct carries the drained slices onto the variants in order;
ReceivePurchaseOrder uses the PO line cost; AdjustStock takes a typed cost (default: product cost).

## Reports

- Profit: `SUM(sign × order_items.cost_cents)` over money events (cost booked with the payment).
- Inventory value (units, cost, retail from the same goods): batch units + units of open orders
  that are **not yet paid** (paid ones are already booked as sold). Cost of the latter = their
  `cost_cents`. One SQL statement.

## Screens

- Stock adjust dialog: required "Unit cost" for increases, prefilled with the product cost
  (server: nullable, falls back to the product cost so scripted callers keep working).
- POS register: `max_cost_formatted` on each item only for admin / super_admin; the key is
  absent for everyone else.
- PO form: a variant with cost 0 prefills the product cost (`||`, not `??`).

## Existing data (migration)

One batch per stock row with quantity > 0 at the default cost; order lines backfilled with
`cost_cents = quantity × COALESCE(unit_cost_cents, NULLIF(variant cost,0), product cost, 0)` and one
slice. Deploy under `php artisan down`, then run `php audit/check_invariants.php`.

## Checks

- S10: per stock row, batch units remaining = `max(0, quantity)`.
- S11: per order line, slices add up to its quantity and to `cost_cents`.
- Harmony checker gets the S10 check; CheckerSelfTest gets S10/S11 corruption cases.

## Known gaps (owner decisions, not built)

- PO shipping/discount are not spread into unit cost (batch cost = PO line price).
- Supplier returns use FIFO, not the batch of the PO being returned.
- Free (0-total) orders: their goods leave stock value but never reach profit cost.
- Write-offs (damaged/lost) leave stock value but have no report line of their own.
