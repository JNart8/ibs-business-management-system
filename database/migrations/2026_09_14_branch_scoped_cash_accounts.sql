-- ============================================================
-- MIGRATION: branch-scoped cash accounts
-- ============================================================
-- ARCHITECTURE.md §5.4 decided cash accounts should be branch-scoped
-- ("a cash account represents a physical drawer in a physical
-- location... two branches can't share one cash balance any more
-- than two physical tills can share one drawer of banknotes"), with
-- each new branch auto-getting its own cash account. §6r later found
-- that auto-creation was never actually built — every branch has
-- shared one global Cash Account this whole time.
--
-- This migration:
--   1. Collapses every non-cash account (mobile money, bank, suspense)
--      to branch_id = NULL (company-wide). This is a starting default,
--      not a final decision — today every branch can already see/use
--      these accounts with no scoping at all, so company-wide preserves
--      that exact behavior on upgrade rather than silently cutting a
--      branch off from an account it currently has. Reassign any of
--      them to a specific branch afterward via Financial Accounts →
--      Edit, which now supports a branch picker for mobile_money/bank.
--   2. Backfills a dedicated cash account for every existing branch
--      that doesn't already have one (covers Kasoa Branch today).
--      Idempotent — safe to run again.
--
-- Going forward, BranchController::storeBranch() auto-creates a new
-- branch's cash account at creation time (no migration needed for
-- future branches).
-- ============================================================

UPDATE `accounts` SET `branch_id` = NULL WHERE `type` != 'cash';

INSERT INTO `accounts` (`branch_id`, `name`, `type`, `balance`, `is_default`, `is_active`)
SELECT b.id, CONCAT(b.name, ' Cash Account'), 'cash', 0.00, 1, 1
FROM `branches` b
WHERE b.is_active = 1
  AND b.id NOT IN (SELECT branch_id FROM `accounts` WHERE type = 'cash' AND branch_id IS NOT NULL);

SELECT 'Branch-scoped cash accounts migration complete.' AS status;
