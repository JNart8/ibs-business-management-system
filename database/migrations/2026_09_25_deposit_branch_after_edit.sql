-- ============================================================
-- Customer deposits: re-tag branch_id after the account was edited.
--
-- Bug: editing a deposit (CustomerController::updateCustomerDeposit)
-- moved the money to the newly chosen account but left
-- customer_transactions.branch_id on the ORIGINAL account's branch. A
-- deposit keyed into BRANCH 1's cash account by mistake and then edited
-- to TEST BRANCH 1's kept counting as a BRANCH 1 deposit in the
-- customer credit settlement report, while TEST BRANCH 1 — which now
-- holds the cash — showed no deposits at all.
--
-- Code now re-derives the branch from the new account on edit. This
-- repairs rows edited before that fix: same backfill as
-- 2026_09_24_deposit_branch_from_account.sql (an edit always re-links
-- the deposit via reference_type 'customer_deposit'), safe to re-run.
-- Only branch-owned accounts (branch_id NOT NULL) are touched.
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

SELECT 'Migration complete: edited customer deposits re-tagged to their current account''s branch.' AS status;
