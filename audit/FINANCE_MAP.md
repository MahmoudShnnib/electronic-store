# Finance & Inventory Map (Phase 0)

Written 2026-10-05 on branch `audit/finance` (forked from `fix/db-consistency-qa` @ d46c4ee).

## ⚠️ Most likely bugs (places one action updates and another ignores)

Each is a hypothesis from reading the code; the scenario loop confirms or clears it.

1. **"Outstanding" ignores refunds in two places, but collection uses net.**
   `ConfirmPayment` and `UpdateOrderStatus::settleOutstanding` treat the amount owed as
   `total − (payments − refunds)`. The admin order page (`OrderQueryService::showProps`,
   `outstanding_formatted = total − gross payments`) and the Invoices report
   (`ReportService::invoices/invoiceSummary`, `outstanding = total − gross payments`)
   ignore refunds. After a deposit is refunded on an open order, the screens say less is
   owed than the app will actually collect on completion.
2. **Inventory valuation uses a different cost than COGS.** `ReportService::inventoryValuation`
   uses `COALESCE(variant.cost_price_cents, product.cost_price_cents)`. Variants saved
   without their own cost get `0` (SaveProduct / ProductRequest line ~258), so valuation
   values them at 0, while checkout snapshots (`PlaceOrder`, `?:`) and the sales report
   (`NULLIF(variant cost, 0)`) fall back to the product cost.
3. **Dashboard "today" / chart days are UTC; reports use the shop timezone**
   (`config('store.timezone')`, Africa/Tripoli, UTC+2). Orders placed 00:00–02:00 local
   land on different days on the dashboard and the sales report.
4. **Dashboard best sellers count units of refunded orders in full** (returned units are
   not subtracted), while the report's product breakdown leaves refunded orders out. Two
   "top products" views disagree after a return.
5. **A partial refund moves a completed order to `refunded`, which blocks any further
   return** (`ResolveOrderIssue` requires `completed`). The second item of a multi-item
   order can never be returned through the return form afterwards (refund-only is still
   possible via the refund form, with no restock). Business-rule question, not a data bug.
6. **No order editing exists.** There is no route/action to change quantities or remove a
   line from a placed order. The "edit order" scenario cannot be exercised.
7. **Write-off ("stolen") has no dedicated type.** Damaged = `quantity −n, damaged +n`
   (units still counted as on premises but unsellable); "stolen/lost" can only be booked
   as `manual_decrease` with a free-text reason. Inventory valuation excludes `damaged`.

## Stack & running

- Laravel 12 + Inertia + Vue 3/TS. PostgreSQL. Money = integer **hundredths** in `*_cents`
  bigint columns (`App\Support\Money`, LYD displayed with 3 decimals — third digit is padding).
- Dev DB from `.env` (unreadable to the agent). Test DB: `electronic_store_test` on
  127.0.0.1:5432 (phpunit.xml). Tests use `RefreshDatabase` + `RolesAndPermissionsSeeder`.
- Tests: `./vendor/bin/pest --parallel` (445 green at audit start, ~36 s).
- Format: `./vendor/bin/pint --dirty`. Frontend: `npm run lint`, `vue-tsc --noEmit`.
- Existing cross-table checker: `tests/Qa/Harmony.php` (`qaHarmony()`), used by
  `tests/Feature/Qa/DbHarmonyTest.php`. The audit checker builds on it.

## Where money and stock live

### Stock
| Place | What | Written by |
|---|---|---|
| `stock_levels.quantity` | available-to-sell per (warehouse, product, variant) | only `RecordMovement` |
| `stock_levels.reserved` | units sold on open (`in_progress`) orders, still on premises | `RecordMovement` (place/cancel), `UpdateOrderStatus::releaseReservation` (complete — **no ledger row**, by design) |
| `stock_levels.damaged` | written-off-as-damaged units still on premises | `AdjustStock` (damaged, supplier return from damaged) |
| `stock_levels.incoming` | units on sent POs not yet received | PO send/receive/cancel via `RecordMovement` |
| `inventory_movements` | immutable ledger of `quantity` changes (before/change/after, type, reference, reason, user) | `RecordMovement` |
| `products.status` | `out_of_stock` auto-reverts to `active` on restock | `RecordMovement` |
| storefront availability | max single-warehouse `quantity` over sellable rows | `AddToCart::assertQuantityAvailable`, product cards/pages |
| reports: inventory valuation | `(quantity+reserved) × cost`, `× price` | read-only (`ReportService::inventoryValuation`) |
| reports/dashboard: low/out of stock | from `stock_levels.quantity` | read-only |

