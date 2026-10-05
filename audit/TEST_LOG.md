# Scenario results

| id | Scenario | Result | Test | Notes |
|---|---|---|---|---|
| H1 | Buy 1 item | ✅ | BuyTest "H1" | stock, ledger, order, admin page, reports, dashboard all move exactly |
| H2 | Buy several items | ✅ | BuyTest "H2" | |
| H3 | Buy the last item in stock | ✅ | BuyTest "H3" | storefront refuses a 2nd cart add; dashboard low→out of stock |
| H4 | Full refund | ✅ (after BUG 1) | RefundTest "H4" | money back, treasury out, restock, revenue/cost/profit net to 0 |
| H5 | Partial refund (one item of several) | ✅ (after BUG 1) | RefundTest "H5" | 🔴 product breakdown drops the whole order once any refund lands (business decision) |
| H6 | Refund then re-buy | ✅ (after BUG 1) | RefundTest "H6" | |
