# Scenario results

| id | Scenario | Result | Test | Notes |
|---|---|---|---|---|
| H1 | Buy 1 item | ✅ | BuyTest "H1" | stock, ledger, order, admin page, reports, dashboard all move exactly |
| H2 | Buy several items | ✅ | BuyTest "H2" | |
| H3 | Buy the last item in stock | ✅ | BuyTest "H3" | storefront refuses a 2nd cart add; dashboard low→out of stock |
| H4 | Full refund | ✅ (after BUG 1) | RefundTest "H4" | money back, treasury out, restock, revenue/cost/profit net to 0 |
| H5 | Partial refund (one item of several) | ✅ (after BUG 1) | RefundTest "H5" | 🔴 product breakdown drops the whole order once any refund lands (business decision) |
| H6 | Refund then re-buy | ✅ (after BUG 1) | RefundTest "H6" | |
| H7 | Add stock | ✅ | StockTest "H7" | shelf, ledger, valuation only |
| H8 | Add stock to a product with pending orders | ✅ | StockTest "H8" | PO receive; reservation untouched; order completes after. 🔴 BD2 valuation uses product cost, not PO cost |
| H13 | Write off stolen/damaged, incl. one in a cart | ✅ (after BUG 2) | StockTest "H13" ×4 | cart checkout refused cleanly, cart kept; stolen = manual_decrease + reason (🔴 BD3) |
| F7 | Write off more stock than exists | ✅ | StockTest "F7" ×6 | reserved units can't be written off; every refused attempt changes nothing |
| H9 | Change a product's price; old orders & reports unchanged | ✅ | OrderLifecycleTest "H9" | only price/cost + valuation (today's prices) move; open order still collected at sold price; price history written |
| H10 | Edit an order (change qty, remove item) before shipping | BLOCKED | — | No order-editing feature exists (no route/action). Building one is a feature, not a fix → MORNING_REVIEW |
| H11 | Cancel an unpaid order | ✅ (after BUG 3) | OrderLifecycleTest "H11" | |
| H12 | Cancel a paid order | ✅ (after BUGS 3, 4) | OrderLifecycleTest "H12", DepositRefundTest | cancel refused while money held; refund then cancel is clean. ⚠ rules M3/M4 wording conflicts with deposit-refund flow (MORNING_REVIEW) |
| F1 | Payment fails / times out after stock reserved | ✅ | FailureTest "F1" | no gateway: refused amounts change nothing; stale COD flagged after 3 days; cancel releases stock, no revenue |
| F2 | Refund fails at the payment step | ✅ | FailureTest "F2" | treasury can't cover it → no refund, no restock, status stays completed, no half-logged history |
| F3 | App/DB error in the middle of a purchase | ✅ | FailureTest "F3" ×2 | crash on 2nd order line / on treasury insert → nothing saved, cart kept |
| F5 | Double-click "Pay" / retried request | ✅ | FailureTest "F5" ×2 | checkout, record payment, complete, return form: each takes effect once |
| F6 | Refund > paid, twice, on a cancelled order | ✅ | FailureTest "F6" | also refund on unpaid order, negative/zero amounts, store credit |
| F4 | Two customers buy the last unit at the same time | ✅ | tests/Concurrency/RaceTest.php "F4" | real forked processes; one order, the other gets a clean cart error; stable 5/5 runs |
| F5 | (simultaneous half) same checkout / payment / return sent at the same instant | ✅ | RaceTest "F5" ×3 | one order, one payment + treasury line, one refund + restock |
| F8 | Delete a product that has past orders | ✅ | ProductAndInputTest "F8" | only catalog counts move; open order still cancels (stock back), completed one still returns; pages render |
| F9 | Invalid inputs: negative / zero / decimal / huge | ✅ | ProductAndInputTest "F9" ×2 | refused everywhere (cart, manual order, POS, stock dialog, payments); 0 price and 99,999,999 LYD price flow through exactly |