### Money
| Place | What | Written by |
|---|---|---|
| `orders.subtotal/discount/total_cents` | snapshot at placement (coupon + manual discount in `discount_cents`) | `PlaceOrder` only (never edited later) |
| `order_items.unit_price/discount/line_total/unit_cost_cents` | per-line snapshot | `PlaceOrder` |
| `orders.payment_status` | roll-up of payments − refunds | `ConfirmPayment`, `CreateRefund`, `settleOutstanding` (0-total) |
| `payments` (status `confirmed`) | money received | `ConfirmPayment` (admin button, or auto on Completed) |
| `refunds` (status `completed`) | money given back | `CreateRefund` (refund form, return form via `ResolveOrderIssue`) |
| `treasury_transactions` | cash/bank ledger; balance = Σ in − Σ out; one leg per payment/refund (unique source) | `RecordTreasuryMovement` (payments, refunds, shift variance, manual deposits/withdrawals) |
| `cashier_shifts.expected/counted/difference_cents` | drawer reconciliation, frozen at close | `CloseShift` |
| `coupons.used_count` + `coupon_redemptions` | coupon usage | `PlaceOrder` (+), `CancelOrder` (− and **hard-deletes** the redemption) |
| `price_histories` | every price/sale/cost change | `SaveProduct` |
| `activity_log` | audit trail (payment_confirmed, refund_created, order_status_changed, order_cancelled, treasury_movement, model diffs) | actions + LogsActivity models |
| Dashboard KPIs | revenue today/month = Σ(total − refunds) over in_progress+completed+refunded, UTC days | read-only |
| Sales report | same revenue rule, store-timezone days; cost from `unit_cost_cents` minus returned units; refunds by refund date | read-only |
| Invoices report | invoiced = total (non-cancelled), paid = **gross** payments | read-only |
| Admin order page | paid (gross), refunded, outstanding = total − gross | read-only |
| Treasury page | balances from ledger | read-only |
| Emails / invoice / receipt / slip | order + line snapshots | read-only |

## Actions: what each SHOULD vs DOES update

| Action | Should update | Actually updates (code) | Gaps |
|---|---|---|---|
| **Buy** (web/API `PlaceOrder`, manual `CreateManualOrder`, POS `CompleteSale`) | stock qty↓ reserved↑, ledger, order+items snapshot, status history, coupon use, cart cleared; no payment yet (COD/bank); POS: also completes + payment + treasury cash/bank | all of these, in one transaction with locked stock rows, cart row lock, coupon row lock, idempotency key | revenue counted at placement (in_progress counts) — consistent across dashboard/report |
| **Payment confirmed** (`ConfirmPayment`) | payment row, treasury IN, payment_status, audit | yes; capped at total − net paid; rejected on cancelled/refunded | none seen |
| **Complete** (`UpdateOrderStatus`) | reserved↓ (no ledger), collect outstanding as payment (COD/POS), status/history/audit | yes; bank transfer must be confirmed first | reservation release has no ledger row (by design) |
| **Full refund** (refund form `CreateRefund` / return form `ResolveOrderIssue`) | refund row, treasury OUT, payment_status, status → refunded (if completed), restock returned units (return form only), audit | yes; refund ≤ paid − refunded; treasury balance must cover it; all in one tx | refund form never restocks (by design: money-only) |
| **Partial refund** | same, partial; per-item restock limited to units still out | yes | order moves to `refunded` → further returns blocked (hypothesis 5) |
| **Add stock** (`AdjustStock` initial/manual_increase/correction, PO receive) | qty↑, ledger with reason+user, OOS→active | yes | — |
| **Edit product/price** (`SaveProduct`) | product/variant prices, price history; past orders unchanged (snapshots) | yes | — |
| **Edit order** | n/a | **no feature** | hypothesis 6 |
| **Cancel** (`CancelOrder`) | qty↑ reserved↓ ledger, coupon use released, status/history/audit; refused while money held | yes | redemption row hard-deleted |
| **Write-off damaged** (`AdjustStock` damaged) | qty↓ damaged↑ ledger with reason; not a sale/refund | yes | no "stolen" type (hypothesis 7) |
| **Supplier return** | qty↓ (or damaged↓ when from damaged) | yes | movement with change 0 when fully from damaged |
| **Delete product** | soft delete; orders/ledger/reports keep snapshots | soft delete; relations use `withTrashed()` | stock of trashed products stays in valuation |
| **Dropship** | n/a | not supported | — |
| **Payment gateway** | n/a | no gateway; `PaymentGatewayManager` drivers are manual labels | "payment fails/times out" = order stays unpaid until cancelled |
