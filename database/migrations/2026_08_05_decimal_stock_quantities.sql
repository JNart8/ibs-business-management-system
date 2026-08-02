-- ============================================================
-- MIGRATION: decimal-capable stock & quantity columns
-- ============================================================
-- Converts every INT column that represents a stock quantity or
-- line-item quantity to DECIMAL(12,3) — three decimal places is
-- enough for gram-level precision on a kg-sold product (0.125 kg)
-- without being excessive. Existing whole-number values convert
-- losslessly (5 becomes 5.000); nothing currently stored changes
-- in meaning.
--
-- Why this was needed: products sold by weight/volume (e.g. "0.5 kg")
-- couldn't be recorded at all — every quantity field only accepted
-- whole numbers. Products sold by count (pcs, box, dozen) are
-- unaffected; decimals are now merely *allowed*, not required.
--
-- Safe to run against a live database — this only widens existing
-- columns, it doesn't drop or rename anything. Take a backup first,
-- same as every other migration.
-- ============================================================

ALTER TABLE `products`
    MODIFY COLUMN `current_stock` DECIMAL(12,3) NOT NULL DEFAULT 0,
    MODIFY COLUMN `reorder_level` DECIMAL(12,3) NOT NULL DEFAULT 10;

ALTER TABLE `stock_movements`
    MODIFY COLUMN `quantity`       DECIMAL(12,3) NOT NULL,
    MODIFY COLUMN `previous_stock` DECIMAL(12,3) NOT NULL,
    MODIFY COLUMN `new_stock`      DECIMAL(12,3) NOT NULL;

ALTER TABLE `sale_items`
    MODIFY COLUMN `quantity` DECIMAL(12,3) NOT NULL;

ALTER TABLE `purchase_items`
    MODIFY COLUMN `quantity` DECIMAL(12,3) NOT NULL COMMENT 'Quantity purchased';

ALTER TABLE `product_cost_history`
    MODIFY COLUMN `quantity`     DECIMAL(12,3) NOT NULL COMMENT 'Quantity purchased',
    MODIFY COLUMN `stock_before` DECIMAL(12,3) DEFAULT NULL COMMENT 'Stock quantity before purchase',
    MODIFY COLUMN `stock_after`  DECIMAL(12,3) DEFAULT NULL COMMENT 'Stock quantity after purchase';

SELECT 'Migration complete: stock/quantity columns now support up to 3 decimal places.' AS status;
