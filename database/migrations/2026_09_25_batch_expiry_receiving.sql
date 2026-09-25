-- ============================================================
-- Batch & expiry tracking (Phase 2: receiving stock).
--
-- Run after 2026_09_25_batch_expiry_tracking.sql.
--
-- Stock received for a tracked product now names its batch:
--   purchase_items   which batch each purchase line delivered, so
--                    editing or voiding the purchase moves stock in
--                    and out of that same batch
--   stock_movements  the batch behind each stock-in (purchase, Stock
--                    In, opening stock, import), for the product's
--                    stock history
-- Both are NULL for untracked products and for everything recorded
-- before this migration.
-- ============================================================

ALTER TABLE `purchase_items`
ADD COLUMN `batch_number` VARCHAR(50) NULL COMMENT 'Batch/lot received (tracked products)' AFTER `line_total`,
ADD COLUMN `expiry_date` DATE NULL COMMENT 'Expiry of the batch received (tracked products)' AFTER `batch_number`;

ALTER TABLE `stock_movements`
ADD COLUMN `batch_number` VARCHAR(50) NULL COMMENT 'Batch/lot received (tracked products)' AFTER `notes`,
ADD COLUMN `expiry_date` DATE NULL COMMENT 'Expiry of the batch received (tracked products)' AFTER `batch_number`;

SELECT 'Migration complete: purchases and stock movements record batch number and expiry date.' AS status;
