-- ============================================================
-- MIGRATION: sales.access permission
-- ============================================================
-- '/sales' and '/pos' have never had a permission of their own —
-- public/index.php's $permissionRestrictions comment documented
-- this as deliberate, and 8f6cd76 even taught the nav to always
-- show Sales under Finance for exactly that reason. A QA pass on
-- a fully locked-down custom role ("Inventory Clerk": products +
-- stock only) found the real cost of that: the role could still
-- open Sales, ring up sales, and void them from the POS.
--
-- Voiding already has a permission (`sales.edit`) but SaleController's
-- voidSale() never actually checked it — a separate bug fixed
-- alongside this one (see app/controllers/SaleController.php).
--
-- This migration adds `sales.access` ("Access Sales / POS") and
-- grants it to all three system roles so existing installs see NO
-- behavior change (Admin/Staff/Cashier could all reach Sales
-- before). Only new custom roles — like the one that surfaced this
-- gap — need it granted explicitly going forward.
-- ============================================================

INSERT INTO `permissions` (`key`, `label`, `category`) VALUES
    ('sales.access', 'Access Sales / POS', 'Sales')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_key`) VALUES
    (1, 'sales.access'),
    (2, 'sales.access'),
    (3, 'sales.access');

SELECT 'sales.access migration complete: Sales/POS now permission-gated, existing roles unaffected.' AS status;
