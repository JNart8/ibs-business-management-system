-- ============================================================
-- PHASE 3D: CUSTOMER-CREDIT CROSS-BRANCH SETTLEMENT
--
-- A customer can deposit money (store credit) at one branch and
-- spend it at another. Customer balances are deliberately shared
-- company-wide (see ARCHITECTURE.md §5.3), so there's no way to
-- trace which specific deposit funded which specific later sale —
-- and no attempt is made to (no FIFO/lot-tracing, see §5.5).
-- Instead: a net-position report (deposits issued vs. credit
-- redeemed, by branch) lets the business see which branch is owed
-- by which, and settlement reuses the existing account_transfers
-- mechanism rather than inventing a new one.
--
-- See ARCHITECTURE.md §5.5 for the full design rationale.
-- ============================================================

-- 1. Let a transfer record who bears its charges, and flag transfers
--    that exist specifically to settle a cross-branch customer-credit
--    imbalance (vs. an ordinary treasury transfer).
ALTER TABLE `account_transfers`
ADD COLUMN `charged_to` ENUM('customer', 'business') NOT NULL DEFAULT 'business' AFTER `charges`,
ADD COLUMN `settlement_type` ENUM('routine', 'customer_credit_balancing') NOT NULL DEFAULT 'routine' AFTER `charged_to`;

-- 2. Attribute each deposit to the branch it was made at, so the new
--    report can group issuance by branch. Nullable + FK SET NULL,
--    mirroring accounts.branch_id's pattern. Existing rows predate
--    branches entirely, so they backfill to Main Branch (1) — same
--    rule used for sales/purchases/stock_movements/expenses in Phase 1.
ALTER TABLE `customer_transactions`
ADD COLUMN `branch_id` INT UNSIGNED NULL DEFAULT 1 AFTER `customer_id`,
ADD CONSTRAINT `fk_customer_transactions_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

UPDATE `customer_transactions` SET `branch_id` = 1 WHERE `branch_id` IS NULL;

SELECT 'Phase 3d migration complete: account_transfers.charged_to/settlement_type added, customer_transactions.branch_id added and backfilled.' AS status;
