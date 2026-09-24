-- ============================================================
-- Customer deposits: re-tag branch_id from the receiving account.
--
-- Bug: customer_transactions.branch_id for a deposit was taken from
-- the RECORDING user's active branch (activeBranchId()), not from where
-- the money actually landed. A company-wide admin whose home branch is
-- Main recording a deposit into Kasoa's cash account tagged it Main —
-- so the customer credit settlement report showed Main as the branch
-- that "issued" the credit, no matter where the customer deposited.
--
-- Code now uses depositBranchId($account) — the receiving account's
-- branch. This backfills existing deposit rows the same way, for both
-- deposit paths:
--   1. Direct deposits (CustomerController::processDeposit) — linked
--      via account_transactions (reference_type 'customer_deposit').
--   2. Resolved suspense deposits (SuspenseController) — linked via
--      suspense_transactions.resolved_account_id.
-- Only rows whose account is branch-owned (branch_id NOT NULL) are
-- touched; deposits into company-wide accounts (shared bank/MoMo) carry
-- no location, so their existing tag is left as-is.
--
-- Note: any customer-credit settlement already recorded for a period
-- containing a re-tagged deposit was computed from the old numbers —
-- re-run the report for that period afterwards and review.
-- ============================================================

UPDATE `customer_transactions` ct
JOIN `account_transactions` at
    ON at.reference_type = 'customer_deposit' AND at.reference_id = ct.id
JOIN `accounts` a ON a.id = at.account_id
SET ct.branch_id = a.branch_id
WHERE ct.transaction_type = 'deposit'
  AND a.branch_id IS NOT NULL
  AND NOT (ct.branch_id <=> a.branch_id);

UPDATE `customer_transactions` ct
JOIN `suspense_transactions` st
    ON ct.reference_type = 'manual' AND ct.reference_id = st.id
JOIN `accounts` a ON a.id = st.resolved_account_id
SET ct.branch_id = a.branch_id
WHERE ct.transaction_type = 'deposit'
  AND a.branch_id IS NOT NULL
  AND NOT (ct.branch_id <=> a.branch_id);

SELECT 'Migration complete: customer deposit branch_id re-tagged from receiving account.' AS status;
