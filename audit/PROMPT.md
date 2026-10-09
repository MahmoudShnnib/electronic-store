# OVERNIGHT FINANCE & INVENTORY AUDIT

## 1. OVERNIGHT MODE: READ FIRST, OVERRIDES EVERYTHING ELSE
I'm asleep and can't answer anything until morning. So:
- Never stop to wait for my approval and never ask me questions. Write anything you'd have asked me into /audit/MORNING_REVIEW.md and keep going.
- When something is unclear, choose the safest option that doesn't change business behavior, record the choice and reason in /audit/DECISIONS.md, and continue.
- If the correct accounting or business treatment of something is unclear (e.g. how a write-off should be recorded, whether a returned item goes back to sellable stock), do NOT invent an answer: mark it 🔴 Business Decision Required in MORNING_REVIEW.md, do not implement a guess, and move to the next scenario.
- Keep working until a stop condition in section 7 is met.

## 2. HARD RULES: PROTECT THE PROJECT'S FUTURE
- All work on branch `audit/finance`. Never commit to main, never merge, never push, never deploy.
- Commit after every single fix, with a clear message, so each change can be reverted on its own.
- Run ONLY against the local/test database. Never touch production data.
- Never use real payment credentials or live payment modes. Sandbox/mock only.
- Schema changes ONLY through proper migration files in the repo, never by editing the DB by hand.
- Never put secrets, API keys, or passwords in code or commits.
- Small, focused fixes only. Don't rewrite or "clean up" code unrelated to the bug you're fixing.
- Don't delete files, tests, or features. If something seems useless, note it in MORNING_REVIEW.md.
- Never delete or weaken a test to make it pass. Never change a rule in INVARIANTS.md just to make a test pass; if a rule seems wrong, note it in MORNING_REVIEW.md.
- No new packages unless truly required; list any you add in DECISIONS.md.

## 3. MEMORY RULES: THE FILES ARE YOUR MEMORY, NOT THIS CHAT
Assume your chat context can be compressed or lost at any moment. Everything you need to continue must be in files under /audit at the repo root:
- STATE.md: the ONE file that says where you are. Keep it SHORT (under ~60 lines) and OVERWRITE it, never keep appending. Always contains:
  - Current phase and current scenario
  - What you were doing and the exact next step
  - Scenarios done / remaining / blocked (counts + names)
  - Iteration count (x/80)
  - Key facts you'd hate to rediscover: how to run the app, tests, and check_invariants; test DB details; known quirks of this codebase
- FINANCE_MAP.md: map of the system (Phase 0).
- INVARIANTS.md: the rules (Phase 1).
- BUGS.md: every bug with status (open / fixed in commit <hash> / blocked).
- DECISIONS.md: every choice you made on your own, one-line reason each.
- LOG.md: append-only, one line per step, e.g. "iter 23: partial refund - FAIL - stock not returned - BUGS #7". History; STATE.md is the present.
- TEST_LOG.md: each scenario with ✅ / ❌ / BLOCKED.
- MORNING_REVIEW.md: everything I need to look at.

Rules:
- At the START of every iteration: re-read STATE.md first. Trust the files over your memory of the chat.
- At the END of every iteration: update STATE.md, add a line to LOG.md, commit (code + audit files together).
- Before any big step (large fix, new phase): update STATE.md first.
- If you're ever unsure what you were doing: read STATE.md, then the last 20 lines of LOG.md, then BUGS.md, and continue from there.
- Write everything so a brand-new session with zero chat history could continue from the files alone.

## 4. GOAL
Make sure every action that touches money or stock (buy, refund, add stock, edit, write off stolen/damaged items) is reflected correctly in EVERY place in the system that depends on it, and leaves the system clean if it fails halfway. Find and fix the places where one side updates and the others don't.

## 5. SETUP PHASES (do once each, then continue without stopping)

