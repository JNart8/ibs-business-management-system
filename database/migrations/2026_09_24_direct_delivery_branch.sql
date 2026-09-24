-- ============================================================
-- Direct deliveries: re-tag sales/purchases with their real branch.
--
-- Bug: DistributorController inserted the direct-delivery sale and
-- purchase without branch_id, so both fell to the column default (1,
-- Main Branch) whoever made them and wherever. Besides misplacing them
-- in branch-scoped lists and reports, this skewed the customer credit
-- settlement report, whose redemption side reads sales.branch_id.
--
-- The same code DID stamp the right branch (activeBranchId()) on the
-- delivery's stock_movements and customer_transactions rows, so those
-- are the source of truth here. Code now stamps branch_id on both
-- headers; this backfills existing ones.
--
-- Only a sale whose direct-delivery movements all agree on one branch
-- is touched. Safe to re-run: rows already correct are left alone.
-- ============================================================

UPDATE `sales` s
JOIN (
    SELECT reference_id AS sale_id, MIN(branch_id) AS branch_id
    FROM `stock_movements`
    WHERE reference_type = 'sale'
      AND notes = 'Direct Delivery - Customer sale'
      AND branch_id IS NOT NULL
    GROUP BY reference_id
    HAVING COUNT(DISTINCT branch_id) = 1
) mv ON mv.sale_id = s.id
SET s.branch_id = mv.branch_id
WHERE s.purchase_id IS NOT NULL
  AND s.branch_id <> mv.branch_id;

-- The linked purchase follows its sale
UPDATE `purchases` p
JOIN `sales` s ON s.id = p.sale_id
SET p.branch_id = s.branch_id
WHERE s.purchase_id = p.id
  AND p.branch_id <> s.branch_id;

SELECT 'Migration complete: direct-delivery sales/purchases re-tagged with their real branch.' AS status;
