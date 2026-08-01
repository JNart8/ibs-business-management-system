-- ============================================================
-- FINANCIAL ACCOUNTS SYSTEM - DATABASE MIGRATION
-- ============================================================

-- 1. CREATE ACCOUNTS TABLE
CREATE TABLE IF NOT EXISTS `accounts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('cash', 'mobile_money', 'bank') NOT NULL,
  `provider` VARCHAR(50) NULL COMMENT 'e.g., MTN, Telecel, AirtelTigo, or Bank Name',
  `account_number` VARCHAR(50) NULL,
  `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `is_default` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_active_type` (`is_active`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. SEED DEFAULT ACCOUNTS
INSERT INTO `accounts` (`name`, `type`, `provider`, `account_number`, `balance`, `is_default`) VALUES
('Cash Account', 'cash', 'Cash', NULL, 5000.00, 1);

-- 3. CREATE ACCOUNT TRANSFERS TABLE
CREATE TABLE IF NOT EXISTS `account_transfers` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `from_account_id` INT UNSIGNED NOT NULL,
  `to_account_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `charges` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `user_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`from_account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`to_account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. CREATE ACCOUNT TRANSACTIONS TABLE
CREATE TABLE IF NOT EXISTS `account_transactions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `account_id` INT UNSIGNED NOT NULL,
  `transaction_type` ENUM('deposit', 'withdrawal', 'transfer_out', 'transfer_in', 'charge') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `balance_before` DECIMAL(12,2) NOT NULL,
  `balance_after` DECIMAL(12,2) NOT NULL,
  `reference_type` ENUM('sale', 'purchase', 'expense', 'transfer', 'charge', 'manual', 'customer_deposit') NOT NULL,
  `reference_id` BIGINT UNSIGNED NULL,
  `notes` TEXT NULL,
  `user_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_account_created` (`account_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. SEED INITIAL TRANSACTIONS FOR DEFAULT BALANCES
INSERT INTO `account_transactions` (`account_id`, `transaction_type`, `amount`, `balance_before`, `balance_after`, `reference_type`, `notes`, `user_id`) VALUES
(1, 'deposit', 5000.00, 0.00, 5000.00, 'manual', 'Opening balance', 1),
(2, 'deposit', 2500.00, 0.00, 2500.00, 'manual', 'Opening balance', 1),
(3, 'deposit', 1000.00, 0.00, 1000.00, 'manual', 'Opening balance', 1),
(4, 'deposit', 800.00, 0.00, 800.00, 'manual', 'Opening balance', 1),
(5, 'deposit', 15000.00, 0.00, 15000.00, 'manual', 'Opening balance', 1);

-- 6. ALTER EXPENSES TABLE TO LINK TO ACCOUNTS
ALTER TABLE `expenses`
ADD COLUMN `account_id` INT UNSIGNED NULL AFTER `amount`,
ADD COLUMN `charges` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `account_id`,
ADD CONSTRAINT `fk_expenses_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL;

-- 7. UPDATE EXISTING EXPENSES TO DEFAULT CASH ACCOUNT FOR CONSISTENCY
UPDATE `expenses` SET `account_id` = 1 WHERE `account_id` IS NULL;
