-- ============================================================
-- PHASE 3c MIGRATION: inter-branch stock transfers
-- ============================================================
-- One-step (instant) transfers — both branches' stock update the
-- moment a transfer is recorded, no dispatch/receive workflow. See
-- ARCHITECTURE.md for why (chosen over two-step for simplicity).
--
-- stock_transfers / stock_transfer_items give a proper browsable
-- transfer history ("Transfer #TRF-0001: 3 products, Branch A →
-- Branch B, by John, on Aug 10"), separate from the per-product
-- stock_movements ledger, which still gets two entries per item
-- (out at the source, in at the destination) tagged with the new
-- 'transfer' reference_type, linked back to the transfer via
-- reference_id.
-- ============================================================

ALTER TABLE `stock_movements`
    MODIFY COLUMN `reference_type` ENUM('purchase', 'sale', 'return', 'adjustment', 'opening', 'transfer') NOT NULL;

CREATE TABLE IF NOT EXISTS `stock_transfers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `transfer_number` VARCHAR(30) NOT NULL UNIQUE,
    `from_branch_id` INT UNSIGNED NOT NULL,
    `to_branch_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_transfers_from` (`from_branch_id`),
    INDEX `idx_transfers_to` (`to_branch_id`),
    INDEX `idx_transfers_created` (`created_at`),
    CONSTRAINT `fk_transfers_from_branch` FOREIGN KEY (`from_branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_transfers_to_branch` FOREIGN KEY (`to_branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_transfers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_transfer_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `transfer_id` BIGINT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` DECIMAL(12,3) NOT NULL,
    INDEX `idx_transfer_items_transfer` (`transfer_id`),
    INDEX `idx_transfer_items_product` (`product_id`),
    CONSTRAINT `fk_transfer_items_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transfer_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`key`, `label`, `category`) VALUES
    ('stock.transfer', 'Transfer Stock Between Branches', 'Inventory')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

-- Admin-only by default, deliberately NOT granted to Staff automatically
-- (unlike stock.manage) — per your decision, this should default to
-- more restricted than general stock management, with Enterprise
-- custom roles used to grant it selectively to trusted staff.
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_key`) VALUES
    (1, 'stock.transfer');

SELECT 'Phase 3c migration complete: stock transfers ready.' AS status;
