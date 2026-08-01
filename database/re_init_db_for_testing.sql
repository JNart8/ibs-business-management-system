-- ============================================================
-- CLEAR EXISTING TEST DATA (PRESERVE ESSENTIAL RECORDS)
-- ============================================================
-- Run this script to clear all test data while keeping:
-- - Walk-in customer (id = 1)
-- - Admin user (id = 1)
-- - Default settings (id = 1)
-- ============================================================

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Clear data from tables that depend on others first (child tables)
TRUNCATE TABLE product_cost_history;
TRUNCATE TABLE supplier_transactions;
TRUNCATE TABLE purchase_items;
TRUNCATE TABLE purchases;
TRUNCATE TABLE stock_movements;
TRUNCATE TABLE sale_items;
TRUNCATE TABLE sales;
TRUNCATE TABLE customer_transactions;

-- Clear data from main tables but preserve essential records
DELETE FROM products WHERE id > 0;
DELETE FROM categories WHERE id > 0;
DELETE FROM suppliers WHERE id > 0;
DELETE FROM customers WHERE id > 1;  -- Preserve walk-in customer (id=1)
DELETE FROM users WHERE id > 1;       -- Preserve admin user (id=1)
DELETE FROM expenses WHERE id > 0;

-- Reset auto-increment counters
ALTER TABLE products AUTO_INCREMENT = 1;
ALTER TABLE categories AUTO_INCREMENT = 1;
ALTER TABLE suppliers AUTO_INCREMENT = 1;
ALTER TABLE customers AUTO_INCREMENT = 2;  -- Next customer ID will be 2
ALTER TABLE users AUTO_INCREMENT = 2;       -- Next user ID will be 2
ALTER TABLE purchases AUTO_INCREMENT = 1;
ALTER TABLE purchase_items AUTO_INCREMENT = 1;
ALTER TABLE supplier_transactions AUTO_INCREMENT = 1;
ALTER TABLE product_cost_history AUTO_INCREMENT = 1;
ALTER TABLE sales AUTO_INCREMENT = 1;
ALTER TABLE sale_items AUTO_INCREMENT = 1;
ALTER TABLE customer_transactions AUTO_INCREMENT = 1;
ALTER TABLE stock_movements AUTO_INCREMENT = 1;
ALTER TABLE expenses AUTO_INCREMENT = 1;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- INSERT NEW TEST DATA
-- ============================================================

