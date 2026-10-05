# Morning review

(Final report goes at the top when the audit completes.)

## Questions / notes for you

- The overnight prompt and the project CLAUDE.md disagree: CLAUDE.md says Payments/Checkout/Returns need your OK first, the prompt says never wait. I followed the prompt for audit bug fixes only (DECISIONS D2) and list every such change below so you can review them first.

## 🔴 Business Decision Required

- **BD1 — Product/category/brand reports after a partial refund.** The sales summary nets a
  refund per order (revenue 1,100 − 50 refunded = 1,050), but the per-product, per-category
  and per-brand breakdowns drop any order with a refund entirely (`ReportService::revenueItems`),
  so a phone the customer kept vanishes from "sales by product" because one cable came back.
  The code's own comment says per-product lines "can't be netted against an order-level
  refund". Options: (a) keep as is; (b) include refunded orders and subtract returned *units*
  (from the return ledger) while attributing the refunded money to the returned lines;
  (c) proportional split of the refund across lines. Needs your call — a refund amount isn't
  always tied to a returned item (e.g. 50 LYD goodwill for a scratch). Pinned by
  RefundTest "H5". The dashboard's best-sellers list now follows the same rule (BUG 1).
