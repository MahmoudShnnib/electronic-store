# STATE — overnight finance & inventory audit

**Phase:** 3 (scenario loop) — **Iteration:** 16/80
**Current scenario:** all 23 listed scenarios done (22 ✅, H10 BLOCKED). Extra checks X1/X2 from FINANCE_MAP hypotheses.
**Doing / next step:** extra checks from FINANCE_MAP hypotheses not yet covered by a scenario:
X1 inventory valuation vs COGS for a variant with no own cost; X2 dashboard "today" (UTC) vs
report day (Africa/Tripoli) for an order at 23:30 UTC. Then write the FINAL REPORT at the top of
MORNING_REVIEW.md and set this file to "AUDIT COMPLETE".

## Scenarios (23): done 22 / remaining 0 / blocked 1 (H10) — bugs: 5 fixed
Happy: H1 buy 1 · H2 buy several · H3 buy last unit · H4 full refund · H5 partial refund ·
H6 refund then re-buy · H7 add stock · H8 add stock w/ pending orders · H9 price change ·
H10 edit order · H11 cancel unpaid · H12 cancel paid · H13 write-off (incl. in a cart)
Failure: F1 payment fails/times out · F2 refund fails at payment · F3 error mid-purchase ·
F4 two buyers last unit · F5 double pay/retry · F6 refund > paid / twice / cancelled ·
F7 write off > stock · F8 delete product w/ orders · F9 invalid inputs

## Key facts
- Branch `audit/finance` (from fix/db-consistency-qa d46c4ee). Never push/merge. Don't commit
  `CLAUDE.md` / `package-lock.json` (user's uncommitted changes) — `git add` explicit paths only.
- Rules: audit/PROMPT.md. Map: FINANCE_MAP.md. Rules: INVARIANTS.md.
- Tests: `./vendor/bin/pest --parallel` (445 green at start, 494 now). Race tests: `pest tests/Concurrency`. Scoped: `--filter`.
  Format: `./vendor/bin/pint --dirty`. Test DB `electronic_store_test` (pgsql 127.0.0.1:5432).
- Checker: `php audit/check_invariants.php` (dev DB from .env) or
  `DB_DATABASE=electronic_store_test php audit/check_invariants.php`. READ ONLY txn.
  In tests: `require_once base_path('audit/invariants.php'); expect(auditFailures())->toBe([]);`
- `.env` is unreadable to the agent — don't try. Every bash call prints a fastfetch banner; ignore.
- Money = integer hundredths; `Money::fromDecimal` / `Money::format`. Revenue rule (dashboard +
  reports): status in_progress/completed/refunded, total − completed refunds.
- No order-edit feature exists (H10 likely BLOCKED). No payment gateway (COD/bank, manual confirm).
- Helpers in tests/Pest.php: `customer()`, `staff('super_admin')`, `defaultWarehouse()`, `giveStock()`.
- Existing harness to copy patterns from: tests/Feature/Qa/DbHarmonyTest.php (`qaCheckout()` payload).
- Payment/refund/checkout code changes → list in MORNING_REVIEW "changes to review first".
- Pest diff output is ANSI-coloured even with NO_COLOR: pipe through `sed -r 's/\x1B\[[0-9;]*[A-Za-z]//g'`.
- Restock/return changes report.valuation.* (units, cost, retail) — remember it in expectations.
- New migration 2026_10_05_100000 (damaged_change) NOT run on dev DB on purpose — user to run it.
