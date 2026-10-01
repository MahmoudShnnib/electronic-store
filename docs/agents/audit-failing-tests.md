# Audit 2026-10-01 — open bugs as failing tests

All 79 bugs found by the audit are fixed on branch `fix/audit-stock-variants`; every test in
`tests/Feature/Audit/` passes. Each test asserts the CORRECT behaviour, so if one turns red
again, that bug is back.

```bash
./vendor/bin/pest tests/Feature/Audit
```

A few audit tests were rewritten when a business decision changed what "correct" means:
STK-5 (shop shows what one warehouse can ship), STK-15 (variants are added one at a time to a
stocked product), STK-4/INV-16 (supplier returns take from damaged only when ticked),
POS-3 (checked on the printed receipt), MON-5 (bank-transfer orders can't complete before the
transfer is confirmed), MON-7 (store credit removed).

More context: Claude memory note `audit-2026-10-01`. Dev DB test data to clean: prefixes
`E2E-*`, `AUDIT*`, coupon `AUDIT10`.
