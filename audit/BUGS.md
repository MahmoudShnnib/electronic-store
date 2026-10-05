# Bugs

Status: open / fixed in `<hash>` / blocked (reason).

| # | Scenario | Expected | Actual | Place that didn't acknowledge | Root cause | Status |
|---|---|---|---|---|---|---|
| 1 | H4 full refund / H5 partial / H6 re-buy | Dashboard best sellers agree with the report's product breakdown (a fully returned product is not a best seller) | Refunded orders counted in full: returned phone shown as best seller with 1,000.000 LYD; re-bought mug shown as 2 sold | Dashboard → Best sellers | 3dde664 widened `$revenueStatuses` to include `refunded` for the netted revenue KPIs; the best-sellers query reused the variable but sums gross line totals | fixed in `cb287ee` |
