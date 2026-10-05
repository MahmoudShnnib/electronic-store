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
