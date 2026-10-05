# Log (append-only)

- iter 1: phase 0 - map written (FINANCE_MAP.md) - 7 hypotheses
- iter 2: phase 1 - INVARIANTS.md written (24 rules)
- iter 3: phase 2 - check_invariants built; dev DB + test DB: ALL RULES PASS; read-only guard verified (session SET did not block, txn READ ONLY does)
- iter 4: H1-H3 buy 1/several/last - PASS (snapshot+diff harness tests/Qa/FinanceSnapshot.php, checker self-test added)
- iter 5: H4-H6 refunds - FAIL - dashboard best sellers count refunded orders - BUGS #1
- iter 6: BUGS #1 fixed in cb287ee (dashboard best sellers use in_progress+completed); full suite 460 green; checker PASS
