-- ============================================================
-- Snapshot cost of goods sold on each sale line.
--
-- Every COGS/profit report multiplied sale quantity by the product's
-- CURRENT products.average_cost, so each new purchase silently
-- rewrote the profit of every past period. sale_items.unit_cost now
-- freezes the cost at the moment of sale:
--   - normal sales:     products.average_cost at time of sale
--   - direct delivery:  that delivery's own purchase cost (goods pass
--                       straight through, never at average cost)
--
-- average_cost is also widened to 4 dp — a weighted average like
-- (10 × 12.50 + 3 × 13.00) / 13 = 12.6154 was being rounded to cents
-- on every purchase, and that error compounded.
--
-- Backfill:
--   1. Direct-delivery lines get their real cost from the linked
--      purchase (purchases.sale_id). Their historical profit CHANGES
--      — it was previously (wrongly) costed at average_cost.
--   2. Every other existing line is costed at today's average_cost,
--      i.e. exactly what reports showed before this migration. Those
--      periods are frozen from here on, not reconstructed.
-- ============================================================

ALTER TABLE `products`
MODIFY COLUMN `average_cost` DECIMAL(12,4) DEFAULT 0.0000 COMMENT 'Weighted moving average cost';

ALTER TABLE `sale_items`
ADD COLUMN `unit_cost` DECIMAL(12,4) NULL COMMENT 'Cost per unit at time of sale (COGS snapshot)' AFTER `unit_price`;

-- 1. Direct deliveries: cost = the delivery's own purchase cost
UPDATE `sale_items` si
JOIN `purchases` pu      ON pu.sale_id = si.sale_id
JOIN `purchase_items` pi ON pi.purchase_id = pu.id AND pi.product_id = si.product_id
SET si.unit_cost = pi.unit_cost
WHERE si.unit_cost IS NULL;

-- 2. Everything else: today's average cost (matches pre-migration reports)
UPDATE `sale_items` si
JOIN `products` p ON p.id = si.product_id
SET si.unit_cost = COALESCE(p.average_cost, 0)
WHERE si.unit_cost IS NULL;

SELECT 'Migration complete: sale_items.unit_cost added and backfilled; products.average_cost widened to 4 dp.' AS status;