-- Insert additional users
INSERT INTO users (username, password_hash, full_name, role, is_active, login_attempts) VALUES
('john.doe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 'staff', 1, 0),
('jane.smith', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', 'cashier', 1, 0),
('mike.johnson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike Johnson', 'cashier', 1, 0),
('sarah.wilson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Wilson', 'staff', 1, 0),
('david.brown', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'David Brown', 'cashier', 0, 3); -- inactive user

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

-- Insert suppliers (with credit management fields populated)
INSERT INTO suppliers (supplier_code, company_name, contact_name, phone, phone_alt, email, address, city, notes, is_active, current_balance, credit_limit, total_purchases) VALUES
('SUP-2024-0001', 'Accra Foods Ltd', 'Kofi Mensah', '0244000001', '0204000001', 'kofi@accrafoods.com', 'Industrial Area, Accra', 'Accra', 'Main food supplier', 1, -15000.00, 50000.00, 125000.00),
('SUP-2024-0002', 'GH Electronics', 'Ama Owusu', '0244000002', '0264000002', 'ama@ghelectronics.com', 'Ring Road Central, Kumasi', 'Kumasi', 'Electronics and accessories', 1, -8500.00, 30000.00, 78000.00),
('SUP-2024-0003', 'Northern Beverages Co', 'Yaw Asante', '0244000003', '0274000003', 'yaw@northernbev.com', 'Central Market, Tamale', 'Tamale', 'Beverage distributor', 1, -22000.00, 75000.00, 215000.00),
('SUP-2024-0004', 'Tema Food Complex', 'Esi Dogbey', '0244111222', '0277111222', 'esi@temafood.com', 'Harbor Industrial Area, Tema', 'Tema', 'Main food supplier for eastern region', 1, -3200.00, 40000.00, 95000.00),
('SUP-2024-0005', 'Kumasi Wholesale Mart', 'Osei Kwame', '0322000111', '0244222333', 'okwame@kumart.com', 'Central Market, Kumasi', 'Kumasi', 'General wholesaler', 1, -18000.00, 60000.00, 182000.00),
('SUP-2024-0006', 'Accra Distributors Ltd', 'Nana Adu', '0302333444', '0544333444', 'nadu@accradist.com', 'Ring Road Central, Accra', 'Accra', 'Nationwide distribution', 1, -5600.00, 35000.00, 112000.00),
('SUP-2024-0007', 'Fresh Farms Ghana', 'Grace Adjei', '0244555666', '0277555666', 'gadjei@freshfarms.com', 'Mampong Road, Mampong', 'Mampong', 'Fresh produce supplier', 1, -4200.00, 25000.00, 67000.00),
('SUP-2024-0008', 'Global Electronics GH', 'Frank Owusu', '0302777888', '0207778888', 'frank@globalelec.com', 'Oxford Street, Osu', 'Accra', 'Electronics and accessories', 1, -9500.00, 40000.00, 135000.00),
('SUP-2024-0009', 'Sunrise Imports', 'Hannah Asare', '0244666777', '0277666777', 'hasare@sunrise.com', 'Trade Fair, Accra', 'Accra', 'Import/Export specialist', 0, 0.00, 0.00, 0.00); -- inactive supplier

-- Insert customers (with credit management)
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

-- Insert products (with cost tracking fields)
INSERT INTO products (sku, barcode, name, description, category_id, supplier_id, cost_price, average_cost, last_purchase_cost, last_purchase_date, selling_price, current_stock, reorder_level, unit, is_active) VALUES
-- Beverages (category 1)
('BEV001', '6221001234567', 'Coca-Cola 50cl', 'Carbonated soft drink, 50cl bottle', 1, 3, 2.50, 2.48, 2.45, '2026-02-10 10:30:00', 3.50, 150, 50, 'pcs', 1),
('BEV002', '6221001234568', 'Fanta Orange 50cl', 'Orange flavored soda, 50cl bottle', 1, 3, 2.50, 2.52, 2.55, '2026-02-12 14:15:00', 3.50, 120, 50, 'pcs', 1),
('BEV003', '6221001234569', 'Sprite 50cl', 'Lemon-lime soda, 50cl bottle', 1, 3, 2.50, 2.50, 2.50, '2026-02-08 09:45:00', 3.50, 135, 50, 'pcs', 1),
('BEV004', '6221001234570', 'Voltic Water 1.5L', 'Natural mineral water, 1.5L bottle', 1, 3, 3.00, 3.05, 3.10, '2026-02-15 11:20:00', 4.50, 200, 30, 'pcs', 1),
('BEV005', '6221001234571', 'Club Energy Drink 250ml', 'Energy drink, 250ml can', 1, 3, 4.00, 4.15, 4.30, '2026-02-05 16:30:00', 6.00, 80, 20, 'pcs', 1),

-- Food Staples (category 2)
('FOD001', '6221001234572', 'Caprice Rice 5kg', 'Premium long grain rice, 5kg bag', 2, 1, 25.00, 24.80, 24.50, '2026-02-14 13:40:00', 32.00, 45, 10, 'pcs', 1),
('FOD002', '6221001234573', 'Fortuna Vegetable Oil 2L', 'Refined vegetable oil, 2L bottle', 2, 1, 18.00, 17.60, 17.20, '2026-02-09 10:15:00', 24.00, 60, 15, 'pcs', 1),
('FOD003', '6221001234574', 'Ideal Milk 170g', 'Evaporated milk, 170g tin', 2, 1, 4.50, 4.45, 4.40, '2026-02-13 12:30:00', 6.50, 200, 50, 'pcs', 1),
('FOD004', '6221001234575', 'Gari 1kg', 'Cassava flakes, 1kg bag', 2, 5, 5.00, 5.10, 5.20, '2026-02-07 09:50:00', 7.00, 75, 20, 'pcs', 1),
('FOD005', '6221001234576', 'Sugar 2kg', 'Refined white sugar, 2kg bag', 2, 5, 10.00, 10.30, 10.60, '2026-02-11 15:45:00', 13.00, 90, 25, 'pcs', 1),

-- Snacks & Confectionery (category 3)
('SNK001', '6221001234577', 'Pringles Original 165g', 'Potato crisps, original flavor', 3, 4, 12.00, 12.20, 12.40, '2026-02-06 14:20:00', 16.00, 45, 10, 'pcs', 1),
('SNK002', '6221001234578', 'Chocomilo 40g', 'Chocolate bar with wafer', 3, 5, 2.00, 2.05, 2.10, '2026-02-12 11:10:00', 3.00, 300, 50, 'pcs', 1),
('SNK003', '6221001234579', 'Yogurt Raisins 200g', 'Yogurt coated raisins', 3, 5, 8.00, 8.15, 8.30, '2026-02-10 10:30:00', 11.00, 60, 15, 'pcs', 1),
('SNK004', '6221001234580', 'McVities Digestive 400g', 'Digestive biscuits', 3, 5, 14.00, 14.25, 14.50, '2026-02-08 16:15:00', 19.00, 35, 10, 'pcs', 1),

-- Household Supplies (category 4)
('HSE001', '6221001234581', 'OMO Detergent 500g', 'Laundry detergent powder', 4, 6, 8.00, 8.10, 8.20, '2026-02-13 13:30:00', 11.00, 120, 30, 'pcs', 1),
('HSE002', '6221001234582', 'Dettol Soap 125g', 'Antiseptic disinfectant soap', 4, 6, 3.00, 3.05, 3.10, '2026-02-09 12:45:00', 4.50, 250, 50, 'pcs', 1),
('HSE003', '6221001234583', 'Hi-Spray 400ml', 'Multi-purpose insect spray', 4, 6, 12.00, 12.30, 12.60, '2026-02-05 15:20:00', 17.00, 40, 10, 'pcs', 1),
('HSE004', '6221001234584', 'Toilet Roll 12-pack', 'Soft toilet tissue, 12 rolls', 4, 6, 18.00, 18.40, 18.80, '2026-02-11 10:15:00', 24.00, 85, 20, 'pcs', 1),

-- Personal Care (category 5)
('PER001', '6221001234585', 'CloseUp Toothpaste 100ml', 'Mint fresh toothpaste', 5, 6, 5.00, 5.10, 5.20, '2026-02-14 11:30:00', 7.50, 150, 30, 'pcs', 1),
('PER002', '6221001234586', 'Dove Shampoo 200ml', 'Hair shampoo for all types', 5, 6, 15.00, 15.30, 15.60, '2026-02-07 14:40:00', 22.00, 55, 15, 'pcs', 1),
('PER003', '6221001234587', 'Nivea Cream 200ml', 'Moisturizing body cream', 5, 6, 18.00, 18.50, 19.00, '2026-02-10 09:20:00', 25.00, 40, 10, 'pcs', 1),
('PER004', '6221001234588', 'Gillette Razor 2-pack', 'Disposable razors', 5, 6, 8.00, 8.20, 8.40, '2026-02-12 16:50:00', 12.00, 95, 20, 'pcs', 1),

-- Electronics (category 6)
('ELC001', '6221001234589', 'Panasonic Battery AA 4-pack', 'Alkaline batteries', 6, 2, 6.00, 6.10, 6.20, '2026-02-08 13:15:00', 9.00, 200, 40, 'pks', 1),
('ELC002', '6221001234590', 'USB Cable 1m', 'Type-C charging cable', 6, 8, 12.00, 12.50, 13.00, '2026-02-13 15:30:00', 20.00, 75, 15, 'pcs', 1),
('ELC003', '6221001234591', 'Phone Charger 2A', 'Wall charger with USB port', 6, 8, 25.00, 26.00, 27.00, '2026-02-09 10:45:00', 40.00, 30, 8, 'pcs', 1),
('ELC004', '6221001234592', 'LED Bulb 9W', 'Energy saving LED bulb', 6, 8, 10.00, 10.20, 10.40, '2026-02-11 12:10:00', 18.00, 110, 25, 'pcs', 1),

-- Fresh Produce (category 7)
('FRP001', '6221001234593', 'Tomatoes 1kg', 'Fresh red tomatoes', 7, 7, 5.00, 5.30, 5.60, '2026-02-14 08:30:00', 8.00, 40, 15, 'kg', 1),
('FRP002', '6221001234594', 'Onions 1kg', 'Yellow onions', 7, 7, 4.00, 4.20, 4.40, '2026-02-12 09:15:00', 7.00, 50, 20, 'kg', 1),
('FRP003', '6221001234595', 'Plantain 3pcs', 'Ripe plantain', 7, 7, 6.00, 6.30, 6.60, '2026-02-10 07:45:00', 10.00, 30, 10, 'bunch', 1),
('FRP004', '6221001234596', 'Oranges 1kg', 'Sweet oranges', 7, 7, 3.00, 3.20, 3.40, '2026-02-13 08:20:00', 5.00, 60, 20, 'kg', 1),

-- Bakery (category 8)
('BAK001', '6221001234597', 'Sliced Bread 450g', 'White sliced bread', 8, 6, 6.00, 6.10, 6.20, '2026-02-14 05:30:00', 9.00, 25, 10, 'pcs', 1),
('BAK002', '6221001234598', 'Tea Cake 200g', 'Butter tea cake', 8, 6, 7.00, 7.20, 7.40, '2026-02-14 06:15:00', 11.00, 15, 5, 'pcs', 1),
('BAK003', '6221001234599', 'Chocolate Muffin', 'Large chocolate muffin', 8, 6, 3.00, 3.10, 3.20, '2026-02-14 06:45:00', 5.00, 20, 5, 'pcs', 1),

-- Frozen Foods (category 9)
('FRZ001', '6221001234600', 'Chicken Wings 1kg', 'Frozen chicken wings', 9, 4, 15.00, 15.40, 15.80, '2026-02-11 11:30:00', 24.00, 35, 10, 'kg', 1),
('FRZ002', '6221001234601', 'Frozen Tilapia 500g', 'Whole frozen tilapia', 9, 4, 12.00, 12.30, 12.60, '2026-02-09 10:45:00', 20.00, 28, 8, 'pcs', 1),
('FRZ003', '6221001234602', 'Mixed Vegetables 500g', 'Frozen mixed veggies', 9, 4, 8.00, 8.20, 8.40, '2026-02-13 14:20:00', 13.00, 45, 12, 'pcs', 1),

-- Inactive products (for testing)
('INV001', '6221001234603', 'Discontinued Product', 'Test inactive item', 1, 4, 10.00, 10.00, NULL, NULL, 15.00, 0, 5, 'pcs', 0);

-- ============================================================
-- INSERT PURCHASE DATA
-- ============================================================

-- Insert purchases
INSERT INTO purchases (purchase_number, supplier_id, purchase_date, subtotal, discount_percent, discount_amount, vat_percent, vat_amount, total_amount, payment_status, payment_method, amount_paid, amount_due, invoice_number, notes, user_id) VALUES
('PUR-20260215-001', 1, '2026-02-15 10:30:00', 12500.00, 2.5, 312.50, 12.5, 1523.44, 13710.94, 'paid', 'bank', 13710.94, 0.00, 'INV-ACC-2026-001', 'Monthly restock - rice and oil', 2),
('PUR-20260214-002', 3, '2026-02-14 14:15:00', 8750.00, 1.0, 87.50, 12.5, 1082.81, 9745.31, 'paid', 'mobile', 9745.31, 0.00, 'NB-2026-002', 'Beverages restock', 2),
('PUR-20260213-003', 5, '2026-02-13 11:20:00', 5400.00, 0.0, 0.00, 12.5, 675.00, 6075.00, 'partial', 'credit', 3000.00, 3075.00, 'KWM-2026-015', 'Sugar and snacks', 3),
('PUR-20260212-004', 6, '2026-02-12 09:45:00', 8900.00, 1.5, 133.50, 12.5, 1095.81, 9862.31, 'unpaid', 'credit', 0.00, 9862.31, 'ADL-2026-008', 'Household supplies', 2),
('PUR-20260211-005', 2, '2026-02-11 15:30:00', 12500.00, 3.0, 375.00, 12.5, 1515.63, 13640.63, 'paid', 'cheque', 13640.63, 0.00, 'GHE-2026-003', 'Electronics restock', 3),
('PUR-20260210-006', 7, '2026-02-10 08:15:00', 3800.00, 0.0, 0.00, 12.5, 475.00, 4275.00, 'paid', 'cash', 4275.00, 0.00, 'FFG-2026-010', 'Fresh produce', 3),
('PUR-20260209-007', 8, '2026-02-09 13:50:00', 9200.00, 2.0, 184.00, 12.5, 1127.00, 10143.00, 'partial', 'credit', 5000.00, 5143.00, 'GE-2026-005', 'Cables and accessories', 2);

-- Insert purchase items
INSERT INTO purchase_items (purchase_id, product_id, product_name, quantity, unit_cost, discount_percent, line_total) VALUES
-- Purchase 1: Accra Foods Ltd (products 6-10)
(1, 6, 'Caprice Rice 5kg', 100, 24.50, 2.5, 2388.75),
(1, 7, 'Fortuna Vegetable Oil 2L', 80, 17.20, 2.5, 1341.60),
(1, 8, 'Ideal Milk 170g', 200, 4.40, 2.5, 858.00),
(1, 9, 'Gari 1kg', 50, 5.20, 2.5, 253.50),
(1, 10, 'Sugar 2kg', 60, 10.60, 2.5, 620.10),

-- Purchase 2: Northern Beverages Co (products 1-5)
(2, 1, 'Coca-Cola 50cl', 200, 2.45, 1.0, 485.10),
(2, 2, 'Fanta Orange 50cl', 150, 2.55, 1.0, 378.68),
(2, 3, 'Sprite 50cl', 180, 2.50, 1.0, 445.50),
(2, 4, 'Voltic Water 1.5L', 150, 3.10, 1.0, 460.35),
(2, 5, 'Club Energy Drink 250ml', 100, 4.30, 1.0, 425.70),

-- Purchase 3: Kumasi Wholesale Mart (products 10, 12-14)
(3, 10, 'Sugar 2kg', 100, 10.60, 0.0, 1060.00),
(3, 12, 'Chocomilo 40g', 500, 2.10, 0.0, 1050.00),
(3, 13, 'Yogurt Raisins 200g', 150, 8.30, 0.0, 1245.00),
(3, 14, 'McVities Digestive 400g', 100, 14.50, 0.0, 1450.00),

-- Purchase 4: Accra Distributors Ltd (products 15-18)
(4, 15, 'OMO Detergent 500g', 200, 8.20, 1.5, 1615.40),
(4, 16, 'Dettol Soap 125g', 300, 3.10, 1.5, 916.05),
(4, 17, 'Hi-Spray 400ml', 80, 12.60, 1.5, 993.72),
(4, 18, 'Toilet Roll 12-pack', 120, 18.80, 1.5, 2222.64),

-- Purchase 5: GH Electronics (products 25-28)
(5, 25, 'Panasonic Battery AA 4-pack', 300, 6.20, 3.0, 1804.20),
(5, 26, 'USB Cable 1m', 150, 13.00, 3.0, 1891.50),
(5, 27, 'Phone Charger 2A', 80, 27.00, 3.0, 2095.20),
(5, 28, 'LED Bulb 9W', 200, 10.40, 3.0, 2017.60),

-- Purchase 6: Fresh Farms Ghana (products 29-32)
(6, 29, 'Tomatoes 1kg', 100, 5.60, 0.0, 560.00),
(6, 30, 'Onions 1kg', 150, 4.40, 0.0, 660.00),
(6, 31, 'Plantain 3pcs', 80, 6.60, 0.0, 528.00),
(6, 32, 'Oranges 1kg', 120, 3.40, 0.0, 408.00),

-- Purchase 7: Global Electronics GH (products 26-28)
(7, 26, 'USB Cable 1m', 200, 13.00, 2.0, 2548.00),
(7, 27, 'Phone Charger 2A', 100, 27.00, 2.0, 2646.00),
(7, 28, 'LED Bulb 9W', 150, 10.40, 2.0, 1528.80);

-- ============================================================
-- INSERT SUPPLIER TRANSACTIONS
-- ============================================================

INSERT INTO supplier_transactions (supplier_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, payment_method, notes, user_id) VALUES
-- Supplier 1: Accra Foods Ltd
(1, 'purchase', -13710.94, -1290.00, -15000.94, 'purchase', 1, 'credit', 'Purchase: PUR-20260215-001', 2),
(1, 'payment', 5000.00, -15000.94, -10000.94, 'payment', NULL, 'bank', 'Bank transfer payment', 2),

-- Supplier 3: Northern Beverages Co
(3, 'purchase', -9745.31, -12254.69, -22000.00, 'purchase', 2, 'credit', 'Purchase: PUR-20260214-002', 2),

-- Supplier 5: Kumasi Wholesale Mart
(5, 'purchase', -6075.00, -11925.00, -18000.00, 'purchase', 3, 'credit', 'Purchase: PUR-20260213-003', 3),
(5, 'payment', 3000.00, -18000.00, -15000.00, 'payment', NULL, 'mobile', 'Mobile money payment', 3),

-- Supplier 6: Accra Distributors Ltd
(6, 'purchase', -9862.31, 4262.31, -5600.00, 'purchase', 4, 'credit', 'Purchase: PUR-20260212-004', 2),

-- Supplier 2: GH Electronics
(2, 'purchase', -13640.63, 5140.63, -8500.00, 'purchase', 5, 'credit', 'Purchase: PUR-20260211-005', 3),
(2, 'payment', 8500.00, -8500.00, 0.00, 'payment', NULL, 'cheque', 'Full payment', 3),

-- Supplier 7: Fresh Farms Ghana
(7, 'purchase', -4275.00, 75.00, -4200.00, 'purchase', 6, 'credit', 'Purchase: PUR-20260210-006', 3),

-- Supplier 8: Global Electronics GH
(8, 'purchase', -10143.00, 643.00, -9500.00, 'purchase', 7, 'credit', 'Purchase: PUR-20260209-007', 2),
(8, 'payment', 5000.00, -9500.00, -4500.00, 'payment', NULL, 'bank', 'Partial payment', 2);

-- ============================================================
-- INSERT PRODUCT COST HISTORY
-- ============================================================

INSERT INTO product_cost_history (product_id, purchase_id, old_cost, new_cost, unit_cost, quantity, change_percent, stock_before, stock_after, created_at) VALUES
-- Product 6: Caprice Rice
(6, 1, 25.00, 24.80, 24.50, 100, -0.8, 45, 145, '2026-02-15 10:30:00'),
-- Product 7: Vegetable Oil
(7, 1, 18.00, 17.60, 17.20, 80, -2.22, 60, 140, '2026-02-15 10:30:00'),
-- Product 1: Coca-Cola
(1, 2, 2.50, 2.48, 2.45, 200, -0.8, 150, 350, '2026-02-14 14:15:00'),
-- Product 2: Fanta
(2, 2, 2.50, 2.52, 2.55, 150, 0.8, 120, 270, '2026-02-14 14:15:00'),
-- Product 10: Sugar
(10, 3, 10.30, 10.60, 10.60, 100, 2.91, 90, 190, '2026-02-13 11:20:00'),
-- Product 25: Batteries
(25, 5, 6.10, 6.20, 6.20, 300, 1.64, 200, 500, '2026-02-11 15:30:00'),
-- Product 29: Tomatoes
(29, 6, 5.30, 5.60, 5.60, 100, 5.66, 40, 140, '2026-02-10 08:15:00');

-- ============================================================
-- INSERT EXPENSES
-- ============================================================

INSERT INTO expenses (expense_date, category, description, amount, payment_method, notes) VALUES
('2026-02-01', 'Utilities', 'Electricity bill - January', 850.00, 'bank', 'Monthly electricity payment'),
('2026-02-03', 'Rent', 'February rent - Shop premises', 2500.00, 'bank', 'Monthly rent'),
('2026-02-05', 'Salaries', 'Staff salaries - January', 4500.00, 'bank', 'Paid to 5 staff members'),
('2026-02-07', 'Maintenance', 'AC repair', 350.00, 'cash', 'Fixed AC in main shop'),
('2026-02-10', 'Supplies', 'Packaging materials', 420.00, 'cash', 'Bags and boxes'),
('2026-02-12', 'Marketing', 'Social media ads', 300.00, 'mobile', 'Facebook/Instagram promotion'),
('2026-02-15', 'Transport', 'Fuel for delivery van', 280.00, 'cash', 'Weekly fuel top-up'),
('2026-02-18', 'Internet', 'Monthly internet subscription', 400.00, 'mobile', 'Fiber optic business plan'),
('2026-02-20', 'Insurance', 'Shop insurance premium', 650.00, 'bank', 'Monthly insurance'),
('2026-02-22', 'Cleaning', 'Cleaning services', 200.00, 'cash', 'Weekly cleaning'),
('2026-02-24', 'Licenses', 'Renewal of business permit', 1200.00, 'cheque', 'Annual renewal'),
('2026-02-25', 'Miscellaneous', 'Office supplies', 180.00, 'cash', 'Stationery and printing');

-- ============================================================
-- INSERT CUSTOMER TRANSACTIONS
-- ============================================================

INSERT INTO customer_transactions (customer_id, transaction_type, amount, balance_before, balance_after, reference_type, payment_method, user_id, notes) VALUES
-- Customer 2: Emmanuel Asare
(2, 'payment', -500.00, -2500.00, -3000.00, 'manual', 'cash', 1, 'Partial payment for credit'),
(2, 'sale', 1500.00, -3000.00, -1500.00, 'sale', 'credit', 1, 'New purchase on credit'),
(2, 'deposit', 2000.00, -1500.00, 500.00, 'manual', 'deposit', 2, 'Customer deposit'),

-- Customer 3: Abena Owusu
(3, 'payment', -250.00, -750.00, -1000.00, 'manual', 'cash', 1, 'Payment against credit'),
(3, 'refund', 150.00, -1000.00, -850.00, 'sale', 'cash', 3, 'Refund for returned item'),

-- Customer 4: Kwame Boateng
(4, 'deposit', 1000.00, 500.00, 1500.00, 'manual', 'deposit', 2, 'Customer deposit'),
(4, 'adjustment', -500.00, 1500.00, 1000.00, 'manual', 'credit', 1, 'Credit adjustment'),

-- Customer 6: Yaw Asante
(6, 'sale', 800.00, -3200.00, -2400.00, 'sale', 'credit', 1, 'Credit purchase'),
(6, 'payment', -400.00, -2400.00, -2800.00, 'manual', 'cash', 3, 'Payment received'),

-- Customer 8: Kojo Eshun
(8, 'sale', 2000.00, -4500.00, -2500.00, 'sale', 'credit', 2, 'Bulk purchase on credit'),
(8, 'payment', -1000.00, -2500.00, -3500.00, 'manual', 'bank', 1, 'Bank transfer payment');

-- ============================================================
-- UPDATE FINAL BALANCES
-- ============================================================

-- Update customers current balances
UPDATE customers SET current_balance = -1500.00 WHERE id = 2; -- Emmanuel
UPDATE customers SET current_balance = -850.00 WHERE id = 3;  -- Abena
UPDATE customers SET current_balance = 1000.00 WHERE id = 4;  -- Kwame
UPDATE customers SET current_balance = -2800.00 WHERE id = 6; -- Yaw
UPDATE customers SET current_balance = -3500.00 WHERE id = 8; -- Kojo

-- Update settings
UPDATE settings SET 
store_name = 'QuickMart Superstore',
store_address = '123 Independence Avenue, Accra, Ghana',
store_phone = '+233 302 555 1234',
currency = 'GHS',
tax_rate = 12.5,
low_stock_alert = 1
WHERE id = 1;

-- ============================================================
-- VERIFICATION QUERIES
-- ============================================================

-- Show counts of inserted data
SELECT 'USERS COUNT (excluding admin)' AS table_name, COUNT(*) AS record_count FROM users WHERE id > 1
UNION ALL
SELECT 'CATEGORIES COUNT', COUNT(*) FROM categories
UNION ALL
SELECT 'SUPPLIERS COUNT', COUNT(*) FROM suppliers
UNION ALL
SELECT 'CUSTOMERS COUNT (excluding walk-in)', COUNT(*) FROM customers WHERE id > 1
UNION ALL
SELECT 'PRODUCTS COUNT', COUNT(*) FROM products
UNION ALL
SELECT 'PURCHASES COUNT', COUNT(*) FROM purchases
UNION ALL
SELECT 'PURCHASE ITEMS COUNT', COUNT(*) FROM purchase_items
UNION ALL
SELECT 'SUPPLIER TRANSACTIONS COUNT', COUNT(*) FROM supplier_transactions
UNION ALL
SELECT 'PRODUCT COST HISTORY COUNT', COUNT(*) FROM product_cost_history
UNION ALL
SELECT 'EXPENSES COUNT', COUNT(*) FROM expenses
UNION ALL
SELECT 'CUSTOMER TRANSACTIONS COUNT', COUNT(*) FROM customer_transactions;

-- Show summary of financial data
SELECT 
    'Total Purchase Value' AS metric, SUM(total_amount) AS value FROM purchases
UNION ALL
SELECT 'Total Paid to Suppliers', SUM(amount_paid) FROM purchases
UNION ALL
SELECT 'Outstanding to Suppliers', SUM(amount_due) FROM purchases
UNION ALL
SELECT 'Total Expenses', SUM(amount) FROM expenses
UNION ALL
SELECT 'Supplier Credit Balance', SUM(current_balance) FROM suppliers WHERE current_balance < 0;

-- Show success message
SELECT '==================================================' AS message
UNION ALL
SELECT 'TEST DATA RESET AND REINSERTED SUCCESSFULLY!' AS message
UNION ALL
SELECT '==================================================' AS message
UNION ALL
SELECT 'Walk-in customer (ID: 1) and Admin user (ID: 1) preserved' AS message
UNION ALL
SELECT 'All new test data inserted with purchase management system' AS message;