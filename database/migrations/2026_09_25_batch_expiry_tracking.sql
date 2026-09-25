-- ============================================================
-- Batch & expiry-date tracking (Phase 1: foundation).
--
-- Opt-in at two levels, so clients who don't sell expiring goods
-- see no change at all:
--   settings.track_expiry   company-wide switch (Settings page);
--                           off by default
--   products.track_expiry   per product, once the company switch
--                           is on (a pharmacy's thermometers don't
--                           expire; its antibiotics do)
--
-- product_batches holds a tracked product's stock at a branch split
-- by batch: batch number, expiry date, quantity. For every tracked
-- product, a branch's batch quantities always add up to its
-- branch_stock quantity — adjustBranchStock()/setBranchStock() in
-- functions.php keep the two in step, the same way they already keep
-- products.current_stock in step with branch_stock. branch_stock stays
-- the source of truth for "how much is here", so nothing that reads it
-- changes.
--
-- expiry_date NULL = "unknown expiry": stock that was on the shelf
-- before the product was tracked, or received without a date. It is
-- treated as the oldest stock (used up first). Emptied batches are
-- kept at quantity 0 rather than deleted, so later phases can point
-- sale lines at the batch they came from.
--
-- No existing data changes: every product starts untracked and gets
-- its batches only when someone switches tracking on for it.
-- ============================================================

ALTER TABLE `settings`
ADD COLUMN `track_expiry` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Batch & expiry tracking available (company-wide switch)',
ADD COLUMN `expiry_warning_days` SMALLINT UNSIGNED NOT NULL DEFAULT 90 COMMENT 'Batches expiring within this many days count as expiring soon';

ALTER TABLE `products`
ADD COLUMN `track_expiry` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Stock is tracked by batch and expiry date' AFTER `unit`;

CREATE TABLE IF NOT EXISTS `product_batches` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`   INT UNSIGNED NOT NULL,
    `branch_id`    INT UNSIGNED NOT NULL,
    `batch_number` VARCHAR(50) NULL COMMENT 'Supplier batch/lot number; optional',
    `expiry_date`  DATE NULL COMMENT 'NULL = unknown expiry (pre-tracking stock)',
    `quantity`     DECIMAL(12,3) NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_batches_product_branch` (`product_id`, `branch_id`, `expiry_date`),
    INDEX `idx_batches_branch_expiry` (`branch_id`, `expiry_date`),
    CONSTRAINT `fk_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_batches_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Migration complete: batch & expiry tracking ready (off until switched on in Settings).' AS status;
