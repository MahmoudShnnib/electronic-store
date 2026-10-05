# STATE — overnight finance & inventory audit

**Phase:** 3 (scenario loop) — **Iteration:** 4/80
**Current scenario:** H4 Full refund (next)
**Doing / next step:** write `tests/Feature/FinanceAudit/RefundTest.php` (H4 full refund, H5
partial refund, H6 refund then re-buy) using finSetUp/finBuy/finSnapshot/finExpectChanges.
Harness: tests/Qa/FinanceSnapshot.php (snapshot of every place + exact diff assert + checker),
tests/Feature/FinanceAudit/FinanceAuditSetup.php (fixtures PHONE×10 100.00, CABLE×50 5.00, MUG×1 2.00).

## Scenarios (23): done 3 (H1-H3) / remaining 20 / blocked 0
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
- Tests: `./vendor/bin/pest --parallel` (445 green at start, ~36 s). Scoped: `--filter`.
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
