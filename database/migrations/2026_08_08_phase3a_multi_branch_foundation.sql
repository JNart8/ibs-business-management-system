-- ============================================================
-- PHASE 3a MIGRATION: multi-branch foundation
-- ============================================================
-- Builds on the branches table + branch_id scaffolding from Phase 1.
-- This migration adds:
--   1. An install-level add-on flag for multi-branch, independent of
--      plan tier (every Enterprise client gets it; a Growth client
--      can also be sold it as a paid add-on).
--   2. A `branches.manage` permission (admin only, by default).
--   3. `user_branches` — a user can be assigned to more than one
--      branch. Each user's `users.branch_id` becomes their *currently
--      active* branch (what POS/sales use, with no picker shown) —
--      resolved once at login or changed via a small branch switcher,
--      not asked on every sale.
--
-- Backward compatible: every existing active user is assigned to
-- Main Branch (id 1) as both their only branch and their active one,
-- matching how they already implicitly work today. Nothing changes
-- for any install until multi-branch is actually turned on.
-- ============================================================

-- ------------------------------------------------------------
-- 1. Multi-branch add-on flag (independent of plan tier)
-- ------------------------------------------------------------
ALTER TABLE `settings`
    ADD COLUMN `addon_multi_branch` TINYINT(1) NOT NULL DEFAULT 0 AFTER `plan`;

-- Enterprise clients get it automatically — see hasMultiBranch() in
-- app/helpers/functions.php, which checks `plan = 'enterprise' OR
-- addon_multi_branch = 1`, so no need to also set this flag for them.
-- Use this flag only for a Growth client buying multi-branch as an add-on:
--   UPDATE settings SET addon_multi_branch = 1 WHERE id = 1;

-- ------------------------------------------------------------
-- 2. branches.manage permission
-- ------------------------------------------------------------
INSERT INTO `permissions` (`key`, `label`, `category`) VALUES
    ('branches.manage', 'Manage Branches', 'Administration')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_key`) VALUES
    (1, 'branches.manage');

-- ------------------------------------------------------------
-- 3. user_branches pivot table
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_branches` (
    `user_id`   INT UNSIGNED NOT NULL,
    `branch_id` INT UNSIGNED NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`user_id`, `branch_id`),
    CONSTRAINT `fk_user_branches_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_branches_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every existing active user is assigned to Main Branch as their one
-- (and therefore primary) branch, matching how they already work today.
INSERT IGNORE INTO `user_branches` (`user_id`, `branch_id`, `is_primary`)
SELECT `id`, 1, 1 FROM `users` WHERE `is_active` = 1;

-- Set each of those users' active branch to Main Branch too, so POS
-- and sales have something to read immediately (harmless — it's the
-- only branch that exists until more are created).
UPDATE `users` SET `branch_id` = 1 WHERE `branch_id` IS NULL;

SELECT 'Phase 3a migration complete: multi-branch foundation ready.' AS status;
