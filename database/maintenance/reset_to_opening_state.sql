-- ============================================================
-- RESET TO OPENING STATE — wipes all trading history.
--
--   !!! THIS PERMANENTLY DELETES DATA. BACK UP THE DATABASE FIRST !!!
--       mysqldump -u <user> -p <database> > backup_before_reset.sql
--   Run it when nobody is using the system (no sales in progress).
--
-- DELETES
--   Sales + items, purchases + items, direct deliveries, stock
--   movements, stock transfers + items, customer transactions (incl.
--   deposits), supplier transactions, account transactions, account
--   transfers (incl. branch settlements), expenses, suspense deposits,
--   product cost history, sale edit history, audit log (+ archive),
--   and which batches each sale / transfer line used.
--
-- KEEPS (unchanged)
--   Products (names, SKUs, prices, cost prices), categories, customers
--   (incl. credit limits and the walk-in customer), suppliers, branches,
--   users + branch assignments, roles/permissions, financial accounts,
--   settings, licence.
--
-- RESETS TO ZERO
--   Stock at every branch (and its batches), customer balances + total purchases,
--   supplier balances + total purchases (you owe no one), every
--   financial account balance.
--
-- The deletes run in one transaction: if anything fails, nothing is
-- changed. Record opening cash / stock afterwards through the app
-- (account deposit, stock in) so it has a proper audit trail.
-- ============================================================

-- What's there now (for your records)
SELECT 'BEFORE' AS stage,
    (SELECT COUNT(*) FROM sales)                 AS sales,
    (SELECT COUNT(*) FROM purchases)             AS purchases,
    (SELECT COUNT(*) FROM stock_movements)       AS stock_movements,
    (SELECT COUNT(*) FROM stock_transfers)       AS stock_transfers,
    (SELECT COUNT(*) FROM customer_transactions) AS customer_txns,
    (SELECT COUNT(*) FROM account_transactions)  AS account_txns,
    (SELECT COUNT(*) FROM expenses)              AS expenses,
    (SELECT COALESCE(SUM(quantity), 0) FROM branch_stock) AS total_stock_units,
    (SELECT COUNT(*) FROM products)              AS products_kept,
    (SELECT COUNT(*) FROM customers)             AS customers_kept;

-- The batch tables below need the 2026_09_25_batch_expiry_* migrations.
-- They're cleared explicitly: with FOREIGN_KEY_CHECKS = 0, InnoDB
-- doesn't cascade deletes to them.
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- ── Trading history ──────────────────────────────────────────
DELETE FROM sale_item_batches;       -- batches each sale line used
DELETE FROM sale_items;
DELETE FROM sales;
DELETE FROM purchase_items;
DELETE FROM purchases;
DELETE FROM stock_transfer_item_batches;   -- batches each transfer line carried
DELETE FROM stock_transfer_items;
DELETE FROM stock_transfers;
DELETE FROM stock_movements;
DELETE FROM product_cost_history;
DELETE FROM expenses;

-- ── Money history ────────────────────────────────────────────
DELETE FROM customer_transactions;   -- includes every customer deposit
DELETE FROM supplier_transactions;
DELETE FROM account_transactions;
DELETE FROM account_transfers;       -- includes customer-credit settlements
DELETE FROM suspense_transactions;

-- ── Logs ─────────────────────────────────────────────────────
-- Delete these three lines to keep the old audit trail instead.
DELETE FROM audit_history;
DELETE FROM audit_log;
DELETE FROM audit_log_archive;

-- ── Stock to zero ────────────────────────────────────────────
DELETE FROM branch_stock;            -- no row = 0 at that branch
DELETE FROM product_batches;         -- batch & expiry detail of that stock
UPDATE products SET current_stock = 0, last_purchase_date = NULL;

-- ── Balances to zero ─────────────────────────────────────────
UPDATE customers SET current_balance = 0, total_purchases = 0;
UPDATE suppliers SET current_balance = 0, total_purchases = 0;
UPDATE accounts  SET balance = 0;

-- Leave a record of the reset as the first entry of the new log
INSERT INTO audit_log (user_id, username, action, entity_type, entity_id, branch_id, details, ip_address)
VALUES (NULL, 'system', 'system.reset_to_opening_state', NULL, NULL, NULL,
        '{"note":"All trading history cleared; stock and balances reset to zero"}', NULL);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

-- Start record ids from 1 again (runs after the commit; harmless if skipped)
ALTER TABLE sale_item_batches     AUTO_INCREMENT = 1;
ALTER TABLE sale_items            AUTO_INCREMENT = 1;
ALTER TABLE sales                 AUTO_INCREMENT = 1;
ALTER TABLE purchase_items        AUTO_INCREMENT = 1;
ALTER TABLE purchases             AUTO_INCREMENT = 1;
ALTER TABLE stock_transfer_item_batches AUTO_INCREMENT = 1;
ALTER TABLE stock_transfer_items  AUTO_INCREMENT = 1;
ALTER TABLE stock_transfers       AUTO_INCREMENT = 1;
ALTER TABLE stock_movements       AUTO_INCREMENT = 1;
ALTER TABLE product_cost_history  AUTO_INCREMENT = 1;
ALTER TABLE expenses              AUTO_INCREMENT = 1;
ALTER TABLE customer_transactions AUTO_INCREMENT = 1;
ALTER TABLE supplier_transactions AUTO_INCREMENT = 1;
ALTER TABLE account_transactions  AUTO_INCREMENT = 1;
ALTER TABLE account_transfers     AUTO_INCREMENT = 1;
ALTER TABLE suspense_transactions AUTO_INCREMENT = 1;
ALTER TABLE branch_stock          AUTO_INCREMENT = 1;
ALTER TABLE product_batches       AUTO_INCREMENT = 1;

-- What's left (every count below should be 0, except the last two)
SELECT 'AFTER' AS stage,
    (SELECT COUNT(*) FROM sales)                 AS sales,
    (SELECT COUNT(*) FROM purchases)             AS purchases,
    (SELECT COUNT(*) FROM stock_movements)       AS stock_movements,
    (SELECT COUNT(*) FROM stock_transfers)       AS stock_transfers,
    (SELECT COUNT(*) FROM customer_transactions) AS customer_txns,
    (SELECT COUNT(*) FROM account_transactions)  AS account_txns,
    (SELECT COALESCE(SUM(quantity), 0) FROM branch_stock) AS total_stock_units,
    (SELECT COUNT(*) FROM customers WHERE current_balance <> 0) AS customers_nonzero,
    (SELECT COUNT(*) FROM suppliers WHERE current_balance <> 0) AS suppliers_nonzero,
    (SELECT COUNT(*) FROM accounts  WHERE balance <> 0)         AS accounts_nonzero,
    (SELECT COUNT(*) FROM products)              AS products_kept,
    (SELECT COUNT(*) FROM customers)             AS customers_kept;
