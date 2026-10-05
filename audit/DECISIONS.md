# Decisions made without the user

One line each: what, and why.

- D1 Branched `audit/finance` from `fix/db-consistency-qa` (d46c4ee), not `main`: it holds the latest money fixes and the QA harness (PR #2); auditing `main` would rediscover fixed bugs.
- D2 Treated the overnight prompt as the user's explicit approval to change code in the CLAUDE.md "confirm first" zones (Payments/Checkout/Returns) **only for audit bug fixes**; every such change is listed separately in MORNING_REVIEW.md for review first. Middleware/auth/roles stay untouched.
- D3 Left the pre-existing uncommitted changes (`CLAUDE.md` audit pointer, `package-lock.json`) out of every audit commit — they are the user's, not mine.
- D4 `check_invariants` is `php audit/check_invariants.php` (Laravel bootstrap + one READ ONLY transaction, rolled back) with the rules in `audit/invariants.php`, so tests and the CLI share one implementation. Not an artisan command: keeps audit tooling out of `app/`.
- D5 Session-level `SET SESSION CHARACTERISTICS ... READ ONLY` did not block writes on this Postgres 18 setup (verified); the checker uses `BEGIN; SET TRANSACTION READ ONLY` instead, which does (verified: UPDATE → SQLSTATE 25006).
- D6 Scenario tests live in `tests/Feature/FinanceAudit/` and drive the real HTTP routes (storefront + admin) wherever a route exists; product price edits use the `SaveProduct` service because the product form needs a large payload unrelated to price.
- D7 Time is frozen at 12:00 UTC in scenario tests so the dashboard (UTC days) and reports (Africa/Tripoli days) cover the same day; the timezone mismatch itself is logged as a bug candidate, not hidden.
- D8 H10 (edit order) marked BLOCKED: the app has no way to edit a placed order; adding one is a new feature with business rules (re-pricing, coupons, stock), not an audit fix.
- D9 Fixed the checker's payment-status mapping (M2): net paid = total is "paid" even after a refunded deposit — matches the app (`ConfirmPayment`) and the rule's own wording ("what the money says"). The rule text itself was not changed.
- D10 Rules M3 ("a completed order never carries refunds") and M4 ("every closed order was paid exactly its total") are wrong for a deposit refunded before completion. Per the hard rules I did NOT change them; DepositRefundTest asserts the checker reports exactly M3+M4 for that order, and MORNING_REVIEW proposes new wording.
- D11 BUG 4 fix leaves the cancelled-order figure in "sales by status" as it was (total − refunds) to keep the change minimal.
- D12 Added a `Concurrency` test suite (phpunit.xml + tests/Pest.php, DatabaseTruncation) for F4: real races need committed data, which RefreshDatabase's wrapping transaction hides. Needs the PHP `pcntl` and `posix` extensions (present locally; check CI).
