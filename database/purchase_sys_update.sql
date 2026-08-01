-- ============================================================
-- PURCHASE MANAGEMENT SYSTEM - DATABASE MIGRATION
-- ============================================================
-- Run this SQL to add purchase tracking functionality
-- ============================================================

-- 1. ADD FIELDS TO PRODUCTS TABLE
-- ============================================================
ALTER TABLE `products`
ADD COLUMN `average_cost` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Weighted moving average cost' AFTER `cost_price`,
ADD COLUMN `last_purchase_cost` DECIMAL(12,2) DEFAULT NULL COMMENT 'Most recent purchase cost' AFTER `average_cost`,
ADD COLUMN `last_purchase_date` DATETIME DEFAULT NULL COMMENT 'Date of last purchase' AFTER `last_purchase_cost`;

-- Update average_cost with current cost_price for existing products
UPDATE `products` SET `average_cost` = `cost_price` WHERE `average_cost` = 0;

-- 2. ADD FIELDS TO SUPPLIERS TABLE
-- ============================================================
ALTER TABLE `suppliers`
ADD COLUMN `current_balance` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Current credit balance (negative = we owe them)' AFTER `notes`,
ADD COLUMN `credit_limit` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Maximum credit we can take from supplier' AFTER `current_balance`,
ADD COLUMN `total_purchases` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Total lifetime purchases' AFTER `credit_limit`;

-- 3. CREATE PURCHASES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `purchases` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_number` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique purchase identifier (e.g., PUR-20260223-0001)',
  `supplier_id` INT(10) UNSIGNED NOT NULL,
  `purchase_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Sum of all line items before discount',
  `discount_percent` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Overall purchase discount percentage',
  `discount_amount` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Calculated discount amount',
  `vat_percent` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'VAT/Tax percentage',
  `vat_amount` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Calculated VAT amount',
  `total_amount` DECIMAL(12,2) NOT NULL COMMENT 'Final amount to pay (subtotal - discount + VAT)',
  `payment_status` ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
  `payment_method` ENUM('cash', 'mobile', 'bank', 'credit', 'cheque') DEFAULT 'credit',
  `amount_paid` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Amount paid so far',
  `amount_due` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Outstanding amount',
  `invoice_number` VARCHAR(100) DEFAULT NULL COMMENT 'Supplier invoice number',
  `notes` TEXT DEFAULT NULL,
  `user_id` INT(10) UNSIGNED DEFAULT NULL COMMENT 'User who created the purchase',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_number` (`purchase_number`),
  KEY `idx_supplier_id` (`supplier_id`),
  KEY `idx_purchase_date` (`purchase_date`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_purchases_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_purchases_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Records all purchases from suppliers';

-- 4. CREATE PURCHASE ITEMS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `purchase_items` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_id` INT(10) UNSIGNED NOT NULL,
  `product_id` INT(10) UNSIGNED NOT NULL,
  `product_name` VARCHAR(200) NOT NULL COMMENT 'Snapshot of product name at time of purchase',
  `quantity` INT(11) NOT NULL COMMENT 'Quantity purchased',
  `unit_cost` DECIMAL(12,2) NOT NULL COMMENT 'Cost per unit',
  `discount_percent` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Item-level discount',
  `line_total` DECIMAL(12,2) NOT NULL COMMENT 'Total for this line (qty × cost × (1 - discount))',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_id` (`purchase_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `fk_purchase_items_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_purchase_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Line items for each purchase';

-- 5. CREATE SUPPLIER TRANSACTIONS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `supplier_transactions` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_id` INT(10) UNSIGNED NOT NULL,
  `transaction_type` ENUM('purchase', 'payment', 'refund', 'adjustment', 'deposit') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL COMMENT 'Transaction amount (negative for purchases/deductions, positive for payments)',
  `balance_before` DECIMAL(12,2) NOT NULL COMMENT 'Balance before this transaction',
  `balance_after` DECIMAL(12,2) NOT NULL COMMENT 'Balance after this transaction',
  `reference_type` ENUM('purchase', 'payment', 'adjustment') DEFAULT NULL,
  `reference_id` INT(10) UNSIGNED DEFAULT NULL COMMENT 'ID of related record (purchase, payment, etc)',
  `payment_method` ENUM('cash', 'mobile', 'bank', 'cheque', 'credit') DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `user_id` INT(10) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_supplier_id` (`supplier_id`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_reference` (`reference_type`, `reference_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_supplier_transactions_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_supplier_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks all financial transactions with suppliers';

-- 6. CREATE PRODUCT COST HISTORY TABLE (Optional but recommended)
-- ============================================================
CREATE TABLE IF NOT EXISTS `product_cost_history` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT(10) UNSIGNED NOT NULL,
  `purchase_id` INT(10) UNSIGNED DEFAULT NULL,
  `old_cost` DECIMAL(12,2) NOT NULL COMMENT 'Previous average cost',
  `new_cost` DECIMAL(12,2) NOT NULL COMMENT 'New average cost after purchase',
  `unit_cost` DECIMAL(12,2) NOT NULL COMMENT 'Cost from this purchase',
  `quantity` INT(11) NOT NULL COMMENT 'Quantity purchased',
  `change_percent` DECIMAL(8,2) DEFAULT NULL COMMENT 'Percentage change in cost',
  `stock_before` INT(11) DEFAULT NULL COMMENT 'Stock quantity before purchase',
  `stock_after` INT(11) DEFAULT NULL COMMENT 'Stock quantity after purchase',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_purchase_id` (`purchase_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_cost_history_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cost_history_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks cost changes over time for valuation and analysis';

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================

-- Additional indexes for common queries
ALTER TABLE `purchases` 
ADD INDEX `idx_payment_status_date` (`payment_status`, `purchase_date`),
ADD INDEX `idx_supplier_date` (`supplier_id`, `purchase_date`);

ALTER TABLE `purchase_items`
ADD INDEX `idx_product_purchase` (`product_id`, `purchase_id`);

ALTER TABLE `supplier_transactions`
ADD INDEX `idx_supplier_type_date` (`supplier_id`, `transaction_type`, `created_at`);

---indexes on existing tables for faster lookups related to reports
ALTER TABLE products ADD INDEX idx_stock_value (current_stock, average_cost);
ALTER TABLE customers ADD INDEX idx_balance (current_balance, is_default);
ALTER TABLE suppliers ADD INDEX idx_balance (current_balance);
-- ============================================================
-- SUCCESS MESSAGE
-- ============================================================
SELECT 'Purchase Management System tables created successfully!' AS status;
SELECT 'Next steps: Run the PurchaseController.php and update navigation' AS next_action;


-- ============================================================
-- EXPENSES TABLE (for tracking non-purchase expenses)
-- ============================================================
CREATE TABLE IF NOT EXISTS expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    expense_date DATE NOT NULL,
    category VARCHAR(100),
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'mobile', 'bank', 'cheque') DEFAULT 'cash',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (expense_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;