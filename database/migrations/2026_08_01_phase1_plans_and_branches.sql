-- ============================================================
-- PHASE 1 MIGRATION: Plan tiers + branch scaffolding
-- ============================================================
-- Run this once per client database (via phpMyAdmin or `mysql <`).
-- Safe to run on an existing, live database — it only ADDS
-- columns/tables with backward-compatible defaults. No existing
-- data is modified in a way that changes its meaning.
--
-- BEFORE RUNNING: take a quick export/backup of the client's
-- database (phpMyAdmin → Export). Cheap insurance.
-- ============================================================

-- ------------------------------------------------------------
-- 1. Plan tier on the settings row
-- ------------------------------------------------------------
ALTER TABLE `settings`
    ADD COLUMN `plan` ENUM('core', 'growth', 'enterprise') NOT NULL DEFAULT 'core' AFTER `id`;

-- If this install should start on a specific plan, set it explicitly, e.g.:
-- UPDATE `settings` SET `plan` = 'growth' WHERE `id` = 1;

-- ------------------------------------------------------------
-- 2. Branches table (scaffolding for future multi-branch build)
-- ------------------------------------------------------------
-- Every install gets exactly one branch today. This is invisible
-- to Core/Growth clients — there is no branch picker anywhere yet.
-- It exists now so that when multi-branch is eventually built for
-- an Enterprise client, existing sales/purchases/stock history
-- doesn't need to be migrated — it already belongs to this branch.
CREATE TABLE IF NOT EXISTS `branches` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `address` TEXT NULL,
    `phone` VARCHAR(20) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `branches` (`id`, `name`)
SELECT 1, 'Main Branch'
WHERE NOT EXISTS (SELECT 1 FROM `branches` WHERE `id` = 1);

-- ------------------------------------------------------------
-- 3. branch_id scaffolding on transactional tables
-- ------------------------------------------------------------
-- Every historical row is backfilled to branch 1 by the DEFAULT.
-- No functional change today: every query still sees all rows,
-- since there is only ever one branch until Phase 3 is built.

ALTER TABLE `sales`
    ADD COLUMN `branch_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
    ADD INDEX `idx_sales_branch` (`branch_id`),
    ADD CONSTRAINT `fk_sales_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT;

ALTER TABLE `purchases`
    ADD COLUMN `branch_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
    ADD INDEX `idx_purchases_branch` (`branch_id`),
    ADD CONSTRAINT `fk_purchases_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT;

ALTER TABLE `stock_movements`
    ADD COLUMN `branch_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
    ADD INDEX `idx_stock_movements_branch` (`branch_id`),
    ADD CONSTRAINT `fk_stock_movements_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT;

ALTER TABLE `expenses`
    ADD COLUMN `branch_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
    ADD INDEX `idx_expenses_branch` (`branch_id`),
    ADD CONSTRAINT `fk_expenses_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT;

-- NOTE ON SCOPE: `products.current_stock` intentionally stays a single
-- global number in Phase 1. Splitting stock per-branch (a `branch_stock`
-- table replacing `current_stock`) is Phase 3 work, because it requires
-- rewriting how ProductController/StockController/SaleController read
-- and write stock — not just adding a column. Doing it now, before
-- there's a real multi-branch client, would add risk for no benefit.

SELECT 'Phase 1 migration complete: plan tiers + branch scaffolding ready.' AS status;
