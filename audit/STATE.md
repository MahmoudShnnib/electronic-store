# STATE — overnight finance & inventory audit

**AUDIT COMPLETE** (2026-10-05). Iterations used: 19/80.

Read `audit/MORNING_REVIEW.md` first: the final report is at the top.

## Result
- 23 listed scenarios: 22 ✅, 1 BLOCKED (H10 edit order: no feature). Extra checks X1, X2 ✅.
- 7 bugs found, 7 fixed (BUGS.md has commit hashes). 8 business decisions open (BD1–BD8) + rules M3/M4 wording.
- Suite 497 green (`./vendor/bin/pest --parallel`); `php audit/check_invariants.php` ALL RULES PASS.

## Key facts (if anyone continues)
- Branch `audit/finance`, not pushed. Don't commit the user's `CLAUDE.md` / `package-lock.json` changes.
- Checker: `php audit/check_invariants.php` (dev DB) / `DB_DATABASE=electronic_store_test php audit/check_invariants.php`.
  Read-only via a READ ONLY transaction. Rules in audit/INVARIANTS.md, code in audit/invariants.php.
- Scenario tests: tests/Feature/FinanceAudit/*, race tests: tests/Concurrency (needs pcntl/posix).
- Migration 2026_10_05_100000 run on dev DB (2026-10-05, user request); still needed on prod. Pending: decide BD1–BD8.
