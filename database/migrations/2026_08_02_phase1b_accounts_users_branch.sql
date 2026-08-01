-- ============================================================
-- PHASE 1b MIGRATION: branch scaffolding for accounts + users
-- ============================================================
-- Extends the Phase 1 migration (2026_08_01_phase1_plans_and_branches.sql)
-- with the two remaining tables needed before Phase 3 (multi-branch)
-- can be designed in detail: accounts and users.
--
-- Same rules as before: additive only, backward-compatible defaults,
-- no existing behavior changes. Take a backup before running.
-- Requires the Phase 1 migration (branches table) to have run first.
-- ============================================================

-- ------------------------------------------------------------
-- 1. accounts.branch_id
-- ------------------------------------------------------------
-- Defaults every existing account to branch 1 (Main Branch), since
-- today there is physically only one location. NULLable on purpose:
-- once a second branch exists, an admin can set specific accounts
-- (e.g. the main company bank account) back to NULL to mark them
-- "shared / company-wide" rather than owned by one branch — see
-- the recommendation in PHASE1_CHANGES.md / architecture docs for
-- why cash accounts should be per-branch but bank accounts can be
-- shared.
ALTER TABLE `accounts`
    ADD COLUMN `branch_id` INT UNSIGNED NULL DEFAULT 1 AFTER `id`,
    ADD INDEX `idx_accounts_branch` (`branch_id`),
    ADD CONSTRAINT `fk_accounts_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

-- ------------------------------------------------------------
-- 2. users.branch_id
-- ------------------------------------------------------------
-- Left NULL by default for every existing user (rather than
-- defaulting to branch 1) because this column has no behavior
-- attached to it yet — nothing reads it until Phase 3 ships a
-- branch-aware login/POS flow. NULL will mean "not tied to one
-- branch" (the natural default for admins); staff/cashiers get
-- assigned a specific branch_id once that flow exists.
ALTER TABLE `users`
    ADD COLUMN `branch_id` INT UNSIGNED NULL DEFAULT NULL AFTER `role`,
    ADD INDEX `idx_users_branch` (`branch_id`),
    ADD CONSTRAINT `fk_users_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

SELECT 'Phase 1b migration complete: accounts + users branch scaffolding ready.' AS status;
