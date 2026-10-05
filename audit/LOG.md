# Log (append-only)

- iter 1: phase 0 - map written (FINANCE_MAP.md) - 7 hypotheses
- iter 2: phase 1 - INVARIANTS.md written (24 rules)
- iter 3: phase 2 - check_invariants built; dev DB + test DB: ALL RULES PASS; read-only guard verified (session SET did not block, txn READ ONLY does)
- iter 4: H1-H3 buy 1/several/last - PASS (snapshot+diff harness tests/Qa/FinanceSnapshot.php, checker self-test added)
