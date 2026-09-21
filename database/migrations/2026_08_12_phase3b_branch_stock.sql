-- ============================================================
-- PHASE 3b MIGRATION: branch_stock (per-branch stock quantities)
-- ============================================================
-- branch_stock becomes the source of truth for "how much of this
-- product is at this branch". products.current_stock stays in the
-- schema as an auto-maintained TOTAL across all branches — kept in
-- sync by application code (adjustBranchStock()/setBranchStock() in
-- functions.php), not a DB trigger, to stay consistent with the rest
-- of this app (nothing here has ever used triggers or stored procs).
-- Anywhere that only needs "how much do we have, company-wide" can
-- keep reading products.current_stock unchanged.
--
-- IMPORTANT — read before running on a client database that already
-- has more than one branch in use: this migration seeds branch_stock
-- with each product's *current total* assigned entirely to Main
-- Branch (id 1), and zero everywhere else. If that client has
-- already been operating a second branch since Phase 3a shipped,
-- this will NOT reflect reality — stock was never tracked
-- per-branch before now, so there is no historical data to recover
-- it from. That client will need a manual stock count per branch
-- after this migration runs, to correct branch_stock to actual
-- quantities. This is a one-time, unavoidable gap — flag it to them
-- before running this.
-- ============================================================

CREATE TABLE IF NOT EXISTS `branch_stock` (
    `product_id` INT UNSIGNED NOT NULL,
    `branch_id`  INT UNSIGNED NOT NULL,
    `quantity`   DECIMAL(12,3) NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`product_id`, `branch_id`),
    INDEX `idx_branch_stock_branch` (`branch_id`),
    CONSTRAINT `fk_branch_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_branch_stock_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: every existing product's full current_stock assigned to Main
-- Branch (id 1). Every other branch (if any already exist) starts at
-- zero — see the warning above.
INSERT IGNORE INTO `branch_stock` (`product_id`, `branch_id`, `quantity`)
SELECT `id`, 1, `current_stock` FROM `products`;

-- Every other existing branch gets an explicit zero row per product,
-- rather than being left absent — makes "no stock recorded yet" and
-- "genuinely zero" the same state, simplifying every query that reads
-- branch_stock (no need to handle a missing row as a special case).
INSERT IGNORE INTO `branch_stock` (`product_id`, `branch_id`, `quantity`)
SELECT p.id, b.id, 0
FROM `products` p
CROSS JOIN `branches` b
WHERE b.id != 1;

SELECT 'Phase 3b migration complete: branch_stock ready.' AS status;
