-- ============================================================
-- Batch & expiry tracking (Phase 3: selling).
--
-- Run after 2026_09_25_batch_expiry_receiving.sql.
--
-- settings.expired_sales  what the POS does with expired stock of a
--                         tracked product:
--                           'block' (default) — never sold; only
--                                   in-date stock counts as available
--                           'warn'  — sold only once the in-date stock
--                                   has run out, with a warning
--
-- sale_item_batches  which batches each sale line took its stock from
--                    (a line can span several), so a voided sale puts
--                    stock back into the same batches, and a sale can
--                    be traced to the batches the customer received.
--                    Only tracked products' lines get rows.
-- ============================================================

ALTER TABLE `settings`
ADD COLUMN `expired_sales` ENUM('block', 'warn') NOT NULL DEFAULT 'block' COMMENT 'Selling expired batches: block, or allow with a warning';

CREATE TABLE IF NOT EXISTS `sale_item_batches` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_item_id` BIGINT UNSIGNED NOT NULL,
    `batch_id`     INT UNSIGNED NOT NULL,
    `quantity`     DECIMAL(12,3) NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_sale_item_batches_item` (`sale_item_id`),
    INDEX `idx_sale_item_batches_batch` (`batch_id`),
    CONSTRAINT `fk_sale_item_batches_item` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sale_item_batches_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Migration complete: sales take stock by batch; expired stock is blocked at the POS by default.' AS status;
