-- ============================================================
-- MIGRATION: POS default-customer setting
-- ============================================================
-- Adds a toggle for whether POS starts each sale with "Walk-in
-- Customer" pre-selected (the existing, hardcoded behavior) or
-- with no customer selected, forcing the cashier to actively choose
-- one every time. Defaults to 1 (today's behavior) so this migration
-- changes nothing for any existing install until someone opts out.
-- ============================================================

ALTER TABLE `settings`
    ADD COLUMN `pos_default_walkin` TINYINT(1) NOT NULL DEFAULT 1 AFTER `sale_discount_type`;

SELECT 'Migration complete: pos_default_walkin setting ready (defaults to on).' AS status;
