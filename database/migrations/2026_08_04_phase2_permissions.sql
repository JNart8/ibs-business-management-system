-- ============================================================
-- PHASE 2 MIGRATION: granular permissions
-- ============================================================
-- Adds a roles/permissions system. On its own this changes NOTHING
-- about how the app behaves — the seed data below reproduces the
-- exact same access rules that were previously hardcoded in
-- public/index.php ($roleRestrictions) and scattered role checks
-- in a few controllers, with one deliberate exception: it CLOSES a
-- pre-existing gap where '/purchases' had no role restriction at
-- all (cashiers could reach it directly by URL even though the
-- dashboard hid the button). See ARCHITECTURE.md §5.10 for why.
--
-- Take a backup before running, same as every migration so far.
-- ============================================================

-- ------------------------------------------------------------
-- 1. Roles (system roles + future custom roles)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = admin/staff/cashier, cannot be renamed or deleted',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `name`, `slug`, `is_system`) VALUES
    (1, 'Admin',   'admin',   1),
    (2, 'Staff',   'staff',   1),
    (3, 'Cashier', 'cashier', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ------------------------------------------------------------
-- 2. Permission catalog
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(60) NOT NULL UNIQUE,
    `label` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'General'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`key`, `label`, `category`) VALUES
    ('users.manage',              'Manage Users',                  'Administration'),
    ('settings.manage',           'Manage Settings',               'Administration'),
    ('roles.manage',              'Manage Roles & Permissions',    'Administration'),
    ('products.manage',           'Manage Products',               'Inventory'),
    ('categories.manage',         'Manage Categories',             'Inventory'),
    ('stock.manage',              'Manage Stock',                  'Inventory'),
    ('suppliers.manage',          'Manage Suppliers',              'Purchasing'),
    ('purchases.manage',          'Manage Purchases',              'Purchasing'),
    ('customers.manage',          'Manage Customers',              'Sales'),
    ('transactions.manage',       'View Customer Transactions',    'Sales'),
    ('distributor.manage',        'Manage Distributor Deliveries', 'Sales'),
    ('sales.backdate',            'Back-date Sales',               'Sales'),
    ('sales.edit',                'Edit / Void Sales',             'Sales'),
    ('financial_accounts.access', 'Access Financial Accounts',     'Finance'),
    ('expenses.manage',           'Manage Expenses',                'Finance'),
    ('suspense.manage',           'Manage Suspense Account',       'Finance')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

-- ------------------------------------------------------------
-- 3. role_permissions (many-to-many)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_key` VARCHAR(60) NOT NULL,
    PRIMARY KEY (`role_id`, `permission_key`),
    CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_key`) REFERENCES `permissions` (`key`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin: every permission
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_key`)
SELECT 1, `key` FROM `permissions`;

-- Staff: everything except users/settings/roles admin and sales.backdate/sales.edit
-- (this exactly matches today's $roleRestrictions + SaleController checks)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_key`) VALUES
    (2, 'products.manage'),
    (2, 'categories.manage'),
    (2, 'stock.manage'),
    (2, 'suppliers.manage'),
    (2, 'purchases.manage'),
    (2, 'customers.manage'),
    (2, 'transactions.manage'),
    (2, 'distributor.manage'),
    (2, 'financial_accounts.access'),
    (2, 'expenses.manage'),
    (2, 'suspense.manage');

-- Cashier: none of the above (matches today — cashier can't reach any of these routes)

-- ------------------------------------------------------------
-- 4. users.role_id — points every existing user at their matching
--    system role, so the new permission check needs no fallback
--    logic. The legacy `role` ENUM column is left untouched and
--    still populated for the handful of cosmetic/display reads
--    that don't need to change (e.g. cashier's post-login landing
--    page). `role_id` is the source of truth for access control
--    going forward.
-- ------------------------------------------------------------
ALTER TABLE `users`
    ADD COLUMN `role_id` INT UNSIGNED NULL DEFAULT NULL AFTER `role`,
    ADD INDEX `idx_users_role_id` (`role_id`),
    ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

UPDATE `users` SET `role_id` = 1 WHERE `role` = 'admin'   AND `role_id` IS NULL;
UPDATE `users` SET `role_id` = 2 WHERE `role` = 'staff'   AND `role_id` IS NULL;
UPDATE `users` SET `role_id` = 3 WHERE `role` = 'cashier' AND `role_id` IS NULL;

SELECT 'Phase 2 migration complete: roles/permissions ready, all users backfilled.' AS status;
