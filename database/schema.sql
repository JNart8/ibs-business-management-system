-- ============================================
-- OPTIMIZED MYSQL SCHEMA FOR SPEED
-- ============================================

-- Users table (employees/admins)
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    last_login TIMESTAMP NULL DEFAULT NULL,
    role ENUM('admin', 'staff', 'cashier') DEFAULT 'cashier',
    is_active TINYINT(1) DEFAULT 1,
    login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_last_login (last_login),
    INDEX idx_active_role (is_active, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customers table with credit management
CREATE TABLE customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    credit_limit DECIMAL(12,2) DEFAULT 0.00,
    current_balance DECIMAL(12,2) DEFAULT 0.00, -- negative = owes money, positive = has credit
    total_purchases DECIMAL(14,2) DEFAULT 0.00,
    is_default TINYINT(1) DEFAULT 0, -- for walk-in customer
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (customer_code),
    INDEX idx_phone (phone),
    INDEX idx_active (is_active),
    INDEX idx_default (is_default),
    INDEX idx_balance (current_balance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories for products
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suppliers table
CREATE TABLE suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_code VARCHAR(20) UNIQUE NOT NULL,
    company_name VARCHAR(150) NOT NULL,
    contact_name VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    phone_alt VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    city VARCHAR(100),
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (supplier_code),
    INDEX idx_phone (phone),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products table with inventory
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) UNIQUE NOT NULL,
    barcode VARCHAR(100) UNIQUE,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT UNSIGNED,
    supplier_id INT UNSIGNED NULL,
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(12,2) NOT NULL,
    current_stock INT DEFAULT 0,
    reorder_level INT DEFAULT 10,
    unit VARCHAR(20) DEFAULT 'pcs',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sku (sku),
    INDEX idx_barcode (barcode),
    INDEX idx_name (name(50)), -- prefix index for faster searches
    INDEX idx_category (category_id),
    INDEX idx_supplier (supplier_id),
    INDEX idx_active_stock (is_active, current_stock),
    INDEX idx_low_stock (current_stock, reorder_level),
    FULLTEXT idx_search (name, description),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock movements (in/out logging)
CREATE TABLE stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    reference_type ENUM('purchase', 'sale', 'return', 'adjustment', 'opening') NOT NULL,
    reference_id BIGINT UNSIGNED, -- links to sales/purchases
    supplier_id INT UNSIGNED NULL,
    previous_stock INT NOT NULL,
    new_stock INT NOT NULL,
    notes VARCHAR(255),
    user_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_date (product_id, created_at),
    INDEX idx_reference (reference_type, reference_id),
    INDEX idx_created (created_at),
    INDEX idx_sm_supplier (supplier_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sales table
CREATE TABLE sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_number VARCHAR(30) UNIQUE NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    discount_type ENUM('percentage', 'flat') NOT NULL DEFAULT 'percentage',
    discount_percent DECIMAL(5,2) DEFAULT 0.00,
    discount_amount DECIMAL(12,2) DEFAULT 0.00,
    tax_amount DECIMAL(12,2) DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL,
    payment_status ENUM('paid', 'partial', 'unpaid') DEFAULT 'unpaid',
    payment_method ENUM('cash', 'credit', 'mobile', 'bank', 'deposit') DEFAULT 'cash',
    amount_paid DECIMAL(12,2) DEFAULT 0.00,
    amount_due DECIMAL(12,2) DEFAULT 0.00,
    notes TEXT,
    user_id INT UNSIGNED,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sale_number (sale_number),
    INDEX idx_customer_date (customer_id, sale_date),
    INDEX idx_payment_status (payment_status),
    INDEX idx_sale_date (sale_date),
    INDEX idx_user (user_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sale items (line items)
CREATE TABLE sale_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(200) NOT NULL, -- denormalized for history
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    discount_type ENUM('percentage', 'flat') NOT NULL DEFAULT 'percentage',
    discount_percent DECIMAL(5,2) DEFAULT 0.00,
    discount_amount DECIMAL(12,2) DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL,
    INDEX idx_sale (sale_id),
    INDEX idx_product (product_id),
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer transactions (payments/deposits/credit adjustments)
CREATE TABLE customer_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    transaction_type ENUM('deposit', 'payment', 'sale', 'refund', 'adjustment') NOT NULL,
    amount DECIMAL(12,2) NOT NULL, -- positive = customer pays/deposits, negative = purchase
    balance_before DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    reference_type VARCHAR(20), -- 'sale', 'manual', etc.
    reference_id BIGINT UNSIGNED, -- sale_id or null
    payment_method ENUM('cash', 'mobile', 'bank', 'credit', 'deposit') DEFAULT 'cash',
    notes VARCHAR(255),
    user_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_date (customer_id, created_at),
    INDEX idx_reference (reference_type, reference_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE customer_transactions 
MODIFY COLUMN payment_method ENUM('cash', 'mobile', 'bank', 'credit', 'deposit') NOT NULL DEFAULT 'cash';

-- System settings (single row config)
CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_name VARCHAR(100),
    store_address TEXT,
    store_phone VARCHAR(20),
    currency VARCHAR(10) DEFAULT 'GHS',
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    sale_discount_type ENUM('percentage', 'flat') NOT NULL DEFAULT 'percentage',
    low_stock_alert TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE sales 
ADD COLUMN item_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 
AFTER amount_due;

UPDATE sales s
SET item_count = (
    SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id
);

-- When a sale item is added, increment the counter
CREATE TRIGGER trg_sale_items_after_insert
AFTER INSERT ON sale_items
FOR EACH ROW
    UPDATE sales SET item_count = item_count + 1 WHERE id = NEW.sale_id;

-- When a sale item is deleted, decrement the counter
CREATE TRIGGER trg_sale_items_after_delete
AFTER DELETE ON sale_items
FOR EACH ROW
    UPDATE sales SET item_count = item_count - 1 WHERE id = OLD.sale_id;


-- ============================================================
-- ADD updated_at COLUMN TO SALES TABLE
-- ============================================================

-- This adds proper timestamp tracking to the sales table
-- Best practice: Every table should have created_at and updated_at

ALTER TABLE sales 
ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL 
AFTER created_at;

-- ============================================================
-- POPULATE updated_at FOR EXISTING RECORDS
-- ============================================================

-- Set updated_at = created_at for all existing sales
-- This ensures historical data has sensible values
UPDATE sales 
SET updated_at = created_at 
WHERE updated_at IS NULL;

-- ============================================================
-- OPTIONAL: ADD TRIGGER FOR AUTO-UPDATE
-- ============================================================

-- This trigger automatically updates updated_at whenever a sale is modified
-- Uncomment if you want automatic timestamp updates


DELIMITER $$

CREATE TRIGGER sales_before_update
BEFORE UPDATE ON sales
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$

DELIMITER ;


-- File: create_audit_history_table.sql

CREATE TABLE IF NOT EXISTS audit_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    action VARCHAR(20) NOT NULL,
    changes TEXT NULL,
    reason VARCHAR(500) NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- INSERT DEFAULT DATA
-- ============================================

-- Insert default walk-in customer
INSERT INTO customers (customer_code, full_name, is_default, credit_limit) 
VALUES ('WALK-IN-001', 'Walk-in Customer', 1, 0.00);

-- Insert default admin user (password: password - CHANGE THIS!)
INSERT INTO users (username, password_hash, full_name, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- Insert default settings
INSERT INTO settings (store_name, currency, tax_rate) 
VALUES ('My Store', 'GHS', 0.00);


-- ============================================================
-- AUDIT HISTORY TABLE
-- ============================================================

-- This table tracks all edits to important records (sales, payments, etc.)
-- Provides complete audit trail for compliance and debugging

CREATE TABLE IF NOT EXISTS audit_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    table_name VARCHAR(50) NOT NULL COMMENT 'Which table was modified (e.g., sales, customers)',
    record_id INT NOT NULL COMMENT 'ID of the record that was modified',
    action VARCHAR(20) NOT NULL COMMENT 'create, update, delete',
    changes TEXT NULL COMMENT 'What changed (formatted text)',
    reason VARCHAR(500) NULL COMMENT 'Why the change was made (user-provided)',
    user_id INT NULL COMMENT 'Who made the change',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Audit trail for record changes';

-- ============================================================
-- SAMPLE DATA (for testing)
-- ============================================================

-- This shows what entries will look like
/*
INSERT INTO audit_history (table_name, record_id, action, changes, reason, user_id)
VALUES 
    ('sales', 123, 'update', 'Amount paid: GHS 50.00 → GHS 75.00; Payment method: cash → mobile', 'Customer made additional payment via mobile money', 1),
    ('sales', 124, 'update', 'Amount paid: GHS 0.00 → GHS 100.00', 'Payment recorded incorrectly, correcting now', 1),
    ('customers', 45, 'update', 'Credit limit: GHS 500.00 → GHS 1000.00', 'Customer requested limit increase, approved by manager', 2);
*/

-- ============================================================
-- VERIFICATION
-- ============================================================

-- Check table was created
SHOW CREATE TABLE audit_history;

-- Verify structure
DESCRIBE audit_history;

-- ============================================================
-- USEFUL QUERIES
-- ============================================================

-- Get audit trail for a specific sale
SELECT 
    ah.*,
    u.full_name as editor_name
FROM audit_history ah
LEFT JOIN users u ON ah.user_id = u.id
WHERE ah.table_name = 'sales' 
  AND ah.record_id = 123
ORDER BY ah.created_at DESC;

-- Get recent edits by a specific user
SELECT 
    ah.*,
    CONCAT(ah.table_name, ' #', ah.record_id) as record
FROM audit_history ah
WHERE ah.user_id = 1
ORDER BY ah.created_at DESC
LIMIT 20;

-- Get all edits today
SELECT 
    ah.*,
    u.full_name as editor_name,
    CONCAT(ah.table_name, ' #', ah.record_id) as record
FROM audit_history ah
LEFT JOIN users u ON ah.user_id = u.id
WHERE DATE(ah.created_at) = CURDATE()
ORDER BY ah.created_at DESC;

-- Count edits by table
SELECT 
    table_name,
    COUNT(*) as total_edits,
    COUNT(DISTINCT record_id) as unique_records,
    COUNT(DISTINCT user_id) as unique_editors
FROM audit_history
GROUP BY table_name
ORDER BY total_edits DESC;

-- ============================================================
-- OPTIONAL: AUTO-CLEANUP OLD ENTRIES
-- ============================================================

-- Keep only last 6 months of audit history (optional)
/*
DELETE FROM audit_history 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
*/

-- Or create an event to auto-cleanup monthly
/*
DELIMITER $$

CREATE EVENT cleanup_old_audit_history
ON SCHEDULE EVERY 1 MONTH
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    DELETE FROM audit_history 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
END$$

DELIMITER ;
*/


-- Optional: Insert sample suppliers
INSERT INTO suppliers (supplier_code, company_name, contact_name, phone, email, city) VALUES
    ('SUP-2024-0001', 'Accra Foods Ltd',       'Kofi Mensah',   '0244000001', 'kofi@accrafoods.com',    'Accra'),
    ('SUP-2024-0002', 'GH Electronics',        'Ama Owusu',     '0244000002', 'ama@ghelectronics.com',  'Kumasi'),
    ('SUP-2024-0003', 'Northern Beverages Co', 'Yaw Asante',    '0244000003', 'yaw@northernbev.com',    'Tamale');





    -- ============================================
-- TEST DATA FOR INITIAL TESTING
-- ============================================

-- Insert additional users
INSERT INTO users (username, password_hash, full_name, role, is_active, login_attempts) VALUES
('john.doe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 'staff', 1, 0),
('jane.smith', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', 'cashier', 1, 0),
('mike.johnson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike Johnson', 'cashier', 1, 0),
('sarah.wilson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Wilson', 'staff', 1, 0),
('david.brown', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'David Brown', 'cashier', 0, 3); -- inactive user

-- Insert additional customers
INSERT INTO customers (customer_code, full_name, phone, email, address, credit_limit, current_balance, total_purchases, is_active) VALUES
('CUST-2024-001', 'Emmanuel Asare', '0244111222', 'emasare@gmail.com', 'House No. 15, Spintex Road, Accra', 5000.00, -2500.00, 12500.00, 1),
('CUST-2024-002', 'Abena Owusu', '0277222333', 'aowusu@yahoo.com', 'Plot 7, Asokwa, Kumasi', 3000.00, -750.00, 8750.00, 1),
('CUST-2024-003', 'Kwame Boateng', '0203334444', 'kboateng@hotmail.com', '22 Liberty Avenue, Tema', 2000.00, 500.00, 4500.00, 1),
('CUST-2024-004', 'Akosua Mensah', '0244555666', 'amensah@gmail.com', 'Block C, Dansoman Estate, Accra', 1000.00, -1000.00, 3200.00, 1),
('CUST-2024-005', 'Yaw Asante', '0544666777', 'yasante@company.com', '15 Independence Avenue, Takoradi', 4000.00, -3200.00, 8900.00, 1),
('CUST-2024-006', 'Esi Amankwah', '0266777888', 'eamankwah@gmail.com', 'House No. 8, Adum, Kumasi', 1500.00, 250.00, 2750.00, 1),
('CUST-2024-007', 'Kojo Eshun', '0244888999', 'keshun@business.com', '45 Ring Road, Accra', 6000.00, -4500.00, 15600.00, 1),
('CUST-2024-008', 'Adwoa Serwaa', '0277999000', 'aserwaa@gmail.com', 'Plot 12, Techiman', 800.00, -300.00, 1800.00, 0), -- inactive customer
('CUST-2024-009', 'Kofi Annan', '0205000111', 'kannan@email.com', '23 Beach Road, Cape Coast', 2500.00, 1000.00, 5200.00, 1),
('CUST-2024-010', 'Ama Serwaa', '0546111222', 'aserwaa2@gmail.com', '7 Hillside, Sunyani', 1200.00, -800.00, 3400.00, 1);

-- Insert categories
INSERT INTO categories (name, description, is_active) VALUES
('Beverages', 'All drinkable products including soft drinks, juices, water, and energy drinks', 1),
('Food Staples', 'Essential food items like rice, flour, sugar, oil, and canned goods', 1),
('Snacks & Confectionery', 'Chips, chocolates, candies, biscuits, and other snack items', 1),
('Household Supplies', 'Cleaning products, detergents, paper products, and home essentials', 1),
('Personal Care', 'Toiletries, cosmetics, skincare, and hygiene products', 1),
('Electronics', 'Small electronics, accessories, batteries, and cables', 1),
('Fresh Produce', 'Fruits, vegetables, and perishable items', 1),
('Bakery', 'Bread, pastries, cakes, and baked goods', 1),
('Frozen Foods', 'Frozen meat, fish, vegetables, and ice cream', 1),
('Baby Products', 'Diapers, baby food, formula, and baby care items', 0); -- inactive category

-- Insert additional suppliers
INSERT INTO suppliers (supplier_code, company_name, contact_name, phone, phone_alt, email, address, city, notes, is_active) VALUES
('SUP-2024-0004', 'Tema Food Complex', 'Esi Dogbey', '0244111222', '0277111222', 'esi@temafood.com', 'Industrial Area, Tema', 'Tema', 'Main food supplier for northern region', 1),
('SUP-2024-0005', 'Kumasi Wholesale Mart', 'Osei Kwame', '0322000111', '0244222333', 'okwame@kumart.com', 'Central Market, Kumasi', 'Kumasi', 'General wholesaler', 1),
('SUP-2024-0006', 'Accra Distributors Ltd', 'Nana Adu', '0302333444', '0544333444', 'nadu@accradist.com', 'Ring Road Central, Accra', 'Accra', 'Nationwide distribution', 1),
('SUP-2024-0007', 'Fresh Farms Ghana', 'Grace Adjei', '0244555666', '0277555666', 'gadjei@freshfarms.com', 'Mampong Road, Mampong', 'Mampong', 'Fresh produce supplier', 1),
('SUP-2024-0008', 'Global Electronics GH', 'Frank Owusu', '0302777888', '0207778888', 'frank@globalelec.com', 'Oxford Street, Osu', 'Accra', 'Electronics and accessories', 1),
('SUP-2024-0009', 'Northern Traders', 'Aliu Mohammed', '0372000222', '0244000222', 'aliu@northerntraders.com', 'Central Mosque Road, Tamale', 'Tamale', 'Supplies to northern regions', 1),
('SUP-2024-0010', 'Coastal Beverages', 'Mensah Botchway', '0312000333', '0543000333', 'mbotch@coastalbev.com', 'Harbor Road, Takoradi', 'Takoradi', 'Beverage distributor', 1),
('SUP-2024-0011', 'Sunrise Imports', 'Hannah Asare', '0244666777', '0277666777', 'hasare@sunrise.com', 'Trade Fair, Accra', 'Accra', 'Import/Export specialist', 0); -- inactive supplier

-- Insert products (with initial stock values for testing)
INSERT INTO products (sku, barcode, name, description, category_id, supplier_id, cost_price, selling_price, current_stock, reorder_level, unit, is_active) VALUES
-- Beverages (category 1)
('BEV001', '6221001234567', 'Coca-Cola 50cl', 'Carbonated soft drink, 50cl bottle', 1, 10, 2.50, 3.50, 150, 50, 'pcs', 1),
('BEV002', '6221001234568', 'Fanta Orange 50cl', 'Orange flavored soda, 50cl bottle', 1, 10, 2.50, 3.50, 120, 50, 'pcs', 1),
('BEV003', '6221001234569', 'Sprite 50cl', 'Lemon-lime soda, 50cl bottle', 1, 10, 2.50, 3.50, 135, 50, 'pcs', 1),
('BEV004', '6221001234570', 'Voltic Water 1.5L', 'Natural mineral water, 1.5L bottle', 1, 4, 3.00, 4.50, 200, 30, 'pcs', 1),
('BEV005', '6221001234571', 'Club Energy Drink 250ml', 'Energy drink, 250ml can', 1, 10, 4.00, 6.00, 80, 20, 'pcs', 1),

-- Food Staples (category 2)
('FOD001', '6221001234572', 'Caprice Rice 5kg', 'Premium long grain rice, 5kg bag', 2, 4, 25.00, 32.00, 45, 10, 'pcs', 1),
('FOD002', '6221001234573', 'Fortuna Vegetable Oil 2L', 'Refined vegetable oil, 2L bottle', 2, 4, 18.00, 24.00, 60, 15, 'pcs', 1),
('FOD003', '6221001234574', 'Ideal Milk 170g', 'Evaporated milk, 170g tin', 2, 4, 4.50, 6.50, 200, 50, 'pcs', 1),
('FOD004', '6221001234575', 'Gari 1kg', 'Cassava flakes, 1kg bag', 2, 9, 5.00, 7.00, 75, 20, 'pcs', 1),
('FOD005', '6221001234576', 'Sugar 2kg', 'Refined white sugar, 2kg bag', 2, 5, 10.00, 13.00, 90, 25, 'pcs', 1),

-- Snacks & Confectionery (category 3)
('SNK001', '6221001234577', 'Pringles Original 165g', 'Potato crisps, original flavor', 3, 4, 12.00, 16.00, 45, 10, 'pcs', 1),
('SNK002', '6221001234578', 'Chocomilo 40g', 'Chocolate bar with wafer', 3, 5, 2.00, 3.00, 300, 50, 'pcs', 1),
('SNK003', '6221001234579', 'Yogurt Raisins 200g', 'Yogurt coated raisins', 3, 5, 8.00, 11.00, 60, 15, 'pcs', 1),
('SNK004', '6221001234580', 'McVities Digestive 400g', 'Digestive biscuits', 3, 5, 14.00, 19.00, 35, 10, 'pcs', 1),

-- Household Supplies (category 4)
('HSE001', '6221001234581', 'OMO Detergent 500g', 'Laundry detergent powder', 4, 6, 8.00, 11.00, 120, 30, 'pcs', 1),
('HSE002', '6221001234582', 'Dettol Soap 125g', 'Antiseptic disinfectant soap', 4, 6, 3.00, 4.50, 250, 50, 'pcs', 1),
('HSE003', '6221001234583', 'Hi-Spray 400ml', 'Multi-purpose insect spray', 4, 6, 12.00, 17.00, 40, 10, 'pcs', 1),
('HSE004', '6221001234584', 'Toilet Roll 12-pack', 'Soft toilet tissue, 12 rolls', 4, 6, 18.00, 24.00, 85, 20, 'pcs', 1),

-- Personal Care (category 5)
('PER001', '6221001234585', 'CloseUp Toothpaste 100ml', 'Mint fresh toothpaste', 5, 6, 5.00, 7.50, 150, 30, 'pcs', 1),
('PER002', '6221001234586', 'Dove Shampoo 200ml', 'Hair shampoo for all types', 5, 6, 15.00, 22.00, 55, 15, 'pcs', 1),
('PER003', '6221001234587', 'Nivea Cream 200ml', 'Moisturizing body cream', 5, 6, 18.00, 25.00, 40, 10, 'pcs', 1),
('PER004', '6221001234588', 'Gillette Razor 2-pack', 'Disposable razors', 5, 6, 8.00, 12.00, 95, 20, 'pcs', 1),

-- Electronics (category 6)
('ELC001', '6221001234589', 'Panasonic Battery AA 4-pack', 'Alkaline batteries', 6, 8, 6.00, 9.00, 200, 40, 'pks', 1),
('ELC002', '6221001234590', 'USB Cable 1m', 'Type-C charging cable', 6, 8, 12.00, 20.00, 75, 15, 'pcs', 1),
('ELC003', '6221001234591', 'Phone Charger 2A', 'Wall charger with USB port', 6, 8, 25.00, 40.00, 30, 8, 'pcs', 1),
('ELC004', '6221001234592', 'LED Bulb 9W', 'Energy saving LED bulb', 6, 8, 10.00, 18.00, 110, 25, 'pcs', 1),

-- Fresh Produce (category 7)
('FRP001', '6221001234593', 'Tomatoes 1kg', 'Fresh red tomatoes', 7, 7, 5.00, 8.00, 40, 15, 'kg', 1),
('FRP002', '6221001234594', 'Onions 1kg', 'Yellow onions', 7, 7, 4.00, 7.00, 50, 20, 'kg', 1),
('FRP003', '6221001234595', 'Plantain 3pcs', 'Ripe plantain', 7, 7, 6.00, 10.00, 30, 10, 'bunch', 1),
('FRP004', '6221001234596', 'Oranges 1kg', 'Sweet oranges', 7, 7, 3.00, 5.00, 60, 20, 'kg', 1),

-- Bakery (category 8)
('BAK001', '6221001234597', 'Sliced Bread 450g', 'White sliced bread', 8, 6, 6.00, 9.00, 25, 10, 'pcs', 1),
('BAK002', '6221001234598', 'Tea Cake 200g', 'Butter tea cake', 8, 6, 7.00, 11.00, 15, 5, 'pcs', 1),
('BAK003', '6221001234599', 'Chocolate Muffin', 'Large chocolate muffin', 8, 6, 3.00, 5.00, 20, 5, 'pcs', 1),

-- Frozen Foods (category 9)
('FRZ001', '6221001234600', 'Chicken Wings 1kg', 'Frozen chicken wings', 9, 4, 15.00, 24.00, 35, 10, 'kg', 1),
('FRZ002', '6221001234601', 'Frozen Tilapia 500g', 'Whole frozen tilapia', 9, 4, 12.00, 20.00, 28, 8, 'pcs', 1),
('FRZ003', '6221001234602', 'Mixed Vegetables 500g', 'Frozen mixed veggies', 9, 4, 8.00, 13.00, 45, 12, 'pcs', 1),

-- Inactive products (for testing)
('INV001', '6221001234603', 'Discontinued Product', 'Test inactive item', 1, 4, 10.00, 15.00, 0, 5, 'pcs', 0);

-- Insert sample customer transactions (for credit management testing)
INSERT INTO customer_transactions (customer_id, transaction_type, amount, balance_before, balance_after, reference_type, payment_method, user_id, notes) VALUES
-- Customer 1: Emmanuel Asare
(2, 'payment', -500.00, -2500.00, -3000.00, 'manual', 'cash', 1, 'Partial payment for credit'),
(2, 'sale', 1500.00, -3000.00, -1500.00, 'sale', 'credit', 1, 'New purchase on credit'),
(2, 'deposit', 2000.00, -1500.00, 500.00, 'manual', 'mobile', 2, 'Customer deposit'),

-- Customer 2: Abena Owusu
(3, 'payment', -250.00, -750.00, -1000.00, 'manual', 'cash', 1, 'Payment against credit'),
(3, 'refund', 150.00, -1000.00, -850.00, 'sale', 'cash', 3, 'Refund for returned item'),

-- Customer 3: Kwame Boateng (positive balance)
(4, 'deposit', 1000.00, 500.00, 1500.00, 'manual', 'mobile', 2, 'Customer deposit'),
(4, 'adjustment', -500.00, 1500.00, 1000.00, 'manual', 'adjustment', 1, 'Credit adjustment'),

-- Customer 5: Yaw Asante
(6, 'sale', 800.00, -3200.00, -2400.00, 'sale', 'credit', 1, 'Credit purchase'),
(6, 'payment', -400.00, -2400.00, -2800.00, 'manual', 'cash', 3, 'Payment received'),

-- Customer 7: Kojo Eshun
(8, 'sale', 2000.00, -4500.00, -2500.00, 'sale', 'credit', 2, 'Bulk purchase on credit'),
(8, 'payment', -1000.00, -2500.00, -3500.00, 'manual', 'bank', 1, 'Bank transfer payment');

-- Update current balances to reflect transactions
UPDATE customers SET current_balance = -1500.00 WHERE id = 2; -- Emmanuel
UPDATE customers SET current_balance = -850.00 WHERE id = 3;  -- Abena
UPDATE customers SET current_balance = 1000.00 WHERE id = 4;  -- Kwame
UPDATE customers SET current_balance = -2800.00 WHERE id = 6; -- Yaw
UPDATE customers SET current_balance = -3500.00 WHERE id = 8; -- Kojo

-- Update settings with realistic values
UPDATE settings SET 
store_name = 'QuickMart Superstore',
store_address = '123 Independence Avenue, Accra, Ghana',
store_phone = '+233 302 555 1234',
currency = 'GHS',
tax_rate = 12.5,
low_stock_alert = 1
WHERE id = 1;
