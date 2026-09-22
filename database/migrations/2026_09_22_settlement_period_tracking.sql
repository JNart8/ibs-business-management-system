-- ============================================================
-- Customer-credit settlement: track WHICH REPORTING PERIOD a
-- settlement transfer pays off.
--
-- Gap: the settlement report (§5.5, Phase 3d) shows a net position
-- for a chosen date range, and "Record Settlement" already creates an
-- account_transfers row with settlement_type = 'customer_credit_balancing'
-- — but nothing recorded which period that transfer was FOR. Re-running
-- the report for the same period after settling had no way to net the
-- settlement back out, so the same imbalance kept showing as owed
-- (real double-pay risk) — and there was no way to build a "who owes
-- whom" branch-pair breakdown that accounts for what's already moved.
--
-- Fix: two nullable DATE columns recording the period a settlement
-- transfer covers. NULL for every ordinary ('routine') transfer —
-- this only ever gets populated for settlement_type =
-- 'customer_credit_balancing'. Matched on exact period bounds
-- (dateFrom/dateTo as the report computed them), not overlap — a
-- settlement always names the specific period it was recorded against
-- via the report's "Record Settlement" link, so exact-match is
-- unambiguous and avoids a settlement silently bleeding into an
-- adjacent period it wasn't actually for.
-- ============================================================

ALTER TABLE `account_transfers`
ADD COLUMN `settles_period_from` DATE NULL AFTER `settlement_type`,
ADD COLUMN `settles_period_to`   DATE NULL AFTER `settles_period_from`;

SELECT 'Migration complete: account_transfers.settles_period_from/settles_period_to added.' AS status;
