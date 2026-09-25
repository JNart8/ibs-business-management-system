-- ============================================================
-- Batch & expiry tracking (Phase 4: stock out and transfers).
--
-- Run after 2026_09_25_batch_expiry_selling.sql.
--
-- stock_transfer_item_batches  which batches each transfer line
--     carried (a line can span several), taken at dispatch in-date
--     first, earliest expiry first. On receipt the destination gets
--     batches with the same batch number and expiry date; on
--     cancellation the stock goes back into source_batch_id.
--     Only tracked products' lines get rows.
--
-- Stock Out needs no schema change: the batch written off is
-- recorded on its stock movement (added in the Phase 2 migration).
-- ============================================================

CREATE TABLE IF NOT EXISTS `stock_transfer_item_batches` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transfer_item_id` BIGINT UNSIGNED NOT NULL,
    `source_batch_id`  INT UNSIGNED NULL COMMENT 'Batch at the source branch the stock left',
    `batch_number`     VARCHAR(50) NULL,
    `expiry_date`      DATE NULL,
    `quantity`         DECIMAL(12,3) NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_transfer_item_batches_item` (`transfer_item_id`),
    CONSTRAINT `fk_transfer_item_batches_item` FOREIGN KEY (`transfer_item_id`) REFERENCES `stock_transfer_items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transfer_item_batches_source` FOREIGN KEY (`source_batch_id`) REFERENCES `product_batches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Migration complete: transfers carry batches between branches.' AS status;
