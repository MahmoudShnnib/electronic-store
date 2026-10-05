# Bugs

Status: open / fixed in `<hash>` / blocked (reason).

| # | Scenario | Expected | Actual | Place that didn't acknowledge | Root cause | Status |
|---|---|---|---|---|---|---|
| 1 | H4 full refund / H5 partial / H6 re-buy | Dashboard best sellers agree with the report's product breakdown (a fully returned product is not a best seller) | Refunded orders counted in full: returned phone shown as best seller with 1,000.000 LYD; re-bought mug shown as 2 sold | Dashboard → Best sellers | 3dde664 widened `$revenueStatuses` to include `refunded` for the netted revenue KPIs; the best-sellers query reused the variable but sums gross line totals | fixed in `cb287ee` |
| 2 | H13 write-off → supplier return from damaged | The ledger records how many damaged units left (who/when/why/how many) | Movement row has quantity_change 0 and nothing else; the damaged count changed with no record of the amount | inventory_movements (audit trail) vs stock_levels.damaged | Ledger only had columns for sellable-quantity changes; `damaged` was mutated silently by RecordMovement | fixed in `cf1059e` (new column `damaged_change` + migration + invariant S9) |
