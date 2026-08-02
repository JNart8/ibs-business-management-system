-- ============================================================
-- PHASE 1c MIGRATION: seed default Mobile Money account
-- ============================================================
-- `finance_accounts.sql` already seeds one default Cash Account on
-- every install. This adds a second default account (Mobile Money)
-- so that Core-plan clients — who can view/use accounts but cannot
-- create new ones — have both accounts they need out of the box.
--
-- Safe to run multiple times: only inserts if a mobile_money
-- account named 'Mobile Money Account' doesn't already exist.
-- Rename/set the provider (MTN, Telecel, AirtelTigo, etc.) per
-- client right after running this — there is no in-app edit
-- screen for accounts, so do it via phpMyAdmin during onboarding
-- if the client uses a specific provider.
-- ============================================================

INSERT INTO `accounts` (`name`, `type`, `provider`, `account_number`, `balance`, `is_default`, `is_active`)
SELECT 'Mobile Money Account', 'mobile_money', NULL, NULL, 0.00, 1, 1
WHERE NOT EXISTS (
    SELECT 1 FROM `accounts` WHERE `type` = 'mobile_money' AND `is_default` = 1
);

SELECT 'Phase 1c migration complete: default Mobile Money account seeded.' AS status;