### Phase 0: Map the system → FINANCE_MAP.md
No code changes in this phase. Document:
- The stack, database, and how to run the app and tests locally.
- Every place money or stock is stored or calculated: tables/columns, cached totals, counters, dashboards, reports, invoices, emails, admin panels, product pages.
- For each action (buy, full refund, partial refund, add stock, edit product/price, edit order, cancel order, write off stolen/damaged, dropship order if supported): which places it SHOULD update, and which it ACTUALLY updates in the code.
- Any place updated by one action but ignored by another. These are the most likely bugs; list them at the top.

### Phase 1: Define the rules → INVARIANTS.md
Rules that must ALWAYS be true. Start with these and add any the code suggests:
- Stock on hand = stock received − sold + returned to stock − written off (± manual adjustments with a reason).
- Stock can never go negative.
- Order total = sum of line items + shipping − discounts.
- Total paid − total refunded = net revenue recorded.
- A refund can never exceed what was paid for that order, and the same item can't be refunded twice.
- Past orders keep the price they were sold at, even if the product price changes later.
- Every change to stock or money has a record of what, when, who, and why. Nothing financial is ever hard-deleted.
- Stolen/damaged write-offs reduce stock and inventory value, but are NOT sales and NOT refunds.
Flag anything unclear as 🔴 Business Decision Required.

### Phase 2: Build the checker
Write a script `check_invariants` that reads the database and verifies every rule in INVARIANTS.md, printing PASS/FAIL per rule with the exact records that break it. It must be READ-ONLY. Document how to run it in STATE.md.

## 6. THE LOOP
For each scenario below, in order:
1. Re-read STATE.md.
2. Write an automated test that:
   a. Snapshots every relevant place from FINANCE_MAP.md (stock, order status, payments, refunds, revenue, reports, etc.).
   b. Performs the action through the real application flow (API/service), not by editing the database directly.
   c. Snapshots again and asserts the EXACT expected change in EVERY place, AND that unrelated places did NOT change.
   d. Runs `check_invariants`.
3. Run it. If it passes: mark ✅ in TEST_LOG.md, update STATE.md + LOG.md, commit, next scenario.
4. If it fails: record in BUGS.md (scenario, expected, actual, which place didn't acknowledge the change, root cause).
5. Fix ONE bug at a time with the smallest change that fixes the root cause.
6. Re-run the FULL test suite and `check_invariants`, not just the new test.
7. If all green: mark bug fixed with commit hash, update STATE.md + LOG.md, commit, continue.
8. If still failing after 3 fix attempts on the same bug: mark it BLOCKED in BUGS.md with the reason, revert any half-done change, and move to the next scenario.

### Scenarios: happy paths
- Buy 1 item / several items / the last item in stock.
- Full refund, partial refund (one item of several), refund then re-buy.
- Add stock; add stock to a product with pending orders.
- Change a product's price, then confirm old orders and reports are unchanged.
- Edit an order (change quantity, remove an item) before shipping.
- Cancel an unpaid order and a paid order.
- Write off stolen/damaged items, including one that's in someone's cart.

### Scenarios: failures (these matter most)
- Payment fails or times out after stock was reserved → stock released, no revenue recorded.
- Refund fails at the payment step → order NOT shown as refunded, stock NOT returned.
- App/DB error in the middle of a purchase → nothing half-saved anywhere.
- Two customers buy the last unit at the same time → only one succeeds.
- Double-click "Pay" or retried request → charged and deducted only once.
- Refund more than was paid, refund twice, refund a cancelled order → rejected cleanly.
- Write off more stock than exists → rejected.
- Delete a product that has past orders → orders and reports stay intact.
- Invalid inputs: negative quantity, zero price, huge numbers, decimal quantities.

## 7. STOP CONDITIONS
Stop only when one of these is true:
- Every scenario is ✅ or BLOCKED, OR
- 80 iterations total.

## 8. FINAL REPORT
At the TOP of MORNING_REVIEW.md write:
- Summary table of all scenarios and their result.
- Every bug found: fixed (with commit hash) or blocked (with reason).
- Every 🔴 Business Decision Required item.
- Every change you made to payment, refund, or pricing logic, listed separately, so I can review those first.
- Your top 3 recommendations for making the finance logic more robust.
Then update STATE.md to say "AUDIT COMPLETE".
