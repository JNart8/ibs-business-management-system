-- ============================================================
-- SUSPENSE ACCOUNT SYSTEM - DATABASE MIGRATION
-- ============================================================

-- 1. Add is_suspense column to accounts table
ALTER TABLE `accounts` ADD COLUMN `is_suspense` TINYINT(1) DEFAULT 0 AFTER `is_default`;

-- 2. Seed Default Suspense Account
INSERT INTO `accounts` (`name`, `type`, `provider`, `account_number`, `balance`, `is_default`, `is_suspense`, `is_active`)
VALUES ('Default Suspense Account', 'bank', 'Suspense', 'SUSPENSE-001', 0.00, 0, 1, 1);

-- 3. Create suspense_transactions table
CREATE TABLE IF NOT EXISTS `suspense_transactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `amount` DECIMAL(12,2) NOT NULL,
  `reference_no` VARCHAR(100) NULL COMMENT 'e.g. Bank Ref, MoMo Transaction ID',
  `source_notes` TEXT NULL COMMENT 'Initial notes about the unknown deposit',
  `is_resolved` TINYINT(1) NOT NULL DEFAULT 0,
  `resolved_customer_id` INT UNSIGNED NULL,
  `resolved_account_id` INT UNSIGNED NULL COMMENT 'The respective bank/momo account it is moved to',
  `resolved_at` TIMESTAMP NULL DEFAULT NULL,
  `resolved_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`resolved_customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`resolved_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
